<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $page->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if (count($tokens))
        <style>
            :root {
                @foreach ($tokens as $token => $value)
                --{{ $token }}: {{ $value }};
                @endforeach
            }
        </style>
    @endif
</head>
<body class="bg-gray-50 text-gray-900">
    <main class="max-w-5xl mx-auto px-4 py-10 space-y-8">
        <h1 class="text-3xl font-bold">{{ $page->title }}</h1>

        @foreach ($renderedBlocks as $block)
            {{ $block }}
        @endforeach
    </main>
</body>
</html>
