<?php

namespace App\Support;

/** Constantes de domínio do ENEM compartilhadas por serviços e views. */
final class Enem
{
    public const AREAS = ['LINGUAGENS', 'HUMANAS', 'NATUREZA', 'MATEMATICA', 'REDACAO'];

    public const OBJECTIVE_AREAS = ['LINGUAGENS', 'HUMANAS', 'NATUREZA', 'MATEMATICA'];

    public const AREA_LABEL = [
        'LINGUAGENS' => 'Linguagens, Códigos e suas Tecnologias',
        'HUMANAS' => 'Ciências Humanas e suas Tecnologias',
        'NATUREZA' => 'Ciências da Natureza e suas Tecnologias',
        'MATEMATICA' => 'Matemática e suas Tecnologias',
        'REDACAO' => 'Redação',
    ];

    public const AREA_SHORT = [
        'LINGUAGENS' => 'Linguagens',
        'HUMANAS' => 'Humanas',
        'NATUREZA' => 'Natureza',
        'MATEMATICA' => 'Matemática',
        'REDACAO' => 'Redação',
    ];

    public const APPLICATIONS = [
        'REGULAR' => 'Aplicação regular',
        'REAPLICACAO' => 'Reaplicação',
        'PPL' => 'PPL',
        'DIGITAL' => 'ENEM Digital',
    ];

    public const LANGUAGES = ['INGLES' => 'Inglês', 'ESPANHOL' => 'Espanhol'];

    public const OPTIONS = ['A', 'B', 'C', 'D', 'E'];

    public const BOOKLET_COLORS = ['AZUL', 'AMARELO', 'BRANCO', 'ROSA', 'CINZA', 'VERDE', 'LARANJA', 'NAO_APLICAVEL'];

    public const PIPELINE_STAGES = ['IMPORTED', 'AUTO_VALIDATED', 'HUMAN_REVIEW_1', 'HUMAN_REVIEW_2', 'PUBLISHED'];

    public const STAGE_LABEL = [
        'IMPORTED' => 'Importado',
        'AUTO_VALIDATED' => 'Validação automática',
        'HUMAN_REVIEW_1' => 'Revisão humana 1',
        'HUMAN_REVIEW_2' => 'Revisão humana 2',
        'PUBLISHED' => 'Publicado',
    ];

    public const ESSAY_COMPETENCIES = [
        1 => 'Domínio da modalidade escrita formal da língua portuguesa.',
        2 => 'Compreensão da proposta e desenvolvimento do tema dentro da estrutura do texto dissertativo-argumentativo.',
        3 => 'Seleção, relação, organização e interpretação de informações, fatos, opiniões e argumentos em defesa de um ponto de vista.',
        4 => 'Conhecimento dos mecanismos linguísticos necessários à construção da argumentação.',
        5 => 'Elaboração de proposta de intervenção para o problema abordado, respeitando os direitos humanos.',
    ];

    public const ESSAY_LEVELS = [0, 40, 80, 120, 160, 200];

    public const MENU = [
        ['route' => 'dashboard', 'label' => 'Início', 'icon' => 'home'],
        ['route' => 'exams.index', 'label' => 'Provas anteriores', 'icon' => 'file'],
        ['route' => 'simulados', 'label' => 'Simulados', 'icon' => 'grid'],
        ['route' => 'essays.index', 'label' => 'Redação', 'icon' => 'pen'],
        ['route' => 'study.plan', 'label' => 'Plano de estudos', 'icon' => 'calendar'],
        ['route' => 'notebook.index', 'label' => 'Caderno de erros', 'icon' => 'book'],
        ['route' => 'performance', 'label' => 'Meu desempenho', 'icon' => 'chart'],
        ['route' => 'guide', 'label' => 'Guia ENEM', 'icon' => 'compass'],
        ['route' => 'referral.index', 'label' => 'Indique e ganhe', 'icon' => 'users'],
        ['route' => 'subscription.index', 'label' => 'Minha assinatura', 'icon' => 'card'],
        ['route' => 'profile.edit', 'label' => 'Perfil', 'icon' => 'user'],
        ['route' => 'help', 'label' => 'Ajuda', 'icon' => 'help'],
    ];
}
