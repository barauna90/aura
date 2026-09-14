<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'recurring' => 'boolean',
            'first_payment_only' => 'boolean',
            'payout_methods' => 'array',
        ];
    }

    public static function current(): self
    {
        // fresh(): após o create os defaults do banco (model, value, ...) não estão no objeto.
        return static::query()->first() ?? static::query()->create(['payout_methods' => ['PIX']])->fresh();
    }
}
