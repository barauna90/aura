<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Scholarship;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** PROGRAMA DE BOLSAS + EMPRESAS PATROCINADORAS (vagas controladas automaticamente). */
class ScholarshipService
{
    public function __construct(private readonly AuditService $audit) {}

    public function grant(User $user, ?int $days, ?Sponsor $sponsor, ?string $reason, int $actorId): Scholarship
    {
        return DB::transaction(function () use ($user, $days, $sponsor, $reason, $actorId) {
            if ($sponsor) {
                $sponsor = Sponsor::lockForUpdate()->find($sponsor->id);
                if (! $sponsor->active || $sponsor->used_seats >= $sponsor->seats) {
                    throw ValidationException::withMessages(['sponsor' => 'Todas as vagas patrocinadas foram preenchidas.']);
                }
                $sponsor->increment('used_seats');
            }
            $s = Scholarship::create([
                'user_id' => $user->id, 'sponsor_id' => $sponsor?->id, 'days' => $days,
                'ends_at' => $days ? now()->addDays($days) : null, 'granted_by' => $actorId, 'reason' => $reason,
            ]);
            $this->audit->log('scholarship.granted', $actorId, 'Scholarship', $s->id, ['user_id' => $user->id, 'days' => $days]);
            Notification::create([
                'user_id' => $user->id, 'kind' => 'SCHOLARSHIP', 'title' => 'Bolsa de estudos concedida',
                'body' => $days ? "Você recebeu uma bolsa de {$days} dias." : 'Você recebeu acesso integral gratuito à plataforma.', 'sent_at' => now(),
            ]);

            return $s;
        });
    }

    public function revoke(Scholarship $s, int $actorId): void
    {
        $s->update(['active' => false]);
        $this->audit->log('scholarship.revoked', $actorId, 'Scholarship', $s->id);
    }
}
