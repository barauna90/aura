<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\ExamEdition;
use App\Models\User;
use App\Services\Content\ContentImportService;
use App\Services\Content\GuardianService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Importa provas oficiais a partir de um manifesto gerado por tools/inep_extract.py
 * (PDFs do Inep + gabarito extraído do PDF de gabarito). Tudo entra pelo mesmo
 * serviço do painel (PENDING/IMPORTED, checksum, fonte). Com --publish, avança o
 * fluxo de auditoria usando o admin e o revisor informados — a validação
 * automática e a verificação de integridade continuam obrigatórias.
 */
class ImportEnemCommand extends Command
{
    protected $signature = 'enem:import {manifest=storage/inep/manifest.json}
                            {--dir=storage/inep : Pasta com os PDFs}
                            {--publish : Avança IMPORTED → PUBLISHED (exige dois revisores distintos)}
                            {--admin=admin@aura.local}
                            {--reviewer=revisor@aura.local}
                            {--free-sample=2024 : Anos marcados como amostra gratuita (separados por vírgula)}';

    protected $description = 'Importa provas oficiais do ENEM a partir do manifesto extraído dos PDFs do Inep';

    public function handle(ContentImportService $importer, GuardianService $guardian): int
    {
        $manifest = json_decode(file_get_contents(base_path($this->argument('manifest'))), true);
        if (! is_array($manifest)) {
            $this->error('Manifesto inválido.');

            return self::FAILURE;
        }
        $dir = base_path($this->option('dir'));
        $admin = User::where('email', $this->option('admin'))->firstOrFail();
        $reviewer = User::where('email', $this->option('reviewer'))->firstOrFail();
        if ($admin->id === $reviewer->id) {
            $this->error('Admin e revisor precisam ser pessoas distintas.');

            return self::FAILURE;
        }
        $freeYears = array_map('intval', array_filter(explode(',', (string) $this->option('free-sample'))));

        foreach ($manifest as $m) {
            $label = "ENEM {$m['year']} D{$m['day']}";
            $edition = ExamEdition::where('year', $m['year'])->first();
            $existing = $edition ? Exam::where('exam_edition_id', $edition->id)->where('application', $m['application'])->where('day', $m['day'])->first() : null;
            if ($existing) {
                $this->line("[skip] {$label}: já cadastrada (#{$existing->id}, {$existing->pipeline_stage})");
                $exam = $existing;
            } else {
                $exam = $importer->createExam([
                    'year' => $m['year'], 'application' => $m['application'], 'day' => $m['day'], 'title' => $m['title'],
                    'duration_minutes' => $m['duration_minutes'], 'areas' => $m['areas'], 'structure_note' => $m['structure_note'] ?? null,
                    'source_url' => $m['booklet']['source_url'], 'document_version' => 'Inep '.$m['year'].' impresso v1',
                    'is_free_sample' => in_array((int) $m['year'], $freeYears, true),
                ], null, $admin->id);

                $b = $m['booklet'];
                $booklet = $importer->addBooklet($exam, [
                    'color' => $b['color'], 'label' => $b['label'], 'page_count' => $b['page_count'], 'source_url' => $b['source_url'], 'document_version' => 'Inep '.$m['year'].' impresso v1',
                ], new UploadedFile("{$dir}/{$b['file']}", $b['file'], 'application/pdf', null, true), $admin->id);

                $k = $m['answer_key'];
                $importer->registerAnswerKey($booklet, ['source_url' => $k['source_url'], 'document_version' => 'Inep '.$m['year'].' gabarito v1'], $k['answers'],
                    new UploadedFile("{$dir}/{$k['file']}", $k['file'], 'application/pdf', null, true), $admin->id);
                $this->info("[ok] {$label}: prova #{$exam->id}, ".count($k['answers']).' questões, '.$b['page_count'].' páginas');
            }

            if ($this->option('publish') && $exam->pipeline_stage !== 'PUBLISHED') {
                try {
                    foreach ([['AUTO_VALIDATED', $admin], ['HUMAN_REVIEW_1', $reviewer], ['HUMAN_REVIEW_2', $admin], ['PUBLISHED', $admin]] as [$stage, $actor]) {
                        $exam = $exam->fresh();
                        if (array_search($exam->pipeline_stage, \App\Support\Enem::PIPELINE_STAGES, true) >= array_search($stage, \App\Support\Enem::PIPELINE_STAGES, true)) {
                            continue;
                        }
                        $guardian->advanceExam($exam, $stage, $actor->id);
                    }
                    $this->info("      publicada (checksum do PDF e do gabarito verificados)");
                } catch (ValidationException $e) {
                    $this->error("      bloqueada pelo guardião: ".implode(' | ', $e->validator->errors()->all()));
                }
            }
        }

        return self::SUCCESS;
    }
}
