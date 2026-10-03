@php
    use App\Support\Locales;
    $locale = app()->getLocale();
    $loc = Locales::current();
    $team = $site['team'];
    $name = localized($team['name'] ?? '');
    $tagline = localized($team['tagline'] ?? '');
    $members = collect($site['members']);
    $byId = $members->keyBy('id');
    $levels = ['Junior' => 1, 'Mid' => 2, 'Senior' => 3, 'Lead' => 4];
    $hue = fn ($s) => crc32((string) $s) % 360;
    $initials = fn ($n) => collect(preg_split('/\s+/u', trim($n)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
    $stack = $members->flatMap(fn ($m) => collect($m['skills'] ?? [])->pluck('name'))
        ->merge(collect($site['projects'])->flatMap(fn ($p) => $p['tech'] ?? []))
        ->map(fn ($s) => trim($s))->filter()->unique(fn ($s) => mb_strtolower($s))->values();
    $categories = collect($site['projects'])->map(fn ($p) => localized($p['category'] ?? ''))->filter()->unique()->values();
    $ogImage = collect($site['projects'])->pluck('image')->filter()->first();
    $jsonLd = [
        '@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $name, 'url' => Locales::url($locale),
        'description' => $tagline, 'email' => $team['email'] ?? null,
        'sameAs' => collect($team['socials'] ?? [])->pluck('url')->filter()->values(),
        'member' => $members->map(fn ($m) => ['@type' => 'Person', 'name' => localized($m['name']), 'jobTitle' => localized($m['role'] ?? '')])->values(),
    ];
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $loc['dir'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $name }}{{ $tagline ? ' — '.$tagline : '' }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(localized($team['about'] ?? '') ?: $tagline, 160) }}">
    <meta name="theme-color" content="#08090f">
    <link rel="canonical" href="{{ Locales::url($locale) }}">
    @foreach (Locales::codes() as $code)
        <link rel="alternate" hreflang="{{ $code }}" href="{{ Locales::url($code) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ Locales::url(Locales::default()) }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $name }}">
    <meta property="og:title" content="{{ $name }}">
    <meta property="og:description" content="{{ $tagline }}">
    <meta property="og:url" content="{{ Locales::url($locale) }}">
    <meta property="og:locale" content="{{ $loc['og'] ?? $locale }}">
    @if ($og = \App\Support\Images::url($ogImage, 'og'))
        <meta property="og:image" content="{{ $og }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif
    @if ($locale === 'fa')
        <link rel="preload" href="{{ asset('fonts/Vazirmatn-FD-NL-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>
    @else
        <link rel="preload" href="{{ asset('fonts/plus-jakarta-sans-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    @endif
    <link rel="stylesheet" href="{{ asset_v('assets/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset_v('assets/site.css') }}">
    <style>:root { --font: {!! $loc['font'] !!}; }</style>
    <script>
        (function () { var t; try { t = localStorage.getItem('theme'); } catch (e) {}
            document.documentElement.dataset.theme = t || (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'); })();
    </script>
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
</head>
<body id="top">
<a class="skip" href="#main">{{ $name }}</a>
<div class="progress" aria-hidden="true"></div>

<header class="nav">
    <div class="container nav-inner">
        <a href="{{ Locales::url($locale) }}" class="logo">
            <span class="logo-mark">{{ mb_substr($name, 0, 1) }}</span><span>{{ $name }}</span>
        </a>
        <nav class="links" id="navLinks" aria-label="{{ __('site.nav.menu') }}">
            <a href="#services">{{ __('site.nav.services') }}</a>
            <a href="#team">{{ __('site.nav.team') }}</a>
            <a href="#projects">{{ __('site.nav.projects') }}</a>
            <a href="#contact">{{ __('site.nav.contact') }}</a>
        </nav>
        <div class="nav-tools">
            @if (count(Locales::codes()) > 1)
                <details class="lang">
                    <summary class="icon-btn wide" aria-label="{{ __('site.nav.language') }}"><x-icon name="globe"/><span>{{ strtoupper($locale) }}</span></summary>
                    <div class="lang-menu">
                        @foreach (Locales::all() as $code => $l)
                            <a href="{{ Locales::url($code) }}" hreflang="{{ $code }}" lang="{{ $code }}" @class(['on' => $code === $locale])>
                                {{ $l['native'] }} @if ($code === $locale)<x-icon name="check"/>@endif
                            </a>
                        @endforeach
                    </div>
                </details>
            @endif
            <button class="icon-btn" id="themeBtn" aria-label="{{ __('site.nav.theme') }}"><x-icon name="sun" class="ic sun"/><x-icon name="moon" class="ic moon"/></button>
            <button class="icon-btn menu-btn" id="menuBtn" aria-label="{{ __('site.nav.menu') }}" aria-expanded="false" aria-controls="navLinks"><x-icon name="menu"/></button>
        </div>
    </div>
</header>

<main id="main">
<section class="hero">
    <div class="hero-bg" aria-hidden="true"><div class="grid-bg"></div><div class="blob b1"></div><div class="blob b2"></div></div>
    <div class="container hero-inner">
        <span class="badge reveal"><i class="pulse"></i>{{ __('site.hero.badge') }}</span>
        <h1 class="reveal">{{ $tagline ?: $name }}</h1>
        <p class="lead reveal">{{ preg_split('/(?<=[.!?؟])\s+/u', localized($team['about'] ?? ''))[0] }}</p>
        <div class="hero-actions reveal">
            <a href="#projects" class="btn primary">{{ __('site.hero.cta_work') }} <x-icon name="arrow-right" class="ic flip"/></a>
            <a href="#contact" class="btn ghost">{{ __('site.hero.cta_contact') }}</a>
        </div>
        @if ($members->isNotEmpty())
            <a href="#team" class="hero-team reveal">
                <span class="stack">
                    @foreach ($members->take(5) as $m)
                        @include('site.partials.avatar', ['m' => $m, 'size' => 'xs'])
                    @endforeach
                </span>
                <span>{{ __('site.hero.team_line') }}</span>
            </a>
        @endif
        @if (! empty($team['stats']))
            <dl class="stats reveal">
                @foreach ($team['stats'] as $s)
                    <div class="stat"><dt>{{ localized($s['label'] ?? '') }}</dt><dd>{{ $s['value'] ?? '' }}</dd></div>
                @endforeach
            </dl>
        @endif
    </div>
</section>

@if (filled(localized($team['about'] ?? '')))
<section class="section container about" id="about">
    <div class="sec-head reveal"><span class="kicker">{{ __('site.about.kicker') }}</span><h2>{{ __('site.about.title') }}</h2></div>
    <p class="about-text reveal">{{ localized($team['about']) }}</p>
</section>
@endif

@if (! empty($site['services']))
<section class="section container" id="services">
    <div class="sec-head reveal"><span class="kicker">{{ __('site.services.kicker') }}</span><h2>{{ __('site.services.title') }}</h2></div>
    <div class="grid services">
        @foreach ($site['services'] as $i => $s)
            <article class="card service reveal" style="--d:{{ $i % 3 }}">
                <div class="s-top"><span class="s-icon">{{ $s['icon'] ?? '' }}</span><span class="s-num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span></div>
                <h3>{{ localized($s['title']) }}</h3>
                <p>{{ localized($s['desc'] ?? '') }}</p>
            </article>
        @endforeach
    </div>
</section>
@endif

@if ($members->isNotEmpty())
<section class="section container" id="team">
    <div class="sec-head reveal"><span class="kicker">{{ __('site.team.kicker') }}</span><h2>{{ __('site.team.title') }}</h2></div>
    @php($usedLevels = array_values(array_intersect(array_keys($levels), $members->pluck('level')->unique()->all())))
    @if (count($usedLevels) > 1)
        <div class="filters" data-filter="team" role="toolbar">
            <button class="chip on" data-v="">{{ __('site.team.all') }}</button>
            @foreach ($usedLevels as $lv)
                <button class="chip" data-v="{{ $lv }}">{{ __("site.levels.$lv") }}</button>
            @endforeach
        </div>
    @endif
    <div class="grid members" id="teamGrid">
        @foreach ($members as $i => $m)
            <article class="card member reveal" style="--d:{{ $i % 3 }}" data-v="{{ $m['level'] }}" data-member="{{ $m['id'] }}" tabindex="0" role="button" aria-haspopup="dialog">
                <div class="m-head">
                    @include('site.partials.avatar', ['m' => $m, 'size' => 'md'])
                    <div class="m-id"><h3>{{ localized($m['name']) }}</h3><p>{{ localized($m['role'] ?? '') }}</p></div>
                </div>
                @include('site.partials.level', ['m' => $m])
                <div class="chips">
                    @foreach (collect($m['skills'] ?? [])->sortByDesc('level')->take(4) as $sk)
                        <span class="tech">{{ $sk['name'] }}</span>
                    @endforeach
                </div>
                <span class="more">{{ __('site.team.view') }} <x-icon name="arrow-right" class="ic flip"/></span>
            </article>
        @endforeach
    </div>
    @foreach ($members as $m)
        <template id="member-{{ $m['id'] }}">@include('site.partials.member-modal', ['m' => $m])</template>
    @endforeach
    <dialog class="modal" id="memberModal" aria-label="{{ __('site.team.title') }}"><div class="modal-body"></div></dialog>
</section>
@endif

@if ($stack->count() > 2)
<section class="section stack-sec" aria-label="{{ __('site.stack.title') }}">
    <div class="container sec-head reveal center"><span class="kicker">{{ __('site.stack.kicker') }}</span><h2>{{ __('site.stack.title') }}</h2></div>
    @foreach ([$stack, $stack->reverse()] as $row)
        <div class="marquee" dir="ltr"><div class="track-m @if ($loop->last) rev @endif">
            @foreach ([1, 2] as $copy)
                <div class="m-row" @if ($copy === 2) aria-hidden="true" @endif>
                    @foreach ($row as $tech)<span class="pill-tech">{{ $tech }}</span>@endforeach
                </div>
            @endforeach
        </div></div>
    @endforeach
</section>
@endif

@if (! empty($site['projects']))
<section class="section container" id="projects">
    <div class="sec-head reveal"><span class="kicker">{{ __('site.projects.kicker') }}</span><h2>{{ __('site.projects.title') }}</h2></div>
    @if ($categories->count() > 1)
        <div class="filters" data-filter="projects" role="toolbar">
            <button class="chip on" data-v="">{{ __('site.projects.all') }}</button>
            @foreach ($categories as $c)<button class="chip" data-v="{{ $c }}">{{ $c }}</button>@endforeach
        </div>
    @endif
    <div class="grid projects" id="projectsGrid">
        @foreach ($site['projects'] as $i => $p)
            @php($title = localized($p['title']))
            <article class="card project reveal" style="--d:{{ $i % 2 }}" data-v="{{ localized($p['category'] ?? '') }}">
                <div class="cover" style="--h:{{ $hue($title) }}">
                    @if (\App\Support\Images::url($p['image'] ?? null, 'cover'))
                        <x-img :src="$p['image']" preset="cover" sizes="(max-width: 720px) 100vw, 560px" :alt="$title"/>
                    @else
                        <span class="cover-ph" aria-hidden="true">{{ mb_substr($title, 0, 1) }}</span>
                    @endif
                    @if (filled(localized($p['category'] ?? '')))<span class="cat">{{ localized($p['category']) }}</span>@endif
                </div>
                <div class="p-body">
                    <div class="p-top"><h3>{{ $title }}</h3>@if (filled(localized($p['year'] ?? '')))<span class="year"><x-icon name="calendar"/>{{ localized($p['year']) }}</span>@endif</div>
                    <p>{{ localized($p['desc'] ?? '') }}</p>
                    <div class="chips">@foreach ($p['tech'] ?? [] as $tech)<span class="tech">{{ $tech }}</span>@endforeach</div>
                    <div class="p-foot">
                        @php($pm = collect($p['members'] ?? [])->map(fn ($id) => $byId[$id] ?? null)->filter())
                        <span class="stack" title="{{ __('site.projects.team') }}">
                            @foreach ($pm as $m)<button class="stack-btn" data-member="{{ $m['id'] }}" aria-label="{{ localized($m['name']) }}">@include('site.partials.avatar', ['m' => $m, 'size' => 'xs'])</button>@endforeach
                        </span>
                        @if (filled($p['link'] ?? null) && ($p['link'] ?? '') !== '#')
                            <a class="visit" href="{{ safe_url($p['link']) }}" target="_blank" rel="noopener">{{ __('site.projects.visit') }} <x-icon name="arrow-up-right" class="ic flip"/></a>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</section>
@endif

<section class="section container" id="contact">
    <div class="contact reveal">
        <div class="contact-info">
            <span class="kicker">{{ __('site.contact.kicker') }}</span>
            <h2>{{ __('site.contact.title') }}</h2>
            <p>{{ __('site.contact.text') }}</p>
            <ul class="c-list">
                @if (filled($team['email'] ?? null))<li><x-icon name="mail"/><a href="mailto:{{ $team['email'] }}" dir="ltr">{{ $team['email'] }}</a></li>@endif
                @if (filled($team['phone'] ?? null))<li><x-icon name="phone"/><a href="tel:{{ preg_replace('/[^\d+]/', '', $team['phone']) }}" dir="ltr">{{ $team['phone'] }}</a></li>@endif
                @if (filled(localized($team['location'] ?? '')))<li><x-icon name="map-pin"/><span>{{ localized($team['location']) }}</span></li>@endif
            </ul>
            <div class="socials">@foreach ($team['socials'] ?? [] as $s)<x-social :link="$s"/>@endforeach</div>
        </div>
        <form class="contact-form" id="contactForm" action="{{ url('/api/contact') }}" method="post" novalidate
              data-sending="{{ __('site.contact.sending') }}" data-sent="{{ __('site.contact.sent') }}"
              data-throttled="{{ __('site.contact.throttled') }}" data-error="{{ __('site.contact.error') }}">
            <label><span>{{ __('site.contact.name') }}</span><input name="name" required maxlength="120" autocomplete="name"></label>
            <label><span>{{ __('site.contact.email') }}</span><input name="email" type="email" required dir="ltr" autocomplete="email"></label>
            <label class="wide"><span>{{ __('site.contact.subject') }}</span><input name="subject" maxlength="190"></label>
            <label class="wide"><span>{{ __('site.contact.body') }}</span><textarea name="body" required minlength="5" rows="5"></textarea></label>
            <input name="website" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
            <button class="btn primary wide">{{ __('site.contact.send') }} <x-icon name="send" class="ic flip"/></button>
            <p class="cf-msg wide" role="status" aria-live="polite"></p>
        </form>
    </div>
</section>
</main>

<footer class="footer">
    <div class="container foot-inner">
        <div class="foot-brand">
            <a href="{{ Locales::url($locale) }}" class="logo"><span class="logo-mark">{{ mb_substr($name, 0, 1) }}</span><span>{{ $name }}</span></a>
            <p>{{ $tagline }}</p>
        </div>
        <nav class="foot-links">
            <a href="#services">{{ __('site.nav.services') }}</a><a href="#team">{{ __('site.nav.team') }}</a>
            <a href="#projects">{{ __('site.nav.projects') }}</a><a href="#contact">{{ __('site.nav.contact') }}</a>
        </nav>
        <nav class="foot-links">
            @foreach (Locales::all() as $code => $l)<a href="{{ Locales::url($code) }}" hreflang="{{ $code }}" lang="{{ $code }}">{{ $l['native'] }}</a>@endforeach
        </nav>
    </div>
    <div class="container foot-bottom">
        <span>© {{ local_year() }} {{ $name }}. {{ __('site.footer.rights') }}</span>
        <a href="#top" class="to-top">{{ __('site.footer.top') }} <x-icon name="arrow-up"/></a>
    </div>
</footer>

<button class="fab to-top-fab" id="toTop" aria-label="{{ __('site.footer.top') }}" hidden><x-icon name="arrow-up"/></button>

@if (count(Locales::codes()) > 1)
    <div class="lang-banner" id="langBanner" hidden>
        @foreach (Locales::all() as $code => $l)
            @continue($code === $locale)
            <template data-lang="{{ $code }}"><span lang="{{ $code }}" dir="{{ $l['dir'] }}">{{ __('site.lang_suggest', [], $code) }}</span>
                <a class="btn primary sm" href="{{ Locales::url($code) }}" hreflang="{{ $code }}">{{ __('site.lang_switch', [], $code) }}</a></template>
        @endforeach
        <div class="lb-body"></div>
        <button class="icon-btn" data-dismiss aria-label="{{ __('site.dismiss') }}"><x-icon name="x"/></button>
    </div>
@endif

@if ($chat)
    @include('site.partials.chat', ['chat' => $chat])
@endif

<script src="{{ asset_v('assets/site.js') }}" defer></script>
</body>
</html>
