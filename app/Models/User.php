<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLES = ['STUDENT', 'REVIEWER', 'ADMIN', 'SUPER_ADMIN'];

    protected $fillable = [
        'name', 'email', 'password', 'role', 'referral_code', 'referred_by_id', 'cpf_hash', 'phone',
        'asaas_customer_id', 'goal', 'target_exam_date', 'weekly_hours', 'main_difficulty', 'onboarding_done',
        'font_scale', 'theme', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token', 'cpf_hash'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'target_exam_date' => 'date',
            'onboarding_done' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function referredBy()
    {
        return $this->belongsTo(User::class, 'referred_by_id');
    }

    public function referrals()
    {
        return $this->hasMany(User::class, 'referred_by_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function examSessions()
    {
        return $this->hasMany(ExamSession::class);
    }

    public function essays()
    {
        return $this->hasMany(Essay::class);
    }

    public function studyPlans()
    {
        return $this->hasMany(StudyPlan::class);
    }

    public function errorNotebook()
    {
        return $this->hasMany(ErrorNotebookEntry::class);
    }

    public function goals()
    {
        return $this->hasMany(Goal::class);
    }

    public function scholarships()
    {
        return $this->hasMany(Scholarship::class);
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class, 'affiliate_id');
    }

    public function consents()
    {
        return $this->hasMany(Consent::class);
    }

    public function appNotifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function roleLevel(): int
    {
        return array_search($this->role, self::ROLES, true) ?: 0;
    }

    public function hasRole(string $minimum): bool
    {
        return $this->roleLevel() >= (array_search($minimum, self::ROLES, true) ?: 0);
    }

    public function isStaff(): bool
    {
        return $this->hasRole('REVIEWER');
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0] ?: 'estudante';
    }
}
