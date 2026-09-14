# Roadmap e estado atual

Legenda: ✅ implementado · 🟡 parcial (modelo/contrato pronto, falta integração) · ⬜ pendente

## Fase 1 — MVP

| Item | Status | Onde |
|---|---|---|
| Cadastro / login / refresh / logout | ✅ | `auth/*`, `/entrar`, `/cadastro` |
| Assinatura (planos configuráveis, checkout, cancelamento, status por webhook) | ✅ (provider `mock`) | `subscriptions/*`, `payments/*`, `/assinatura` |
| Gateway brasileiro real (PIX, cartão recorrente, boleto) | 🟡 interface pronta | `payments/payment-provider.interface.ts` |
| Dashboard do aluno + "Comece por aqui" | ✅ | `/dashboard` |
| Provas oficiais (catálogo com filtros, proveniência, aviso de estrutura) | ✅ | `/provas`, `content/*` |
| Exibição do PDF oficial em tela dividida | ✅ | `PdfViewer`, `/sessao/[id]` |
| Cronômetro do servidor, sem pausa no Modo Prova Real, encerramento automático | ✅ | `exam-engine/timer.rules.ts` + cron |
| Cartão-resposta com autosave e resiliência | ✅ | `AnswerSheet`, `saveAnswers` |
| Correção pelo gabarito oficial, idioma, anuladas, por área/disciplina | ✅ | `grading.rules.ts` |
| Histórico, resultado, relatório de questões, comparação | ✅ | `/desempenho`, `/sessao/[id]/resultado`, `/desempenho/comparar` |
| Redação (proposta, rascunho, folha sem IA, envio pós-prova) | ✅ | `/redacao/[id]` |
| Correção A/B (+C), validador de zero por edição, auditor de consistência | ✅ | `essays/*` |
| Painel admin (import, auditoria em 5 etapas, versões, dashboard) | ✅ | `/admin/*` |
| Onboarding (objetivo, data, horas, dificuldade) | ✅ | `/onboarding` |
| MFA opcional | 🟡 modelado | `User.mfaEnabled` |

## Fase 2

| Item | Status |
|---|---|
| Plano de estudos (regular e intensivo), tarefas, reagendamento, calendário | ✅ |
| Caderno de erros com repetição espaçada | ✅ |
| Analytics (mapa por área, 7/30/90 dias/tudo, média móvel, questões mais demoradas, tópicos fracos) | ✅ |
| Guia ENEM por eixo (tópicos editoriais verificados; recorrência derivada de classificações) | ✅ (conteúdo a preencher pelo CMS) |
| Professor IA (pós-prova, com base oficial; bloqueado na Prova Real) | ✅ |
| Classificação pedagógica e resoluções editoriais (CMS) | 🟡 modelo pronto; falta tela de edição |
| Base RAG com documentos oficiais (ingestão de PDF em chunks) | 🟡 busca pronta; falta ingestão |

## Fase 3

| Item | Status |
|---|---|
| Programa de indicação (link `/r/CODIGO`, cliques, cadastros, comissões, saque) | ✅ |
| Comissionamento configurável e antifraude | ✅ |
| Cupons e promoções | ✅ |
| Bolsas e patrocinadores com vagas | ✅ |
| Notificações in-app | ✅ · e-mail/push 🟡 (consentimento pronto, transporte pendente) |

## Fase 4

App mobile ⬜ · gamificação discreta (modelo `Achievement` pronto) 🟡 · patrocínios avançados ⬜ · simulados de treinamento em seção separada (rota criada, vazia) 🟡.

## Testes obrigatórios (seção 64)

Cobertos por unidade: cronômetro, encerramento, cartão-resposta/gabarito, idioma, webhook assinado, cupom, comissão, fraude, redação/pontuação, plano de estudos. Pendentes: testes e2e de pagamento/renovação/cancelamento com banco, autosave no navegador, responsividade e acessibilidade automatizadas (axe/Playwright).
