<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
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

    public function withdrawal()
    {
        return $this->belongsTo(Withdrawal::class);
    }
}
