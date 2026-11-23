<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->text('description')->nullable()->change();
            $table->foreignId('attendance_id')->nullable()->unique()->constrained('attendances')->restrictOnDelete();
        });
        DB::table('attendances')->whereNotNull('check_out_at')->whereNotNull('daily_activity')
            ->orderBy('id')->chunkById(200, function ($records): void {
                foreach ($records as $record) {
                    $description = trim($record->daily_activity);
                    if ($description === '') {
                        continue;
                    }
                    DB::table('activities')->insert([
                        'attendance_id' => $record->id, 'user_id' => $record->user_id,
                        'type' => 'work', 'name' => 'Actividad de la jornada '.$record->date,
                        'description' => $description, 'occurred_at' => $record->check_out_at,
                        'metadata' => json_encode(['source' => 'attendance', 'method' => $record->check_out_method]),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->dropUnique(['attendance_id']);
            $table->dropConstrainedForeignId('attendance_id');
        });
    }
};
