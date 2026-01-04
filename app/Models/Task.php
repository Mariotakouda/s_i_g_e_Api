<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'status',
        'due_date',
        'task_file',
        'report_file',
        'employee_id',
        'creator_id', // ✅ Aligné sur votre migration
    ];

    // Relation vers l'employé assigné
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // Relation vers le créateur (Admin ou Manager)
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}