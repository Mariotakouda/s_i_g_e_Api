<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table departments.
     */
    public function up(): void
    {
         Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('managers')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Supprime la table departments.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
