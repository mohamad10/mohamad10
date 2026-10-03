<?php

namespace App\Http\Requests;

use App\Models\Member;
use App\Support\Locales;
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
        $image = ['nullable', 'string', 'max:2048'];

        return [
            'team' => ['required', 'array'],
            ...$this->tr('team.name', 120, true), ...$this->tr('team.tagline'), ...$this->tr('team.about', 5000),
            ...$this->tr('team.location'),
            'team.email' => ['nullable', 'email'], 'team.phone' => $str,
            'team.stats' => ['nullable', 'array', 'max:12'],
            'team.stats.*.value' => ['nullable', 'string', 'max:32'], ...$this->tr('team.stats.*.label'),
            'team.socials' => ['nullable', 'array', 'max:20'],
            'team.socials.*.label' => $str, 'team.socials.*.url' => $str,

            'services' => ['present', 'array', 'max:50'],
            'services.*.icon' => ['nullable', 'string', 'max:16'],
            ...$this->tr('services.*.title', 255, true), ...$this->tr('services.*.desc', 5000),

            'members' => ['present', 'array', 'max:200'],
            'members.*.id' => ['nullable', 'string', 'max:64', 'distinct'],
            ...$this->tr('members.*.name', 255, true), ...$this->tr('members.*.role'),
            'members.*.level' => ['required', Rule::in(Member::LEVELS)],
            'members.*.years' => ['nullable', 'integer', 'min:0', 'max:80'],
            'members.*.avatar' => $image,
            'members.*.available' => ['boolean'],
            ...$this->tr('members.*.location'), ...$this->tr('members.*.education'), ...$this->tr('members.*.languages'),
            ...$this->tr('members.*.bio', 5000),
            'members.*.skills' => ['nullable', 'array', 'max:50'],
            'members.*.skills.*.name' => $str,
            'members.*.skills.*.level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'members.*.links' => ['nullable', 'array', 'max:20'],
            'members.*.links.*.label' => $str, 'members.*.links.*.url' => $str,

            'projects' => ['present', 'array', 'max:500'],
            ...$this->tr('projects.*.title', 255, true), ...$this->tr('projects.*.category'),
            ...$this->tr('projects.*.year', 32), ...$this->tr('projects.*.desc', 5000),
            'projects.*.image' => $image, 'projects.*.link' => $str,
            'projects.*.tech' => ['nullable', 'array', 'max:30'], 'projects.*.tech.*' => ['string', 'max:60'],
            'projects.*.members' => ['nullable', 'array'], 'projects.*.members.*' => ['string', 'max:64'],
        ];
    }

    /** Rules for a translatable field: an object keyed by known locale codes. The default locale is required when $required. */
    private function tr(string $field, int $max = 255, bool $required = false): array
    {
        $rules = [
            $field => [$required ? 'required' : 'nullable', 'array:'.implode(',', Locales::codes())],
            "{$field}.*" => ['nullable', 'string', "max:{$max}"],
        ];
        if ($required) {
            $rules["{$field}.".Locales::default()] = ['required', 'string', "max:{$max}"];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['*.'.Locales::default().'.required' => 'متن زبان پیش‌فرض ('.Locales::default().') الزامی است.'];
    }
}
