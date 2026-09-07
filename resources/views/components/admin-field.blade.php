@props(['label', 'name', 'hint' => null])
<label class="block">
    <span class="mb-1.5 block text-sm font-semibold text-brand-ink">{{ $label }}</span>
    {{ $slot }}
    @if ($hint)<span class="mt-1 block text-xs text-slate-500">{{ $hint }}</span>@endif
    @error($name)<span role="status" class="mt-1 block text-xs font-medium text-brand-red">{{ $message }}</span>@enderror
</label>
