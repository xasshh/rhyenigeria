@props(['title' => null, 'charts' => false, 'noindex' => false])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#109648">
    <title>{{ $title ? $title.' · ' : '' }}Renewed Hope Youth Engagement — {{ config('membership.slogan') }}</title>
    <meta name="description" content="Renewed Hope Youth Engagement (RHYE). {{ config('membership.slogan') }} Join the movement and get your digital membership card.">
    @include('partials.head-icons')
    @if ($noindex)<meta name="robots" content="noindex, nofollow">@endif
    {{ $head ?? '' }}
    @fonts
    @vite(array_values(array_filter(['resources/css/app.css', 'resources/js/site.js', $charts ? 'resources/js/charts.js' : null])))
</head>
<body {{ $attributes->merge(['class' => 'min-h-dvh bg-surface font-body text-ink antialiased']) }}>
    {{ $slot }}
</body>
</html>
