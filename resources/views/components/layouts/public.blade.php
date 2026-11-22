@props(['title' => null, 'description' => null, 'image' => null, 'current' => null])
@php($slogan = config('site.slogan'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f2a1c">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('site.name') }}</title>
    <meta name="description" content="{{ $description ?? config('site.name').' ('.config('site.short').'): '.$slogan }}">
    <meta property="og:title" content="{{ $title ?? config('site.name') }}">
    <meta property="og:description" content="{{ $description ?? $slogan }}">
    <meta property="og:type" content="article">
    @if ($image)<meta property="og:image" content="{{ $image }}"><meta name="twitter:card" content="summary_large_image">@endif
    @include('partials.head-icons')
    @fonts
    @vite(['resources/css/app.css', 'resources/js/site.js'])
</head>
<body class="bg-white font-display text-brand antialiased">
    @include('partials.public-header', ['current' => $current])
    <main>{{ $slot }}</main>
    @include('partials.public-footer')
</body>
</html>
