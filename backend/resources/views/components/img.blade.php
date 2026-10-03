@props(['src' => null, 'preset', 'sizes' => '100vw', 'alt' => '', 'eager' => false])
@php($url = \App\Support\Images::url($src, $preset))
@if ($url)
    @php($ratio = config("site.images.presets.{$preset}.ratio"))
    <img src="{{ $url }}" @if ($set = \App\Support\Images::srcset($src, $preset)) srcset="{{ $set }}" sizes="{{ $sizes }}" @endif
         alt="{{ $alt }}" width="800" height="{{ (int) round(800 / $ratio) }}"
         loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" {{ $attributes }}>
@endif
