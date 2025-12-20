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
     * Vérifie si l'utilisateur est admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
