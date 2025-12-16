<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            
            // Champs principaux
            $table->string('title');
            $table->text('message');
            
            // Cibles de l'annonce (mutuellement exclusives)
            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('employees')
                ->onDelete('cascade');
                
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->onDelete('cascade');
            
            // Flag pour annonces générales
            $table->boolean('is_general')->default(false);
            
            // Créateur de l'annonce
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};