<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'confirmed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function referralConversion()
    {
        return $this->hasOne(ReferralConversion::class);
    }
}
