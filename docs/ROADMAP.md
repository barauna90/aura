# Roadmap e estado atual

Legenda: ✅ implementado · 🟡 parcial · ⬜ pendente

## Fase 1 — MVP

| Item | Status |
|---|---|
| Cadastro, login, recuperação de senha, logout | ✅ |
| Assinatura via Asaas (PIX, cartão recorrente, boleto) com liberação automática por webhook | ✅ |
| Chave de API e token do webhook configuráveis no painel (criptografados) | ✅ |
| Dashboard do aluno + "Comece por aqui" | ✅ |
| Catálogo de provas oficiais com filtros e proveniência | ✅ |
| PDF oficial em tela dividida, cronômetro do servidor, cartão-resposta com autosave | ✅ |
| Correção pelo gabarito oficial; histórico; comparação | ✅ |
| Redação (proposta, rascunho, folha) e correção A/B/C com validador de zero por edição | ✅ (provider real requer chave Anthropic) |
| Painel admin: importação, auditoria em 5 etapas, versões, planos, cupons, indicações, bolsas, usuários, configurações | ✅ |
| Onboarding | ✅ |
| E-mail de recuperação de senha | 🟡 requer `MAIL_*` configurado |
| MFA opcional | ⬜ |

## Fase 2

| Item | Status |
|---|---|
| Plano de estudos regular/intensivo, tarefas, reagendamento, metas | ✅ |
| Caderno de erros com repetição espaçada | ✅ |
| Analytics (mapa por área, períodos, média móvel, tópicos fracos) | ✅ |
| Guia ENEM por eixo (tópicos editoriais verificados; recorrência derivada de classificações) | ✅ conteúdo a preencher |
| Professor IA (pós-prova; bloqueado na Prova Real) | ✅ |
| Tela de CMS para classificações/resoluções editoriais | ⬜ (modelo pronto) |
| Ingestão de documentos oficiais na base RAG | ⬜ (busca pronta) |
| Calendário visual | 🟡 lista por dia no plano |

## Fase 3

| Item | Status |
|---|---|
| Programa de indicação (`/r/CODIGO`, cliques, cadastros, comissões, saque) | ✅ |
| Comissionamento configurável e antifraude | ✅ |
| Cupons e promoções | ✅ |
| Bolsas e patrocinadores com vagas | ✅ |
| Notificações in-app | ✅ · e-mail/push ⬜ |

## Fase 4

App mobile ⬜ · gamificação discreta (modelo pronto) 🟡 · simulados de treinamento (rota separada, vazia) 🟡 · páginas do PDF como imagem (fallback) ⬜.

## Testes (seção 64)

Cobertos: cronômetro, encerramento, cartão-resposta, gabarito, idioma, pagamento/webhook/renovação/estorno, cupom, comissão, fraude, redação e pontuação, guardião/checksum, RBAC. Pendentes: testes de navegador (autosave offline, responsividade, acessibilidade automatizada).
