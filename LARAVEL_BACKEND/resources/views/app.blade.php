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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="/images/branding/relaysiq-favicon.png" type="image/png">
    <link rel="icon" href="/images/branding/relaysiq-mark.png" type="image/png" sizes="512x512">
@if ($brandFavicon)
    <link rel="apple-touch-icon" href="{{ $brandFavicon }}">
@else
    <link rel="apple-touch-icon" href="/images/branding/relaysiq-app-icon.png">
@endif
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
    <meta property="og:type" content="{{ $seo['ogType'] ?? 'website' }}">
    <meta property="og:site_name" content="{{ $seo['siteName'] ?? config('app.name') }}">
    @if (!empty($seo['ogTitle']))
    <meta property="og:title" content="{{ $seo['ogTitle'] }}">
    @endif
    @if (!empty($seo['ogDescription']))
    <meta property="og:description" content="{{ $seo['ogDescription'] }}">
    @endif
    @if (!empty($seo['ogUrl']))
    <meta property="og:url" content="{{ $seo['ogUrl'] }}">
    @endif
    @if (!empty($seo['ogImage']))
    <meta property="og:image" content="{{ $seo['ogImage'] }}">
    @endif
    <meta name="twitter:card" content="{{ $seo['twitterCard'] ?? 'summary_large_image' }}">
    @if (!empty($seo['ogTitle']))
    <meta name="twitter:title" content="{{ $seo['ogTitle'] }}">
    @endif
    @if (!empty($seo['ogDescription']))
    <meta name="twitter:description" content="{{ $seo['ogDescription'] }}">
    @endif
    @if (!empty($seo['ogImage']))
    <meta name="twitter:image" content="{{ $seo['ogImage'] }}">
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
</body>
</html>
