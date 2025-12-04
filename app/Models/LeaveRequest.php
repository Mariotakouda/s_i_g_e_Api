<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use HasFactory;

    /**
     * Les attributs qui peuvent être massivement assignés.
     * C'est la cause probable de votre erreur 500 si ces champs manquaient.
     */
    protected $fillable = [
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'status',
        'message',
    ];

    /**
     * Une demande appartient à un employé.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}