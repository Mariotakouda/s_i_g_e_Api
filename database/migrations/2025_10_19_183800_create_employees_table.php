<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // Informations minimales
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();

            // Infos RH (remplies plus tard par un admin)
            $table->string('contract_type')->nullable();
            $table->date('hire_date')->nullable();
            $table->float('salary_base')->nullable();

            // Relation avec department (optionnel au début)
            $table->foreignId('department_id')->nullable()->constrained()->onDelete('cascade');

            // Lien avec users
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade')->unique();


            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
