<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Indicação efetivada: o indicado pagou (webhook) e passa pelo prazo de validação. */
class ReferralConversion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'available_at' => 'datetime',
            'fraud_flags' => 'array',
        ];
    }

    public function affiliate()
    {
        return $this->belongsTo(User::class, 'affiliate_id');
    }

    public function referredUser()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function commission()
    {
        return $this->belongsTo(Commission::class);
    }
}
