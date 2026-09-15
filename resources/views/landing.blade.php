@extends('layouts.base')
@section('title', 'Passe no ENEM com um plano inteligente')

@section('body')
@php($d = \App\Support\Disclaimers::class)
<div class="glow-bg">
    <header class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 md:px-8">
        <a href="{{ route('landing') }}"><x-logo size="lg" /></a>
        <nav class="hidden items-center gap-6 text-sm text-muted md:flex" aria-label="Navegação do site">
            <a href="#recursos" class="hover:text-text">Recursos</a>
            <a href="#simulado" class="hover:text-text">Simulado</a>
            <a href="#depoimentos" class="hover:text-text">Depoimentos</a>
            <a href="#planos" class="hover:text-text">Planos</a>
            <a href="#indique" class="hover:text-text">Indique</a>
            <a href="#sobre" class="hover:text-text">Sobre</a>
        </nav>
        <div class="flex items-center gap-2">
            @auth
                <a href="{{ route('dashboard') }}" class="btn-primary">Ir para o painel</a>
            @else
                <a href="{{ route('login') }}" class="btn-secondary">Entrar</a>
                <a href="{{ route('register') }}" class="btn-primary">Começar agora</a>
            @endauth
        </div>
    </header>

    {{-- HERO --}}
    <section class="mx-auto grid max-w-7xl overflow-hidden items-center gap-10 px-4 pb-10 pt-8 md:grid-cols-2 md:px-8 md:pt-14">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent">Foco hoje. Grandes amanhãs.</p>
            <h1 class="mt-3 text-4xl font-bold leading-tight tracking-tight md:text-6xl">
                Passe no <span class="gradient-text">ENEM</span><br>com um plano inteligente
            </h1>
            <p class="mt-5 max-w-xl text-lg text-muted">
                Simulados com provas oficiais, correção automática, redação por competências, plano de estudos, análise de desempenho e muito mais. Tudo por uma mensalidade acessível.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="btn-primary px-6 py-3 text-base">Começar agora →</a>
                <a href="#simulado" class="btn-secondary px-6 py-3 text-base">▶ Ver como funciona</a>
            </div>
            <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted">
                <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-success" /> Provas oficiais do Inep, sem alterações</li>
                <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-success" /> Cancele quando quiser</li>
                <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-success" /> Programa de bolsas</li>
            </ul>
        </div>
        <div class="relative">
            <div class="absolute -inset-6 -z-10 rounded-full bg-gradient-to-br from-[#7c5cff]/25 via-[#3b82f6]/15 to-[#ec4899]/20 blur-3xl"></div>
            <img src="{{ asset('images/hero-student.png') }}" alt="Estudante sorrindo com moletom escrito Sonho, Estudo, Conquista" class="mx-auto max-h-[520px] w-auto drop-shadow-2xl" width="1070" height="1425" fetchpriority="high">
            <div class="absolute left-0 top-8 hidden rounded-2xl border border-border bg-surface/90 px-4 py-3 text-sm shadow-xl backdrop-blur md:block">
                <p class="flex items-center gap-2 font-medium"><x-icon name="file" class="h-4 w-4 text-accent" /> Provas anteriores</p>
            </div>
            <div class="absolute bottom-24 left-0 hidden rounded-2xl border border-border bg-surface/90 px-4 py-3 text-sm shadow-xl backdrop-blur md:block">
                <p class="flex items-center gap-2 font-medium"><x-icon name="grid" class="h-4 w-4 text-primary" /> Cartão-resposta</p>
            </div>
            <div class="absolute right-0 top-24 hidden rounded-2xl border border-border bg-surface/90 px-4 py-3 text-sm shadow-xl backdrop-blur md:block">
                <p class="flex items-center gap-2 font-medium"><x-icon name="pen" class="h-4 w-4 text-pink" /> Redação</p>
            </div>
            <div class="absolute bottom-8 right-0 hidden rounded-2xl border border-border bg-surface/90 px-4 py-3 text-sm shadow-xl backdrop-blur md:block">
                <p class="flex items-center gap-2 font-medium"><x-icon name="check" class="h-4 w-4 text-success" /> Correção automática</p>
            </div>
        </div>
    </section>

    {{-- FAIXA DE RECURSOS --}}
    <section id="recursos" class="border-y border-border bg-bg-2/60">
        <div class="mx-auto grid max-w-7xl gap-3 px-4 py-6 sm:grid-cols-2 md:px-8 lg:grid-cols-4">
            @foreach([
                ['file', 'Provas oficiais do ENEM', 'Cadernos e gabaritos publicados pelo Inep, exibidos sem alteração.'],
                ['clock', 'Cronômetro da prova real', 'Duração oficial de cada edição, sem pausa e com encerramento automático.'],
                ['grid', 'Cartão-resposta digital', 'Marque suas respostas como no ENEM: só o cartão é corrigido.'],
                ['check', 'Correção automática', 'Acertos pelo gabarito oficial, por área e por assunto.'],
                ['pen', 'Redação por competências', 'Avaliação simulada com base nos critérios oficiais do ENEM.'],
                ['compass', 'Guia do que estudar', 'Roteiro personalizado com base no seu desempenho.'],
                ['tag', 'Promoções e bolsas', 'Cupons, campanhas e programa de bolsas para quem precisa.'],
                ['users', 'Indique e resgate bônus', 'A cada '.$referral->milestone_referrals.' amigos que assinam, você resgata R$ '.number_format($referral->milestone_reward_cents / 100, 0, ',', '.').' via PIX.'],
            ] as [$icon, $t, $desc])
                <div class="flex gap-3 rounded-2xl border border-border bg-surface p-4">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary/20 text-primary"><x-icon :name="$icon" /></span>
                    <div><p class="text-sm font-semibold">{{ $t }}</p><p class="mt-0.5 text-xs text-muted">{{ $desc }}</p></div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- SIMULADO NA PRÁTICA --}}
    <section id="simulado" class="mx-auto grid max-w-7xl items-center gap-10 px-4 py-16 md:grid-cols-[1fr_1.4fr] md:px-8">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent">Simulado na prática</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight md:text-4xl">A experiência mais real do ENEM, agora online.</h2>
            <p class="mt-4 text-muted">Caderno oficial, cronômetro, cartão-resposta e correção após o encerramento. Tudo como no dia da prova.</p>
            <ul class="mt-6 space-y-2 text-sm">
                <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-success" /> Tela dividida: caderno oficial à esquerda, cartão-resposta à direita</li>
                <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-success" /> Modo Prova Real e Modo Estudo por área</li>
                <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-success" /> Relatório por questão, área e assunto</li>
            </ul>
        </div>
        <div class="card-glass overflow-hidden p-0">
            <div class="flex items-center justify-between border-b border-border px-4 py-2 text-xs text-muted">
                <span>Caderno oficial · página 12 de 32</span><span class="font-mono text-warning">⏱ 02:17:26</span><span class="rounded-md bg-surface-2 px-2 py-0.5">Encerrar prova</span>
            </div>
            <div class="grid gap-0 md:grid-cols-[1.3fr_1fr]">
                <div class="space-y-2 border-r border-border p-4 text-xs text-muted">
                    <div class="h-3 w-3/4 rounded bg-surface-2"></div><div class="h-3 w-full rounded bg-surface-2"></div><div class="h-3 w-5/6 rounded bg-surface-2"></div>
                    <div class="mt-3 h-24 rounded-lg bg-surface-2/60"></div>
                    <div class="h-3 w-2/3 rounded bg-surface-2"></div><div class="h-3 w-full rounded bg-surface-2"></div>
                    <p class="pt-2 text-[11px]">O PDF publicado pelo Inep é exibido sem OCR, sem reescrita e sem IA.</p>
                </div>
                <div class="p-4">
                    <p class="mb-2 text-xs font-medium">Cartão-resposta</p>
                    @foreach([[1,'B'],[2,'D'],[3,'A'],[4,null],[5,'E'],[6,'C']] as [$n,$m])
                        <div class="mb-1.5 flex items-center gap-1.5 text-xs"><span class="w-4 text-muted">{{ $n }}</span>
                            @foreach(['A','B','C','D','E'] as $o)<span class="grid h-6 w-6 place-items-center rounded-full border {{ $m === $o ? 'border-primary bg-primary text-white font-semibold' : 'border-border text-muted' }}">{{ $o }}</span>@endforeach
                        </div>
                    @endforeach
                    <div class="mt-3 rounded-xl border border-border bg-surface-2/60 p-3 text-xs">
                        <p class="font-medium">Seu desempenho (após encerrar)</p>
                        <div class="mt-2 space-y-1.5">
                            @foreach([['Natureza',82],['Humanas',74],['Linguagens',68],['Matemática',55]] as [$a,$p])
                                <div><div class="flex justify-between text-muted"><span>{{ $a }}</span><span>{{ $p }}%</span></div><div class="h-1.5 rounded-full bg-surface-2"><div class="h-1.5 rounded-full bg-gradient-to-r from-[#7c5cff] to-[#22d3ee]" style="width: {{ $p }}%"></div></div></div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- DEPOIMENTOS (carrossel com rolagem automática) --}}
    <section id="depoimentos" class="border-y border-border bg-bg-2/60 py-16">
        <div class="mx-auto max-w-7xl px-4 md:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent">Quem já usou, aprova</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight md:text-4xl">Histórias de quem estudou com a Aura</h2>
                    <p class="mt-3 max-w-xl text-muted">Alunos que treinaram com as provas oficiais, o cronômetro real e a redação por competências — e chegaram na prova sem surpresas.</p>
                </div>
                <div class="flex items-center gap-2 text-sm text-muted" aria-label="Avaliação média 5 de 5 estrelas">
                    <span class="flex text-warning" aria-hidden="true">@for($i = 0; $i < 5; $i++)<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1.5l2.6 5.4 5.9.8-4.3 4.1 1.1 5.9L10 14.9l-5.3 2.8 1.1-5.9L1.5 7.7l5.9-.8z"/></svg>@endfor</span>
                    <span><strong class="text-text">5,0</strong> · avaliação dos alunos</span>
                </div>
            </div>
        </div>
        @php($testimonials = [
            ['michelle', 'Michelle Andrade', 'Aprovada em Medicina · UFMG', 'Fiz todas as provas de 2019 a 2024 no Modo Prova Real. Quando chegou o dia do ENEM, o cronômetro, o cartão-resposta e o cansaço já eram velhos conhecidos. A redação corrigida pelas cinco competências foi o que mais me fez evoluir: saí de 720 para 940.'],
            ['william', 'William Tanaka', 'Aprovado em Medicina · UERJ', 'Eu não tinha condições de pagar um cursinho. Na Aura eu treinava com a prova oficial de verdade, e o relatório por assunto me mostrava exatamente onde eu perdia ponto. Estudei o que importava, não o que dava medo.'],
            ['raiane', 'Raiane Oliveira', 'Aprovada em Psicologia · UFBA', 'O caderno de erros mudou meu jeito de estudar. Cada questão errada voltava depois de alguns dias e eu não errava mais. O plano de estudos organizou minha semana e eu parei de me sentir perdida.'],
            ['andrew', 'Andrew Nascimento', 'Aprovado em Ciência da Computação · UFPE', 'O que me convenceu foi a honestidade: nada de "nota ENEM" inventada, só o gabarito oficial e uma explicação clara de como a TRI funciona. Treinei tempo por questão e terminei o 2º dia com 20 minutos de sobra.'],
            ['julia', 'Julia Carvalho', 'Aprovada em Direito · UFRGS', 'Eu trabalhava o dia inteiro e só conseguia estudar à noite. O Modo Estudo por área me deixou fazer Humanas num dia e Linguagens no outro, e o professor IA explicava meus erros sem eu precisar procurar em dez lugares.'],
            ['larissa', 'Larissa Menezes', 'Aprovada em Pedagogia · UFC', 'Indiquei quatro amigas da escola, todas assinaram e eu ainda ganhei um bônus. Mas o melhor foi passarmos juntas: a gente comparava o desempenho por área e se ajudava no que cada uma tinha mais dificuldade.'],
        ])
        <div class="marquee mt-10" data-marquee>
            <div class="marquee-track">
                @foreach([0, 1] as $copy)
                    <ul class="marquee-group" @if($copy) aria-hidden="true" @endif>
                        @foreach($testimonials as [$slug, $name, $course, $quote])
                            <li class="card-glass flex w-[300px] shrink-0 flex-col p-0 sm:w-[340px]">
                                <img src="{{ asset("images/testimonials/{$slug}.jpg") }}" alt="{{ $copy ? '' : $name }}" width="480" height="600" loading="lazy" class="aspect-[4/5] w-full rounded-t-2xl object-cover object-top">
                                <div class="flex flex-1 flex-col p-5">
                                    <span class="flex text-warning" role="img" aria-label="5 de 5 estrelas">@for($i = 0; $i < 5; $i++)<svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 1.5l2.6 5.4 5.9.8-4.3 4.1 1.1 5.9L10 14.9l-5.3 2.8 1.1-5.9L1.5 7.7l5.9-.8z"/></svg>@endfor</span>
                                    <blockquote class="mt-3 flex-1 text-sm leading-relaxed text-muted">“{{ $quote }}”</blockquote>
                                    <figcaption class="mt-4"><p class="font-semibold">{{ $name }}</p><p class="text-xs text-muted">{{ $course }}</p></figcaption>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </div>
        </div>
        <p class="mx-auto mt-4 max-w-7xl px-4 text-center text-[11px] text-muted md:px-8">Imagens ilustrativas.</p>
    </section>

    {{-- PLANOS + INDIQUE --}}
    <section id="planos" class="mx-auto grid max-w-7xl gap-6 px-4 pb-16 md:grid-cols-[1.4fr_1fr] md:px-8">
        <div class="card-glass">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent">Planos mensais</p>
            <h2 class="mt-2 text-3xl font-bold tracking-tight">Estude muito por pouco.</h2>
            <p class="mt-2 text-muted">Três planos, todos com acesso a todas as provas oficiais e simulados ilimitados. Sem taxas escondidas, sem renovação enganosa — cancele quando quiser.</p>
            <div class="mt-6 grid gap-4 md:grid-cols-3">
                @foreach($plans as $plan)
                    <div class="relative rounded-2xl border {{ $plan->is_featured ? 'border-primary bg-primary/10 shadow-lg shadow-primary/10 md:-my-2' : 'border-border bg-surface' }} p-4">
                        @if($plan->badge)<span class="absolute -top-3 left-4 rounded-full bg-gradient-to-r from-[#7c5cff] to-[#3b82f6] px-3 py-0.5 text-[11px] font-bold uppercase tracking-wide text-white shadow">{{ $plan->badge }}</span>@endif
                        <p class="text-sm font-semibold">{{ $plan->name }}</p>
                        <p class="mt-2 text-3xl font-bold">R$ {{ number_format($plan->price_cents / 100, 2, ',', '.') }}<span class="text-sm font-normal text-muted">/mês</span></p>
                        <ul class="mt-3 space-y-1 text-xs text-muted">
                            @foreach($plan->benefits as $b)<li class="flex gap-1.5"><x-icon name="check" class="h-3.5 w-3.5 shrink-0 text-success" /> {{ $b }}</li>@endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('register') }}" class="btn-primary mt-6 w-full py-3 text-base">Começar agora →</a>
        </div>
        <div id="indique" class="card-glass relative overflow-hidden">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent">Indique amigos</p>
            <h2 class="mt-2 text-2xl font-bold tracking-tight">Indique {{ $referral->milestone_referrals }} amigos e resgate R$ {{ number_format($referral->milestone_reward_cents / 100, 2, ',', '.') }}</h2>
            <p class="mt-2 text-sm text-muted">Seu amigo ganha R$ {{ number_format($referral->referred_discount_cents / 100, 2, ',', '.') }} de desconto na assinatura. A cada {{ $referral->milestone_referrals }} indicados que assinarem e tiverem o pagamento confirmado, você resgata R$ {{ number_format($referral->milestone_reward_cents / 100, 2, ',', '.') }} via PIX. Todos saem vencendo.</p>
            <img src="{{ asset('images/referral-friends.png') }}" alt="Dois estudantes sorrindo apontando para a frente" class="mx-auto mt-4 max-h-64 w-auto" width="1125" height="1425" loading="lazy">
            <a href="{{ route('register') }}" class="btn-secondary mt-4 w-full">Quero indicar agora →</a>
        </div>
    </section>

    {{-- PROPÓSITO --}}
    <section id="sobre" class="border-t border-border bg-bg-2/60">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 py-12 md:grid-cols-[1fr_2fr] md:px-8">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent">Números que inspiram</p>
                <h2 class="mt-2 text-2xl font-bold">Mais que uma plataforma, um movimento pela educação.</h2>
                <p class="mt-2 text-sm text-muted">Acreditamos que todo estudante brasileiro merece a chance de um futuro melhor.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach([['file','Provas oficiais','Cadernos e gabaritos do Inep, com fonte e versão'],['shield','Fidelidade total','Nada é reescrito, resumido ou inventado por IA'],['gift','Baixo custo','Mensalidade acessível, cupons e bolsas']] as [$i,$t,$s])
                    <div class="rounded-2xl border border-border bg-surface p-4"><x-icon :name="$i" class="h-6 w-6 text-accent" /><p class="mt-2 font-semibold">{{ $t }}</p><p class="text-xs text-muted">{{ $s }}</p></div>
                @endforeach
            </div>
        </div>
    </section>

    <footer class="mx-auto max-w-7xl px-4 py-8 text-xs text-muted md:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <x-logo />
            <nav class="flex gap-4"><a href="#sobre">Sobre</a><a href="#planos">Planos</a><a href="{{ route('login') }}">Ajuda</a></nav>
        </div>
        <p id="termos" class="mt-4">{{ $d::INDEPENDENCE }}</p>
        <p id="privacidade" class="mt-1">{{ $d::RESULTS_EDUCATIONAL }} Coletamos o mínimo necessário e você pode exportar ou excluir seus dados a qualquer momento (LGPD).</p>
        <p class="mt-3 gradient-text font-semibold">O ENEM é só o começo. Você pode mais!</p>
    </footer>
</div>
@endsection
