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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            
            // Relaciones principales
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departaments')->nullOnDelete();

            // Fechas y Marcas de Tiempo
            $table->date('date');
            $table->string('Activity_description')->nullable();
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('lunch_start_at')->nullable();
            $table->dateTime('lunch_end_at')->nullable();
            $table->dateTime('check_out_at')->nullable();

            // Métricas y Cálculos (En minutos)
            $table->integer('total_minutes')->default(0);
            $table->integer('late_minutes')->default(0);
            $table->integer('early_leave_minutes')->default(0);
            $table->integer('overtime_minutes')->default(0);
            $table->integer('missing_minutes')->default(0);

            // Metadatos y Auditoría
            $table->string('status')->default('presente'); // presente, tarde, ausente
            $table->string('check_in_method')->nullable(); // web, qr, manual
            $table->string('check_out_method')->nullable();
            $table->text('daily_activity')->nullable();

            $table->timestamps();

            // Evita registros duplicados de un usuario el mismo día
            $table->unique(['user_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};