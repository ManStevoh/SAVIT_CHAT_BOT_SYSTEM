<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@php
    $seo = is_array($page['props']['seo'] ?? null) ? $page['props']['seo'] : null;
    $brandFavicon = null;
    try {
        $brandFavicon = app(\App\Services\Cms\CmsSeoService::class)->faviconUrl();
    } catch (\Throwable) {
        $brandFavicon = null;
    }
@endphp
<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-V0C1J7FCY2"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-V0C1J7FCY2');
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="icon" id="dynamic-favicon" href="{{ $brandFavicon ?: '/favicon-light.png?v=4' }}" type="image/png">
    <link rel="icon" href="/favicon-dark.png?v=4" type="image/png" media="(prefers-color-scheme: dark)">
    <link rel="icon" href="{{ $brandFavicon ?: '/favicon-light.png?v=4' }}" type="image/png" media="(prefers-color-scheme: light)">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $brandFavicon ?: '/favicon-light.png?v=4' }}">
    <script>
        (function() {
            function updateFavicon() {
                var isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                var href = isDark ? '/favicon-dark.png?v=4' : '/favicon-light.png?v=4';
                var link = document.getElementById('dynamic-favicon');
                if (link) { link.href = href; }
            }
            try {
                updateFavicon();
                if (window.matchMedia) {
                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', updateFavicon);
                }
            } catch (e) {}
        })();
    </script>
@php
    $ogImage = $seo['ogImage'] ?? $seo['image'] ?? null;
@endphp
@if ($seo)
    <title>{{ $seo['title'] ?? config('app.name', 'RelayIQ') }}</title>
    @if (!empty($seo['description']))
    <meta name="description" content="{{ $seo['description'] }}">
    @endif
    @if (!empty($seo['robots']))
    <meta name="robots" content="{{ $seo['robots'] }}">
    @endif
    @if (!empty($seo['canonical']))
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    @endif
    @foreach (($seo['sameAs'] ?? []) as $sameAsUrl)
        @if (is_string($sameAsUrl) && $sameAsUrl !== '')
    <link rel="me" href="{{ $sameAsUrl }}">
        @endif
    @endforeach
    @if (!empty($seo['googleSiteVerification']))
    <meta name="google-site-verification" content="{{ $seo['googleSiteVerification'] }}">
    @endif
    <meta property="og:type" content="{{ $seo['ogType'] ?? 'website' }}">
    <meta property="og:site_name" content="{{ $seo['siteName'] ?? config('app.name') }}">
    @if (!empty($seo['ogLocale']))
    <meta property="og:locale" content="{{ $seo['ogLocale'] }}">
    @endif
    @if (!empty($seo['ogTitle']))
    <meta property="og:title" content="{{ $seo['ogTitle'] }}">
    @endif
    @if (!empty($seo['ogDescription']))
    <meta property="og:description" content="{{ $seo['ogDescription'] }}">
    @endif
    @if (!empty($seo['ogUrl']))
    <meta property="og:url" content="{{ $seo['ogUrl'] }}">
    @endif
    @if (!empty($ogImage))
    <meta property="og:image" content="{{ $ogImage }}">
    @endif
    @if (!empty($ogImage) && !empty($seo['ogImageWidth']))
    <meta property="og:image:width" content="{{ $seo['ogImageWidth'] }}">
    @endif
    @if (!empty($ogImage) && !empty($seo['ogImageHeight']))
    <meta property="og:image:height" content="{{ $seo['ogImageHeight'] }}">
    @endif
    @if (!empty($seo['articlePublishedTime']))
    <meta property="article:published_time" content="{{ $seo['articlePublishedTime'] }}">
    @endif
    @if (!empty($seo['articleModifiedTime']))
    <meta property="article:modified_time" content="{{ $seo['articleModifiedTime'] }}">
    @endif
    <meta name="twitter:card" content="{{ $seo['twitterCard'] ?? 'summary_large_image' }}">
    @if (!empty($seo['twitterSite']))
    <meta name="twitter:site" content="{{ $seo['twitterSite'] }}">
    @endif
    @if (!empty($seo['ogTitle']))
    <meta name="twitter:title" content="{{ $seo['ogTitle'] }}">
    @endif
    @if (!empty($seo['ogDescription']))
    <meta name="twitter:description" content="{{ $seo['ogDescription'] }}">
    @endif
    @if (!empty($ogImage))
    <meta name="twitter:image" content="{{ $ogImage }}">
    @endif
    @if (!empty($seo['jsonLd']))
    <script type="application/ld+json">{!! json_encode($seo['jsonLd'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
@else
    <title inertia>{{ config('app.name', 'RelayIQ') }}</title>
@endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-serif:400|plus-jakarta-sans:400,500,600,700|geist-mono:400&display=swap" rel="stylesheet" />
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
    <script>
        (function () {
            function showBootFailure(message) {
                var app = document.getElementById('app');
                if (!app || app.dataset.bootFailed === '1') return;
                if (app.childElementCount > 0 && (app.innerText || '').trim().length > 0) return;
                app.dataset.bootFailed = '1';
                app.innerHTML = '<div style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;padding:24px;font-family:system-ui,sans-serif;color:#111">' +
                    '<strong style="font-size:18px">App failed to load</strong>' +
                    '<p style="max-width:28rem;text-align:center;color:#555;margin:0">' + message + '</p>' +
                    '<button type="button" style="padding:8px 14px;border-radius:8px;border:1px solid #ccc;background:#fff;cursor:pointer" onclick="location.reload(true)">Hard refresh</button>' +
                    '</div>';
            }
            window.addEventListener('error', function (event) {
                var target = event && event.target;
                if (target && target.tagName === 'SCRIPT' && target.src) {
                    showBootFailure('A required script could not be loaded (often after a rebuild). Press Ctrl+Shift+R.');
                }
            }, true);
            setTimeout(function () {
                var app = document.getElementById('app');
                if (!app) return;
                if (app.childElementCount === 0 || !(app.innerText || '').trim()) {
                    showBootFailure('The dashboard did not start. Press Ctrl+Shift+R to load the latest assets.');
                }
            }, 12000);
        })();
    </script>
</body>
</html>
