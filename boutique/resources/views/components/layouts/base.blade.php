@props(['title' => null, 'description' => null, 'image' => null, 'installable' => false])
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#3730a3">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @if ($description)<meta name="description" content="{{ $description }}">@endif
    {{-- Aperçu affiché quand le lien est collé dans WhatsApp, Facebook ou TikTok. --}}
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title ?? config('app.name').' · Votre boutique sur WhatsApp' }}">
    @if ($description)<meta property="og:description" content="{{ $description }}">@endif
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $image ?? asset('og-wanllan.jpg') }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    @if ($installable === true)
        {{-- Espace commerçant : installable comme une application (écran d'accueil du téléphone). --}}
        <link rel="manifest" href="{{ asset('manifest.json') }}">
        <script>if ('serviceWorker' in navigator) addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));</script>
    @endif
    @vite('resources/css/app.css')
</head>
<body {{ $attributes }}>
    {{ $slot }}
</body>
</html>
