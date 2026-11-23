<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('attendances', 'Activity_description')) {
            DB::table('attendances')->where(function ($query): void {
                $query->whereNull('daily_activity')->orWhere('daily_activity', '');
            })->update(['daily_activity' => DB::raw(DB::connection()->getQueryGrammar()->wrap('Activity_description'))]);
        }
        foreach (['present' => 'presente', 'pending' => 'pendiente', 'late' => 'tarde', 'absent' => 'ausente'] as $old => $new) {
            DB::table('attendances')->where('status', $old)->update(['status' => $new]);
        }
    }

    public function down(): void
    {
        /** Data is preserved because the original columns remain available. */
    }
};
