<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Bônus do indicador: gerado a cada N indicações validadas (ReferralConversion). */
class Commission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'conversions_count' => 'integer',
        ];
    }

    public function affiliate()
    {
        return $this->belongsTo(User::class, 'affiliate_id');
    }

    /** Indicações validadas que compõem este bônus. */
    public function conversions()
    {
        return $this->hasMany(ReferralConversion::class);
    }

    public function withdrawal()
    {
        return $this->belongsTo(Withdrawal::class);
    }
}
