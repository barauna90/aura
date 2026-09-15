# Política de conteúdo oficial (ENEM OFFICIAL CONTENT GUARDIAN)

Referência normativa da seção 1 do projeto: **o sistema não inventa** questões, alternativas, gabaritos, textos motivadores, temas, durações, notas oficiais, parâmetros da TRI nem regras atribuídas ao Inep.

## Proveniência obrigatória

Somente registros com `source_type = OFFICIAL_INEP` provenientes de cadernos, gabaritos e documentos publicados pelo Inep/MEC. Cada `content_sources` guarda `source_type`, `source_url`, `source_year`, `document_version`, `import_date`, `last_validation`, `checksum` (SHA-256 do documento) e `review_status` (`PENDING → VALIDATING → VERIFIED | REJECTED`).

Cada questão está ligada a edição, ano, aplicação, dia, área, caderno, cor, número original, alternativas A–E, gabarito oficial (ou anulação), origem, versão e status.

## Como o guardião impede erros

Implementado em `app/Services/Content/GuardianRules.php` (regras puras, testadas) e `GuardianService.php`.

1. **Exibição fiel** — o aluno vê o **PDF oficial inalterado** (`/cadernos/{id}/pdf`, só cadernos verificados de provas publicadas) em tela dividida: caderno à esquerda, cartão-resposta à direita. Sem OCR, IA ou reconstrução. Acessibilidade age só na interface.
2. **Filtro no banco** — `Exam::officialVisible()` exige `review_status = VERIFIED`, `pipeline_stage = PUBLISHED` e fonte `OFFICIAL_INEP` verificada. Catálogo, início de sessão, PDF e propostas de redação usam o mesmo escopo.
3. **Validação estrutural** (`validateExamForPublication`) — fonte oficial com URL e checksum, duração cadastrada por edição, caderno com páginas e questões, gabarito importado, cada questão com resposta ou anulação, nenhuma questão não oficial.
4. **Verificação de integridade** (`verifyIntegrity`) — recomputa o SHA-256 do PDF no disco `official` e compara com o registrado; recomputa o checksum canônico do gabarito (`número:letra|…`) e exige coincidência com um `official_answer_sets` importado. Divergência bloqueia a publicação e gera `system_alerts` (regra de ouro de QA).
5. **Fluxo de auditoria** — `IMPORTED → AUTO_VALIDATED → HUMAN_REVIEW_1 → HUMAN_REVIEW_2 → PUBLISHED`, sem pular etapas; revisões humanas por pessoas distintas (checado no `audit_logs`); publicar exige `ADMIN`.
6. **Nada muda em silêncio** — alterar gabarito ou metadados exige motivo, grava `content_versions` (anterior/novo/autor/data), `audit_logs`, e devolve a prova a `PENDING/IMPORTED` — ela some do catálogo até nova auditoria (teste `test_changing_an_official_answer_requires_reason_and_unpublishes_the_exam`).
7. **IA sem escrita** — `AiService`/providers não recebem modelos de conteúdo; só leem resultados, resoluções `VERIFIED` e a base de documentos oficiais.

## Importação automática (2019–2024)

`tools/inep_fetch.py` baixa os PDFs somente de `download.inep.gov.br`; `tools/inep_extract.py` lê o gabarito **do próprio PDF oficial** (letras, anuladas e notas de rodapé "Questão N anulada") e mapeia questão → página pelo texto do caderno; `php artisan enem:import` grava tudo com checksum e passa pelo mesmo fluxo de auditoria (com `--publish`, revisões atribuídas ao revisor e ao admin do seed — em produção prefira revisar no painel). Áreas por faixa de numeração e durações (5h30/5h) seguem a estrutura oficial das edições de dois dias; nada é inferido de outra fonte. Propostas de redação não são importadas automaticamente.

## Correção e notas

- Só o **cartão-resposta** (`answers.option`) é comparado ao gabarito oficial; anuladas não contam; língua estrangeira filtrada pela escolha do aluno. A marcação feita no **caderno** (`answers.draft_option`) é rascunho: salva e exibida no relatório, nunca corrigida (teste `test_booklet_marks_are_saved_but_only_the_answer_sheet_is_graded`).
- Exibimos **acertos oficiais pelo gabarito**. Nunca `acertos × valor = nota ENEM`. Qualquer estimativa leva `Disclaimers::SCORE_ESTIMATE`.
- Redação: `Nota estimada da correção simulada` + `Disclaimers::ESSAY_EVALUATION`. Regras de nota zero cadastradas **por edição** com URL da fonte; só valem se `VERIFIED`; sem regras cadastradas, nenhuma é aplicada.

## Conteúdo não oficial

- Classificações, resoluções e materiais do guia são `EDITORIAL` e só aparecem quando `VERIFIED`; sem resolução validada, a interface mostra "Resolução detalhada ainda não disponível."
- Simulados autorais/IA vivem em **Simulados de Treinamento** (rota separada) e nunca são apresentados como questões do ENEM.
- Sobre regras do ENEM, o Professor IA consulta a base oficial; sem resultado responde "Nenhuma informação oficial validada foi encontrada na base."
- O guia usa "conteúdo recorrente nas provas analisadas" e "habilidade importante na Matriz de Referência" — nunca "vai cair".
