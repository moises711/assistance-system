<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('department_manager', function (Blueprint $table) {
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departaments')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['manager_id', 'department_id']);
        });

        DB::table('users')
            ->where('role', 'gerente')
            ->whereNotNull('department_id')
            ->orderBy('id')
            ->get(['id', 'department_id'])
            ->each(fn (object $manager) => DB::table('department_manager')->insertOrIgnore([
                'manager_id' => $manager->id,
                'department_id' => $manager->department_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_manager');
    }
};
