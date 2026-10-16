@props(['title' => null, 'description' => null])
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
    @vite('resources/css/app.css')
</head>
<body {{ $attributes }}>
    {{ $slot }}
</body>
</html>
