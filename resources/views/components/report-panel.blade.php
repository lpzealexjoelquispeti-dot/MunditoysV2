@props(['title', 'subtitle' => null, 'note' => null])
<section {{ $attributes->class(['min-w-0 rounded-2xl border border-orange-100 bg-white p-5 shadow-sm sm:p-6']) }}>
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-brand-ink">{{ $title }}</h2>
        @if($subtitle)<p class="mt-1 text-sm leading-relaxed text-slate-500">{{ $subtitle }}</p>@endif
    </div>
    {{ $slot }}
    @if($note)<p class="mt-5 border-t border-slate-100 pt-4 text-sm leading-relaxed text-slate-600">{{ $note }}</p>@endif
</section>
