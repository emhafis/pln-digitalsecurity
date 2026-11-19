<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Digital Security') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/favicon-hsse.png') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-inertia::head />
    </head>
    <body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased">
        <x-inertia::app />
    </body>
</html>
