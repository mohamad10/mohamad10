@props(['name'])
<svg {{ $attributes->merge(['class' => 'ic', 'aria-hidden' => 'true']) }}><use href="{{ asset('assets/icons.svg') }}#i-{{ $name }}"/></svg>
