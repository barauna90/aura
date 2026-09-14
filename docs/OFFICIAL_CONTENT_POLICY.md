# Política de conteúdo oficial (ENEM OFFICIAL CONTENT GUARDIAN)

Este documento é a referência normativa da seção 1 do projeto: **o sistema não inventa** questões, alternativas, gabaritos, textos motivadores, temas de redação, durações, notas oficiais, parâmetros da TRI nem regras atribuídas ao Inep.

## O que é conteúdo oficial

Somente registros com `sourceType = OFFICIAL_INEP` provenientes de cadernos, gabaritos e documentos publicados pelo Inep/MEC. Cada `ContentSource` guarda:

| Campo | Significado |
|---|---|
| `SOURCE_TYPE` | `OFFICIAL_INEP` (obrigatório para provas) · `EDITORIAL` · `AI_GENERATED_EDUCATIONAL` |
| `SOURCE_URL` | URL do documento no site do Inep |
| `SOURCE_YEAR` / `DOCUMENT_VERSION` | Edição e versão do documento |
| `IMPORT_DATE` / `LAST_VALIDATION` | Datas de importação e última validação |
| `CHECKSUM` | SHA-256 do PDF original (ou do gabarito canônico) |
| `REVIEW_STATUS` | `PENDING` → `VALIDATING` → `VERIFIED` / `REJECTED` |

Cada questão está ligada a: edição, ano, aplicação, dia, área, caderno, cor, número original, alternativas (A–E), gabarito oficial (ou anulação), origem, versão e status.

## Como o guardião impede erros

Implementado em `apps/api/src/modules/content/guardian.rules.ts` (regras puras, testadas) e `guardian.service.ts` (persistência).

1. **Exibição fiel** — o aluno vê o **PDF oficial inalterado** (tela dividida: caderno à esquerda, cartão-resposta à direita). Nada de OCR, IA, reconstrução ou reformatação no conteúdo da prova. Opções de acessibilidade agem só na interface.
2. **Filtro de visibilidade no banco** — o catálogo (`OFFICIAL_VISIBLE_WHERE`) só retorna `reviewStatus = VERIFIED` **e** `pipelineStage = PUBLISHED` **e** fonte `OFFICIAL_INEP` verificada.
3. **Validação estrutural** (`validateExamForPublication`) — fonte oficial com URL e checksum, duração cadastrada por edição (nunca universal), caderno com páginas e questões, gabarito verificado, cada questão com resposta ou anulação oficial, nenhuma questão `AI_GENERATED` dentro de prova oficial.
4. **Verificação de integridade** (`verifyIntegrity`) — recomputa o SHA-256 do PDF em storage e compara com o registrado; recomputa o checksum canônico do gabarito (`número:letra|…`) e exige que coincida com um `OfficialAnswerSet` importado. Qualquer divergência bloqueia a publicação e gera `SystemAlert` (regra de ouro de QA, seção 65).
5. **Fluxo de auditoria** — `IMPORTED → AUTO_VALIDATED → HUMAN_REVIEW_1 → HUMAN_REVIEW_2 → PUBLISHED`, sem pular etapas; as revisões humanas devem ser de pessoas distintas; publicar exige perfil ADMIN.
6. **Nada muda em silêncio** — alterar gabarito ou metadados exige motivo, gera `ContentVersion` (versão anterior + nova + autor + data), grava `AuditLog` e devolve a prova a `PENDING/IMPORTED`, retirando-a do catálogo até nova auditoria.
7. **IA sem escrita** — nenhum serviço de IA possui referência aos repositórios de `Question`, `OfficialAnswer`, `Exam` ou `EssayPrompt`. A IA só lê resultados, resoluções `VERIFIED` e a base RAG de documentos oficiais.

## Correção e notas

- A correção compara **somente o cartão-resposta** com o gabarito oficial; anuladas não contam.
- Apresentamos **acertos oficiais pelo gabarito**. **Nunca** calculamos `acertos × valor = nota ENEM`. Se um dia houver estimativa pedagógica, ela carrega o aviso `DISCLAIMERS.SCORE_ESTIMATE` e nunca é chamada de nota oficial.
- A redação recebe `Nota estimada da correção simulada` com o aviso `DISCLAIMERS.ESSAY_EVALUATION`. As regras de nota zero são cadastradas **por edição** com URL da fonte e só valem se `VERIFIED`.

## Conteúdo não oficial

- Classificações pedagógicas, resoluções e materiais do guia são `EDITORIAL` e só aparecem quando `VERIFIED`. Sem resolução validada, a interface mostra exatamente: "Resolução detalhada ainda não disponível."
- Simulados autorais ou gerados por IA vivem na seção separada **Simulados de Treinamento** (`AI_GENERATED_EDUCATIONAL`) e nunca são apresentados como questões do ENEM.
- Sobre regras do ENEM, a IA consulta primeiro a base de documentos oficiais; sem resultado, responde exatamente: "Nenhuma informação oficial validada foi encontrada na base."
- O guia usa "conteúdo recorrente nas provas analisadas" e "habilidade importante na Matriz de Referência" — nunca "vai cair".
