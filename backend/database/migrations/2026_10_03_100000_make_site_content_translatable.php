<?php

use App\Support\Locales;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns text columns into JSON translation maps ({"en": "...", "fa": "..."}).
 * Existing single-language content was written in Persian, so it is kept under "fa".
 */
return new class extends Migration
{
    private const LEGACY = 'fa';

    private array $columns = [
        'services' => ['title', 'desc'],
        'members' => ['name', 'role', 'location', 'education', 'languages', 'bio'],
        'projects' => ['title', 'category', 'year', 'desc'],
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $c) {
                    $t->json("{$c}_i18n")->nullable();
                }
            });
            DB::table($table)->orderBy('id')->each(function ($row) use ($table, $columns) {
                DB::table($table)->where('id', $row->id)->update(collect($columns)->mapWithKeys(fn ($c) => [
                    "{$c}_i18n" => blank($row->$c) ? null : json_encode([self::LEGACY => $row->$c], JSON_UNESCAPED_UNICODE),
                ])->all());
            });
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn($columns));
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $c) {
                    $t->renameColumn("{$c}_i18n", $c);
                }
            });
        }

        $team = DB::table('settings')->where('key', 'team')->value('value');
        if ($team) {
            $team = json_decode($team, true);
            foreach (['name', 'tagline', 'about', 'location'] as $k) {
                if (isset($team[$k]) && ! is_array($team[$k])) {
                    $team[$k] = [self::LEGACY => $team[$k]];
                }
            }
            foreach ($team['stats'] ?? [] as $i => $s) {
                if (! is_array($s['label'] ?? null)) {
                    $team['stats'][$i]['label'] = [self::LEGACY => $s['label'] ?? ''];
                }
            }
            DB::table('settings')->where('key', 'team')->update(['value' => json_encode($team, JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $c) {
                    $t->text("{$c}_plain")->nullable();
                }
            });
            DB::table($table)->orderBy('id')->each(function ($row) use ($table, $columns) {
                DB::table($table)->where('id', $row->id)->update(collect($columns)->mapWithKeys(fn ($c) => [
                    "{$c}_plain" => Locales::pick(json_decode($row->$c ?? 'null', true), self::LEGACY) ?: null,
                ])->all());
            });
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn($columns));
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $c) {
                    $t->renameColumn("{$c}_plain", $c);
                }
            });
        }
    }
};
