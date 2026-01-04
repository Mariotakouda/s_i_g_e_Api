<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade'); // L'ADMIN/MANAGER
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->date('due_date')->nullable();
            $table->string('task_file')->nullable();    // PDF de l'Admin/Manager
            $table->string('report_file')->nullable();  // PDF de l'Employé
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
