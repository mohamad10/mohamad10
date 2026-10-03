@php($rank = ['Junior' => 1, 'Mid' => 2, 'Senior' => 3, 'Lead' => 4][$m['level']] ?? 1)
<div class="m-meta">
    <span class="lvl lvl-{{ $m['level'] }}">
        <span class="lvl-bars" aria-hidden="true">@for ($i = 1; $i <= 4; $i++)<i @class(['on' => $i <= $rank])></i>@endfor</span>
        {{ __('site.levels.'.$m['level']) }}
    </span>
    @if (! empty($m['years']))<span class="meta-item"><x-icon name="briefcase"/>{{ trans_choice('site.team.years', $m['years'], ['count' => $m['years']]) }}</span>@endif
    <span @class(['meta-item', 'avail' => ! empty($m['available'])])>{{ ! empty($m['available']) ? __('site.team.available') : __('site.team.busy') }}</span>
</div>
