@php($sizes = ['xs' => '32px', 'md' => '64px', 'lg' => '112px'][$size])
<span class="avatar av-{{ $size }}" style="--h:{{ crc32((string) $m['id']) % 360 }}">
    @if (\App\Support\Images::url($m['avatar'] ?? null, 'avatar'))
        <x-img :src="$m['avatar']" preset="avatar" :sizes="$sizes" :alt="localized($m['name'])"/>
    @else
        <span class="av-ph" aria-hidden="true">{{ collect(preg_split('/\s+/u', trim(localized($m['name']))))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}</span>
    @endif
    @if ($size !== 'xs' && ! empty($m['available']))<i class="dot" title="{{ __('site.team.available') }}"></i>@endif
</span>
