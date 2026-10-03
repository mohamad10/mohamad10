{!! "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach (\App\Support\Locales::codes() as $code)
    <url>
        <loc>{{ \App\Support\Locales::url($code) }}</loc>
@foreach (\App\Support\Locales::codes() as $alt)
        <xhtml:link rel="alternate" hreflang="{{ $alt }}" href="{{ \App\Support\Locales::url($alt) }}"/>
@endforeach
    </url>
@endforeach
</urlset>
