<?php

namespace App\Models;

use App\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'address',
        'date_of_birth',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function patientAssignments(): HasMany
    {
        return $this->hasMany(PatientAssignment::class, 'member_id');
    }

    public function careTeamMembers(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'patient_assignments',
            'patient_id',
            'member_id',
        )->withPivot(['role', 'assigned_by_id'])
            ->withTimestamps();
    }

    public function assignedPatients(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'patient_assignments',
            'member_id',
            'patient_id',
        )->withPivot(['role', 'assigned_by_id'])
            ->withTimestamps();
    }

    public function medicines(): HasMany
    {
        return $this->hasMany(Medicine::class, 'patient_id');
    }

    public function prescribedTherapyPlans(): HasMany
    {
        return $this->hasMany(TherapyPlan::class, 'doctor_id');
    }

    public function therapyPlans(): HasMany
    {
        return $this->hasMany(TherapyPlan::class, 'patient_id');
    }

    public function dispensers(): HasMany
    {
        return $this->hasMany(Dispenser::class, 'patient_id');
    }

    public function sensorLogs(): HasMany
    {
        return $this->hasMany(SensorLog::class, 'patient_id');
    }

    public function doseLogs(): HasMany
    {
        return $this->hasMany(DoseLog::class, 'patient_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'patient_id');
    }

    public function appointmentsAsPatient(): HasMany
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

    public function appointmentsAsDoctor(): HasMany
    {
        return $this->hasMany(Appointment::class, 'doctor_id');
    }

    public function scopePatients(Builder $query): Builder
    {
        return $query->where('role', UserRole::Patient->value);
    }

    public function scopeDoctors(Builder $query): Builder
    {
        return $query->where('role', UserRole::Doctor->value);
    }

    public function scopeCaregivers(Builder $query): Builder
    {
        return $query->where('role', UserRole::Caregiver->value);
    }

    public function hasRole(UserRole|string ...$roles): bool
    {
        $roleValues = array_map(
            static fn (UserRole|string $role): string => $role instanceof UserRole ? $role->value : $role,
            $roles,
        );

        return in_array($this->role?->value ?? '', $roleValues, true);
    }

    public function canManageClinicalData(): bool
    {
        return $this->hasRole(UserRole::Admin, UserRole::Doctor);
    }
}
