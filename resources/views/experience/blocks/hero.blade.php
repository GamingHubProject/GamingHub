<div
    class="rounded-xl px-8 py-16 text-center text-white bg-cover bg-center"
    style="background-color: var(--color-primary, #4f46e5); {{ $backgroundImage ? "background-image: linear-gradient(rgba(0,0,0,0.35), rgba(0,0,0,0.35)), url('".$backgroundImage."')" : '' }}"
>
    <h2 class="text-3xl md:text-4xl font-bold">{{ $heading }}</h2>

    @if ($subheading)
        <p class="mt-3 text-lg opacity-90 max-w-2xl mx-auto">{{ $subheading }}</p>
    @endif

    @if ($ctaLabel && $ctaUrl)
        <a
            href="{{ $ctaUrl }}"
            class="inline-block mt-6 rounded-lg bg-white px-5 py-2.5 font-semibold"
            style="color: var(--color-primary, #4f46e5)"
        >{{ $ctaLabel }}</a>
    @endif
</div>
