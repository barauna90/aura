<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'benefits' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function coupons()
    {
        return $this->belongsToMany(Coupon::class);
    }
}
