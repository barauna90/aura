# Aura Simulados — Preparação intensiva para o ENEM

Plataforma SaaS (PHP 8.3 · Laravel 13 · MySQL 8) para estudantes que não podem pagar um cursinho: provas oficiais anteriores exibidas **exatamente como o Inep publicou**, cronômetro real, cartão-resposta digital, correção pelo gabarito oficial, redação avaliada pelas cinco competências (dois avaliadores independentes + terceiro em divergência), plano de estudos adaptativo, caderno de erros, assinatura mensal via **Asaas** com liberação automática de acesso, programa de indicação com comissões e bolsas.

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
php artisan migrate --seed      # planos, admin, revisor, tópicos do guia — NÃO cria provas
npm install && npm run build    # gera public/build
php artisan serve               # http://localhost:8000
```

Em produção: `php artisan queue:work` (fila `database` para a correção de redação) e `php artisan schedule:run` no cron a cada minuto (encerramento de provas expiradas, expiração de assinaturas, liberação de comissões). Aponte o document root para `public/`.

Credenciais do seed (troque em produção): `admin@aura.local / Admin123!Troque` e `revisor@aura.local / Revisor123!Troque`.

## Asaas — cobrança e liberação automática

1. Em **Administração → Configurações** informe o ambiente (sandbox/produção), a **chave de API** (fica criptografada no banco) e um **token do webhook**.
2. No painel do Asaas cadastre o webhook com a URL mostrada na tela (`/webhooks/asaas`), o mesmo token, API v3, fila sequencial e os eventos `PAYMENT_CONFIRMED`, `PAYMENT_RECEIVED`, `PAYMENT_OVERDUE`, `PAYMENT_REFUNDED`, `PAYMENT_CHARGEBACK_REQUESTED`, `PAYMENT_DELETED`.
3. O aluno assina em **Minha assinatura** (PIX, cartão recorrente ou boleto). A assinatura fica `PENDING` até o webhook confirmar; então vira `ACTIVE`, o período é estendido, o aluno é notificado e a comissão do indicador é gerada. Estorno/chargeback revoga o acesso e cancela comissões. Detalhes em [docs/ASAAS.md](docs/ASAAS.md).

## Importando uma prova oficial

1. Baixe o caderno e o gabarito oficiais no site do Inep.
2. Em **Administração → Conteúdo oficial**: cadastre a prova (ano, aplicação, dia, **duração oficial daquela edição**, URL e versão), adicione o caderno (PDF — checksum calculado na importação), registre o gabarito (`número;área;letra;idioma;página`) e a proposta de redação (transcrição fiel). Cadastre as regras de nota zero da edição com a URL da fonte.
3. **Validar agora** → avance `Importado → Validação automática → Revisão humana 1 → Revisão humana 2 → Publicado`. As duas revisões exigem pessoas diferentes; publicar exige ADMIN e reverifica o checksum do PDF e do gabarito.

## Testes

```bash
php artisan test
```

20 testes / 180+ asserções: guardião (fonte oficial, checksum do gabarito, revisores distintos, alteração versionada que despublica a prova), cronômetro (sem pausa na Prova Real, expiração, retomada no modo estudo), correção (idioma, anuladas, em branco, por área/disciplina, nunca "nota ENEM"), fluxo completo de prova via HTTP, redação (rascunho sem IA, limite de linhas, A/B, zero por regra da edição, limite do plano), checkout no Asaas + webhook (token, idempotência, ativação, comissão, estorno), configurações criptografadas, cupons, comissões/antifraude, plano de estudos e repetição espaçada.

## Avisos institucionais

Esta plataforma é independente e não possui vínculo, patrocínio ou afiliação com o Instituto Nacional de Estudos e Pesquisas Educacionais Anísio Teixeira — Inep — ou com o Ministério da Educação. Resultados são educacionais; estimativas de nota não equivalem ao resultado oficial (o Inep utiliza a TRI).
