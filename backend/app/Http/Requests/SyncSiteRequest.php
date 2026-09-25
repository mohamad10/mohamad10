<?php

namespace App\Http\Requests;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $str = ['nullable', 'string', 'max:255'];
        $text = ['nullable', 'string', 'max:5000'];
        $image = ['nullable', 'string', 'max:2048'];

        return [
            'team' => ['required', 'array'],
            'team.name' => ['required', 'string', 'max:120'],
            'team.tagline' => $str, 'team.about' => $text,
            'team.email' => ['nullable', 'email'], 'team.phone' => $str, 'team.location' => $str,
            'team.stats' => ['nullable', 'array', 'max:12'],
            'team.stats.*.value' => $str, 'team.stats.*.label' => $str,
            'team.socials' => ['nullable', 'array', 'max:20'],
            'team.socials.*.label' => $str, 'team.socials.*.url' => $str,

            'services' => ['present', 'array', 'max:50'],
            'services.*.icon' => ['nullable', 'string', 'max:16'],
            'services.*.title' => ['required', 'string', 'max:255'],
            'services.*.desc' => $text,

            'members' => ['present', 'array', 'max:200'],
            'members.*.id' => ['nullable', 'string', 'max:64', 'distinct'],
            'members.*.name' => ['required', 'string', 'max:255'],
            'members.*.role' => $str,
            'members.*.level' => ['required', Rule::in(Member::LEVELS)],
            'members.*.years' => ['nullable', 'integer', 'min:0', 'max:80'],
            'members.*.avatar' => $image,
            'members.*.available' => ['boolean'],
            'members.*.location' => $str, 'members.*.education' => $str, 'members.*.languages' => $str,
            'members.*.bio' => $text,
            'members.*.skills' => ['nullable', 'array', 'max:50'],
            'members.*.skills.*.name' => $str,
            'members.*.skills.*.level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'members.*.links' => ['nullable', 'array', 'max:20'],
            'members.*.links.*.label' => $str, 'members.*.links.*.url' => $str,

            'projects' => ['present', 'array', 'max:500'],
            'projects.*.title' => ['required', 'string', 'max:255'],
            'projects.*.category' => $str, 'projects.*.year' => ['nullable', 'string', 'max:16'],
            'projects.*.image' => $image, 'projects.*.link' => $str, 'projects.*.desc' => $text,
            'projects.*.tech' => ['nullable', 'array', 'max:30'], 'projects.*.tech.*' => ['string', 'max:60'],
            'projects.*.members' => ['nullable', 'array'], 'projects.*.members.*' => ['string', 'max:64'],
        ];
    }
}
