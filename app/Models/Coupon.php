<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function plans()
    {
        return $this->belongsToMany(Plan::class);
    }

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }
}
