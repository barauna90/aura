# Aura Simulados — Preparação intensiva para o ENEM

Plataforma SaaS (PHP 8.3 · Laravel 13 · MySQL 8) para estudantes que não podem pagar um cursinho: provas oficiais anteriores exibidas **exatamente como o Inep publicou**, cronômetro real, cartão-resposta digital, correção pelo gabarito oficial, redação avaliada pelas cinco competências (dois avaliadores independentes + terceiro em divergência), plano de estudos adaptativo, caderno de erros, assinatura mensal via **Asaas** com liberação automática de acesso, programa de indicação (R$ 40 a cada 4 indicados pagantes) e bolsas.

> **Princípio inegociável:** o sistema nunca inventa questões, alternativas, gabaritos, textos motivadores, temas, durações, notas oficiais ou regras do Inep. Todo conteúdo oficial guarda fonte, versão, checksum e status de auditoria, e só aparece como "prova oficial" quando `VERIFIED` + `PUBLISHED`. Veja [docs/OFFICIAL_CONTENT_POLICY.md](docs/OFFICIAL_CONTENT_POLICY.md).

Documentação: [ARCHITECTURE](docs/ARCHITECTURE.md) · [AGENTS](docs/AGENTS.md) · [OFFICIAL_CONTENT_POLICY](docs/OFFICIAL_CONTENT_POLICY.md) · [ASAAS](docs/ASAAS.md) · [ROADMAP](docs/ROADMAP.md)

## Requisitos

- PHP 8.3+ com extensões `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`, `intl`
- Composer 2 · Node 20+ (só para compilar os assets) · MySQL 8 (ou MariaDB 10.6+)

## Instalação

```bash
composer install
cp .env.example .env            # ajuste DB_*, APP_URL e (opcional) ASAAS_*
php artisan key:generate
php artisan migrate --seed      # 3 planos pagos (Rede Pública R$ 29,90 · Estudante R$ 39,90 · Intensivo R$ 49,90), admin, revisor — NÃO cria provas
npm install && npm run build    # gera public/build
php artisan serve               # http://localhost:8000
```

Em produção: `php artisan queue:work` (fila `database` para a correção de redação) e `php artisan schedule:run` no cron a cada minuto (encerramento de provas expiradas, expiração de assinaturas, validação de indicações e geração de bônus). Aponte o document root para `public/`.

Credenciais do seed (troque em produção): `admin@aura.local / Admin123!Troque` e `revisor@aura.local / Revisor123!Troque`.

### Usuários de teste (fora de produção)

`migrate --seed` também roda o `DemoUsersSeeder` (ou `php artisan db:seed --class=DemoUsersSeeder`), que cria um usuário por perfil e por plano — assinaturas já `ACTIVE` localmente, sem passar pelo Asaas. Senha de todos: **`Teste123!`**

| E-mail | Perfil | Acesso |
|---|---|---|
| `superadmin.teste@aura.local` | SUPER_ADMIN | painel completo + acesso integral da equipe |
| `admin.teste@aura.local` | ADMIN | painel administrativo + acesso integral da equipe |
| `revisor.teste@aura.local` | REVIEWER | revisão de conteúdo oficial + acesso integral |
| `aluno.redepublica@aura.local` | STUDENT | Rede Pública (4 redações/mês) |
| `aluno.estudante@aura.local` | STUDENT | Plano Estudante (8 redações/mês) |
| `aluno.intensivo@aura.local` | STUDENT | Plano Intensivo (15 redações/mês, Modo Intensivo, prioridade) |
| `aluno.bolsista@aura.local` | STUDENT | Bolsa de estudos de 90 dias |
| `aluno.semplano@aura.local` | STUDENT | sem assinatura (vê a vitrine de planos) |

O seeder se recusa a rodar com `APP_ENV=production`.

## Planos

Não existe plano gratuito nem período de teste. Ao criar a conta o aluno cai direto na escolha do plano e pagamento; até o webhook do Asaas confirmar o pagamento (ou o admin conceder bolsa), o middleware `subscribed` bloqueia **todas** as funções — só ficam acessíveis Minha assinatura, Perfil, Ajuda e Sair. Os três planos são editáveis em **Administração → Planos** (preço, benefícios, limites, selo e destaque):

| Plano | Preço | Correções de redação/mês | Extras |
|---|---|---|---|
| Rede Pública | R$ 29,90 | 4 | Plano de estudos, Indique e ganhe |
| Plano Estudante (destaque, selo PROMOÇÃO) | R$ 39,90 | 8 | Plano de estudos, Indique e ganhe |
| Plano Intensivo | R$ 49,90 | 15 | Modo Intensivo ENEM, prioridade na correção, Indique e ganhe |

Todos incluem acesso a todas as provas oficiais e simulados ilimitados no Modo Prova Real.

## Asaas — cobrança e liberação automática

1. Em **Administração → Configurações** informe o ambiente (sandbox/produção), a **chave de API** (fica criptografada no banco) e um **token do webhook**.
2. No painel do Asaas cadastre o webhook com a URL mostrada na tela (`/webhooks/asaas`), o mesmo token, API v3, fila sequencial e os eventos `PAYMENT_CONFIRMED`, `PAYMENT_RECEIVED`, `PAYMENT_OVERDUE`, `PAYMENT_REFUNDED`, `PAYMENT_CHARGEBACK_REQUESTED`, `PAYMENT_DELETED`.
3. O aluno assina em **Minha assinatura** (PIX, cartão recorrente ou boleto). A assinatura fica `PENDING` até o webhook confirmar; então vira `ACTIVE`, o período é estendido, o aluno é notificado e a indicação do indicador é registrada (a cada 4 validadas, bônus de R$ 40). Estorno/chargeback revoga o acesso e invalida a indicação. Detalhes em [docs/ASAAS.md](docs/ASAAS.md).

## Provas oficiais 2019–2024 (importação automática)

O repositório traz o manifesto `storage/inep/manifest.json` com os gabaritos e o mapa questão → página de **12 provas** (ENEM 2019 a 2024, 1º e 2º dias, aplicação regular impressa, Caderno 1 Azul / Caderno 5 Amarelo), extraídos dos PDFs oficiais do Inep. Os PDFs (≈76 MB) não ficam no Git — baixe-os direto do Inep e importe:

```bash
pip install pypdf
python tools/inep_fetch.py storage/inep                              # baixa 24 PDFs de download.inep.gov.br
python tools/inep_extract.py storage/inep storage/inep/manifest.json # (opcional) regera o manifesto a partir dos PDFs
php artisan enem:import --publish                                    # importa, valida e publica pelo guardião
```

O comando calcula os checksums, cria as questões (1–5 em inglês **e** espanhol), registra o gabarito oficial (anuladas incluídas) e percorre o fluxo `Importado → Validação automática → Revisão 1 (revisor) → Revisão 2 (admin) → Publicado`, reverificando o PDF e o gabarito antes de publicar. Nenhuma prova fica liberada sem assinatura, a menos que você passe `--free-sample=2024` (marca a edição como amostra acessível a quem ainda não assinou). Sem `--publish`, as provas ficam em `IMPORTADO` aguardando a revisão humana no painel.

> As propostas de redação do 1º dia são importadas do próprio caderno oficial: o tema (entre aspas na proposta) e as regras de nota zero (itens 4.x das instruções) são extraídos do PDF, e os textos motivadores são lidos na página da proposta, exibida inalterada no editor. Nenhum texto é transcrito à mão, exceto o tema de 2023 (kerning quebrado no PDF), transcrito da mesma página.

## Importando outra prova manualmente

1. Baixe o caderno e o gabarito oficiais no site do Inep.
2. Em **Administração → Conteúdo oficial**: cadastre a prova (ano, aplicação, dia, **duração oficial daquela edição**, URL e versão), adicione o caderno (PDF — checksum calculado na importação), registre o gabarito (`número;área;letra;idioma;página`) e a proposta de redação (transcrição fiel). Cadastre as regras de nota zero da edição com a URL da fonte.
3. **Validar agora** → avance `Importado → Validação automática → Revisão humana 1 → Revisão humana 2 → Publicado`. As duas revisões exigem pessoas diferentes; publicar exige ADMIN e reverifica o checksum do PDF e do gabarito.

## Simulados, Redação e Guia ENEM

- **Simulados** (`/simulados`): simulado por área (só as questões de uma área de uma prova oficial, Modo Estudo), prova completa (Modo Prova Real), Maratona ENEM (1º + 2º dia por edição) e a rotina semanal do **Modo Intensivo** (exclusiva do Plano Intensivo). Tudo com questões oficiais.
- **Redação** (`/redacao`): propostas oficiais 2019–2024, contador de correções do mês, melhor nota e média, explicação das cinco competências e da estrutura do texto.
- **Guia ENEM** (`/guia`): 89 temas por eixo e disciplina (`StudyTopicsSeeder`), cada um com descrição e roteiro "o que estudar"; busca; ao abrir um tema o aluno marca como estudado (barra de progresso) e **cola links de vídeos de estudo** (YouTube/Vimeo incorporados; outros sites como link). Os vídeos são pessoais; a equipe pode marcar um vídeo como recomendado para todos.

## Durante a prova: caderno e cartão-resposta

À esquerda o caderno oficial (PDF, com navegação por página e atalho `p.N` em cada questão). À direita, duas abas:

- **Caderno** — o aluno marca a alternativa escolhida em cada questão enquanto resolve (como circular no papel). É rascunho: fica salvo, aparece no relatório, mas **não é corrigido**.
- **Cartão-resposta** — única coisa considerada na correção. O botão **Transferir para o cartão** copia as marcações do caderno que ainda não foram transferidas; ao encerrar, o sistema avisa quantas marcações ficaram só no caderno.

## Testes

```bash
php artisan test
```

27 testes / 400+ asserções: guardião (fonte oficial, checksum do gabarito, revisores distintos, alteração versionada que despublica a prova), cronômetro (sem pausa na Prova Real, expiração, retomada no modo estudo), correção (idioma, anuladas, em branco, por área/disciplina, nunca "nota ENEM"), fluxo completo de prova via HTTP, marcações do caderno salvas sem entrar na correção, redação (rascunho sem IA, limite de linhas, A/B, zero por regra da edição, limite do plano), checkout no Asaas + webhook (token, idempotência, ativação, indicação efetivada, estorno), meta de indicações (bônus de R$ 40 a cada 4 validadas, desistência no prazo, estorno cancela bônus não pago, saque e pagamento), configurações criptografadas, cupons, antifraude, plano de estudos e repetição espaçada.

## Avisos institucionais

Esta plataforma é independente e não possui vínculo, patrocínio ou afiliação com o Instituto Nacional de Estudos e Pesquisas Educacionais Anísio Teixeira — Inep — ou com o Ministério da Educação. Resultados são educacionais; estimativas de nota não equivalem ao resultado oficial (o Inep utiliza a TRI).
