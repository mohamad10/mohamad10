<button class="icon-btn close" data-close aria-label="{{ __('site.team.close') }}"><x-icon name="x"/></button>
<div class="mm-head">
    @include('site.partials.avatar', ['m' => $m, 'size' => 'lg'])
    <div>
        <h2>{{ localized($m['name']) }}</h2>
        <p class="muted">{{ localized($m['role'] ?? '') }}</p>
        @include('site.partials.level', ['m' => $m])
    </div>
</div>
@if (filled(localized($m['bio'] ?? '')))<p class="mm-bio">{{ localized($m['bio']) }}</p>@endif
<dl class="facts">
    @foreach (['location' => 'map-pin', 'education' => 'graduation-cap', 'languages' => 'languages'] as $k => $ic)
        @if (filled(localized($m[$k] ?? '')))
            <div><dt><x-icon :name="$ic"/>{{ __("site.team.$k") }}</dt><dd>{{ localized($m[$k]) }}</dd></div>
        @endif
    @endforeach
</dl>
@if (! empty($m['skills']))
    <h4>{{ __('site.team.skills') }}</h4>
    <div class="bars">
        @foreach (collect($m['skills'])->sortByDesc('level') as $sk)
            <div class="bar"><div class="bar-l"><span>{{ $sk['name'] }}</span><span>{{ (int) $sk['level'] }}%</span></div>
                <div class="track" role="meter" aria-valuenow="{{ (int) $sk['level'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $sk['name'] }}"><div class="fill" style="--w:{{ min(100, (int) $sk['level']) }}%"></div></div></div>
        @endforeach
    </div>
@endif
@php($projs = collect($site['projects'])->filter(fn ($p) => in_array($m['id'], $p['members'] ?? [], true)))
@if ($projs->isNotEmpty())
    <h4>{{ __('site.team.projects') }}</h4>
    <div class="chips">@foreach ($projs as $p)<span class="tag">{{ localized($p['title']) }}</span>@endforeach</div>
@endif
@if (! empty($m['links']))
    <div class="socials">@foreach ($m['links'] as $l)<x-social :link="$l"/>@endforeach</div>
@endif
