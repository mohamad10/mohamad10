<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>پنل مدیریت</title>
    <link rel="stylesheet" href="{{ asset_v('assets/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset_v('assets/admin.css') }}">
    <script>
        window.ADMIN = { api: @json(url('/api')), site: @json(url('/')) };
        (function () { var t; try { t = localStorage.getItem('theme'); } catch (e) {}
            document.documentElement.dataset.theme = t || (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'); })();
    </script>
</head>
<body>
<div id="lock" class="lock">
    <form class="card lock-card" id="lockForm">
        <span class="logo-mark">⌘</span>
        <h1>پنل مدیریت</h1>
        <p class="muted" id="lockMsg">با حساب مدیر وارد شوید</p>
        <input type="email" id="email" placeholder="ایمیل" dir="ltr" required autocomplete="username">
        <input type="password" id="pass" placeholder="رمز عبور" dir="ltr" required autocomplete="current-password">
        <button class="btn primary">ورود</button>
    </form>
</div>

<div id="app" hidden>
    <header class="top">
        <div class="top-inner">
            <span class="brand"><span class="logo-mark sm">⌘</span> پنل مدیریت</span>
            <span class="saved" id="saved" role="status"></span>
            <div class="top-tools">
                <div class="seg" id="langSwitch" title="زبان محتوایی که ویرایش می‌کنید"></div>
                <a href="{{ url('/') }}" target="_blank" class="btn ghost sm" id="viewSite">مشاهده سایت ↗</a>
                <button class="btn ghost sm" id="logout">خروج</button>
            </div>
        </div>
    </header>
    <main class="admin">
        <nav class="tabs" id="tabs"></nav>
        <section id="panel"></section>
    </main>
</div>

<script src="{{ asset_v('assets/admin.js') }}"></script>
</body>
</html>
