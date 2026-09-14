@props(['size' => 'md'])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 font-semibold tracking-tight']) }}>
    <span class="grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br from-[#7c5cff] to-[#22d3ee] text-sm font-bold text-white" aria-hidden="true">A</span>
    <span class="{{ $size === 'lg' ? 'text-2xl' : 'text-lg' }}">Aura<span class="gradient-text">Simulados</span></span>
</span>
