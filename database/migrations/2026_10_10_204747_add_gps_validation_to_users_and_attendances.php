<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('work_site_id')->nullable()->constrained('work_sites')->nullOnDelete();
        });
        Schema::table('attendances', function (Blueprint $table): void {
            $table->json('check_in_location')->nullable();
            $table->json('check_out_location')->nullable();
        });
        foreach (['ViewAny:WorkSite', 'View:WorkSite', 'Create:WorkSite', 'Update:WorkSite', 'Delete:WorkSite', 'AssignWorkSite:User', 'Correct:Attendance'] as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('attendances', fn (Blueprint $table) => $table->dropColumn(['check_in_location', 'check_out_location']));
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('work_site_id'));
    }
};
