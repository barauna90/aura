@props(['size' => 'md'])
{{-- Símbolo oficial (A + anel + estrela) recortado da logo enviada; o texto acompanha o tema. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 font-semibold tracking-tight']) }}>
    <img src="{{ asset('images/logo-mark.png') }}" alt="" class="{{ $size === 'lg' ? 'h-10' : 'h-8' }} w-auto" width="1274" height="668" aria-hidden="true">
    <span class="{{ $size === 'lg' ? 'text-2xl' : 'text-lg' }} leading-none">
        Aura <span class="block text-[0.55em] font-medium uppercase tracking-[0.3em] text-muted">Simulados</span>
    </span>
</span>
