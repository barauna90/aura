<?php

namespace App\Services;

use App\Models\Essay;
use App\Models\ExamSession;
use App\Models\OfficialDocumentChunk;
use App\Models\User;
use App\Services\Ai\AiService;
use App\Services\Billing\AccessService;
use App\Support\Disclaimers;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * PROFESSOR ENEM IA + BASE OFICIAL (RAG lexical). Contexto = resultado do aluno,
 * resoluções VERIFIED e trechos de documentos oficiais. Bloqueado na Prova Real.
 */
class TutorService
{
    public function __construct(private readonly AiService $ai, private readonly AccessService $access) {}

    /** Busca lexical em trechos de documentos oficiais VERIFIED. */
    public function searchOfficial(string $query, int $limit = 4): array
    {
        $terms = array_values(array_filter(preg_split('/\W+/u', mb_strtolower($query)), fn ($t) => mb_strlen($t) > 3));
        if (! $terms) {
            return ['found' => false, 'message' => Disclaimers::NO_OFFICIAL_INFO, 'chunks' => []];
        }
        $chunks = OfficialDocumentChunk::with('document.source')
            ->whereHas('document', fn ($q) => $q->where('review_status', 'VERIFIED'))
            ->where(function ($q) use ($terms) {
                foreach ($terms as $t) {
                    $q->orWhere('content', 'like', "%{$t}%");
                }
            })->limit(50)->get()
            ->map(fn ($c) => ['c' => $c, 'score' => count(array_filter($terms, fn ($t) => str_contains(mb_strtolower($c->content), $t)))])
            ->sortByDesc('score')->take($limit);
        if ($chunks->isEmpty()) {
            return ['found' => false, 'message' => Disclaimers::NO_OFFICIAL_INFO, 'chunks' => []];
        }

        return ['found' => true, 'chunks' => $chunks->map(fn ($x) => ['content' => $x['c']->content, 'title' => $x['c']->document->title, 'year' => $x['c']->document->year])->values()->all()];
    }

    public function ask(User $user, string $question, ?ExamSession $session, ?Essay $essay): array
    {
        $this->access->assertFeature($user, 'tutor');
        if (ExamSession::where('user_id', $user->id)->where('mode', 'PROVA_REAL')->where('status', 'IN_PROGRESS')->exists()) {
            throw new AuthorizationException('O Professor IA não está disponível durante o Modo Prova Real.');
        }
        $context = [];
        if ($session && $session->user_id === $user->id && $session->isFinished() && $session->result) {
            $context[] = "RESULTADO DA PROVA: {$session->result->correct}/{$session->result->total_questions} acertos. Por área: ".json_encode($session->result->by_area, JSON_UNESCAPED_UNICODE);
            $wrong = $session->answerSheet->answers()->with(['question.officialAnswer', 'question.classification.topic', 'question.resolution'])->where('is_correct', false)->limit(15)->get()
                ->map(function ($a) {
                    $q = $a->question;
                    $res = $q->resolution?->review_status === 'VERIFIED' ? $q->resolution->body : Disclaimers::NO_RESOLUTION;
                    $topic = $q->classification?->review_status === 'VERIFIED' ? ($q->classification->topic?->name ?? 'sem tópico') : 'assunto não classificado';

                    return "Q{$q->original_number} ({$q->area}, {$topic}): marcou {$a->option}, gabarito {$q->officialAnswer?->correct}. Resolução: {$res}";
                });
            if ($wrong->isNotEmpty()) {
                $context[] = "QUESTÕES ERRADAS:\n".$wrong->implode("\n");
            }
        }
        if ($essay && $essay->user_id === $user->id && $essay->finalResult) {
            $context[] = "REDAÇÃO (nota simulada {$essay->finalResult->total}): ".json_encode($essay->finalResult->competency_scores).'. Melhorias: '.$essay->evaluations->flatMap->improvements->implode('; ');
        }
        $kb = $this->searchOfficial($question);
        if ($kb['found']) {
            $context[] = "BASE OFICIAL:\n".implode("\n", array_map(fn ($c) => "[{$c['title']} {$c['year']}] {$c['content']}", $kb['chunks']));
        }
        $noInfo = Disclaimers::NO_OFFICIAL_INFO;
        $out = $this->ai->complete('TUTOR', "Você é o Professor ENEM IA, um tutor educacional. Regras:
- Use somente o CONTEXTO fornecido (resultados do aluno, resoluções validadas, base oficial).
- Se a pergunta for sobre regras do ENEM e a base oficial não trouxer a resposta, diga exatamente: \"{$noInfo}\"
- Nunca invente questões, gabaritos, notas oficiais ou regras. Nunca afirme que algo \"vai cair\".
- Não converta acertos em nota do ENEM. Se perguntarem, explique que o Inep usa a TRI.
- Seja direto, em português do Brasil, com passos práticos de estudo.",
            "CONTEXTO:\n".(implode("\n\n", $context) ?: '(sem contexto adicional)')."\n\nPERGUNTA DO ALUNO: {$question}", null, 4000);

        return ['answer' => $out['text'], 'used_official_base' => $kb['found']];
    }
}
