<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'contract_type',
        'hire_date',
        'salary_base',
        'department_id',
    ];

    // ===== RELATIONS =====

    /**
     * L'utilisateur lié à cet employé
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Département de l'employé
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Les tâches de l'employé
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Les présences de l'employé
     */
    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    /**
     * Les demandes de congés de l'employé
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Les rôles de l'employé
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'employee_role');
    }

    /**
     * Les annonces de l'employé
     */
    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }
}
