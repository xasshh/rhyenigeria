@php($icons = trim((string) config('site.icons'), '/'))
@if ($icons === '')
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
@else
<link rel="icon" href="{{ asset($icons.'/favicon-48.png') }}" type="image/png" sizes="48x48">
@endif
<link rel="icon" href="{{ asset(ltrim($icons.'/favicon-32.png', '/')) }}" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="{{ asset(ltrim($icons.'/apple-touch-icon.png', '/')) }}">
