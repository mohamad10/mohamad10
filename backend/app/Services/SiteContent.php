<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Project;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reads and writes the whole site document in the same shape the frontend uses
 * (see assets/data.js): { team, services, members, projects }.
 */
class SiteContent
{
    public const CACHE_KEY = 'site-content';

    public function get(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => $this->build());
    }

    public function build(): array
    {
        return [
            'team' => Setting::get('team', []),
            'services' => Service::orderBy('position')->get()
                ->map(fn (Service $s) => $s->only('icon', 'title', 'desc'))->all(),
            'members' => Member::orderBy('position')->get()->map(fn (Member $m) => [
                'id' => $m->slug,
                ...$m->only('name', 'role', 'level', 'years', 'avatar', 'available', 'location', 'education', 'languages', 'bio'),
                'skills' => $m->skills ?? [],
                'links' => $m->links ?? [],
            ])->all(),
            'projects' => Project::with('members:id,slug')->orderBy('position')->get()->map(fn (Project $p) => [
                ...$p->only('title', 'category', 'year', 'image', 'link', 'desc'),
                'tech' => $p->tech ?? [],
                'members' => $p->members->pluck('slug')->all(),
            ])->all(),
        ];
    }

    /** Replace all site content with the given (validated) document. */
    public function sync(array $data): array
    {
        DB::transaction(function () use ($data) {
            Setting::put('team', $data['team']);

            Service::query()->delete();
            foreach ($data['services'] ?? [] as $i => $s) {
                Service::create([...$s, 'position' => $i]);
            }

            $keep = [];
            foreach ($data['members'] ?? [] as $i => $m) {
                $slug = $m['id'] ?? null ?: 'm'.Str::lower(Str::random(8));
                Member::updateOrCreate(['slug' => $slug], [
                    ...collect($m)->except('id')->all(),
                    'skills' => $m['skills'] ?? [],
                    'links' => $m['links'] ?? [],
                    'position' => $i,
                ]);
                $keep[] = $slug;
            }
            Member::whereNotIn('slug', $keep)->delete();
            $ids = Member::pluck('id', 'slug');

            Project::query()->delete();
            foreach ($data['projects'] ?? [] as $i => $p) {
                $project = Project::create([...collect($p)->except('members')->all(), 'tech' => $p['tech'] ?? [], 'position' => $i]);
                $project->members()->sync(collect($p['members'] ?? [])->map(fn ($s) => $ids[$s] ?? null)->filter()->unique()->values());
            }
        });

        Cache::forget(self::CACHE_KEY);

        return $this->get();
    }
}
