<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'needs_password_change',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'needs_password_change' => 'boolean',
    ];

    /**
     * Relation vers l'employé associé
     */
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Vérifie si l'utilisateur est admin (insensible à la casse)
     */
    public function isAdmin(): bool
    {
        return strtolower($this->role) === 'admin';
    }

    /**
     * Vérifie si l'utilisateur est manager (plusieurs sources possibles)
     */
    public function isManager(): bool
    {
        // 1. Vérification du rôle direct (insensible à la casse)
        if (in_array(strtolower($this->role), ['admin', 'manager'])) {
            return true;
        }

        // 2. Vérification via la table pivot roles
        if ($this->employee) {
            $hasManagerRole = $this->employee->roles()
                ->whereRaw('LOWER(name) = ?', ['manager'])
                ->exists();

            if ($hasManagerRole) {
                return true;
            }

            // 3. Vérification dans la table managers
            $existsInManagersTable = \App\Models\Manager::where('employee_id', $this->employee->id)->exists();
            
            return $existsInManagersTable;
        }

        return false;
    }
}