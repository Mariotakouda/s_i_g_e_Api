<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $fillable = [
        'title', 
        'message', 
        'employee_id',
        'department_id',
        'is_general',
        'user_id' // ⬅️ Doit être présent
    ];

    protected $casts = [
        'is_general' => 'boolean',
    ];

    /**
     * Relation avec l'employé destinataire (si annonce personnelle)
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relation avec le département destinataire (si annonce départementale)
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
    
    /**
     * Relation avec l'utilisateur qui a créé l'annonce (Admin/RH)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}