@props(['link'])
@php
    $label = $link['label'] ?? '';
    $brand = \Illuminate\Support\Str::of($label)->lower()->replace(['twitter', 'stack overflow'], ['x', 'stackoverflow'])->replaceMatches('/[^a-z]/', '')->toString();
    $hasBrand = $brand && is_file(public_path("assets/brands/{$brand}.svg"));
@endphp
<a href="{{ safe_url($link['url'] ?? null) }}" target="_blank" rel="noopener me" {{ $attributes->merge(['class' => 'social']) }}>
    @if ($hasBrand)
        <span class="brand-ic" style="--src:url('{{ asset("assets/brands/{$brand}.svg") }}')" aria-hidden="true"></span>
    @else
        <x-icon name="link"/>
    @endif
    <span>{{ $label }}</span>
</a>
