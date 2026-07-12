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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });

        Schema::table('vehicle_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicle_logs', 'entry_image')) {
                $table->string('entry_image')->nullable();
            }
            if (!Schema::hasColumn('vehicle_logs', 'exit_image')) {
                $table->string('exit_image')->nullable();
            }
            if (!Schema::hasColumn('vehicle_logs', 'exit_plate_number')) {
                $table->string('exit_plate_number')->nullable();
            }
            if (!Schema::hasColumn('vehicle_logs', 'guard_in_id')) {
                $table->unsignedBigInteger('guard_in_id')->nullable();
            }
            if (!Schema::hasColumn('vehicle_logs', 'guard_out_id')) {
                $table->unsignedBigInteger('guard_out_id')->nullable();
            }
            if (!Schema::hasColumn('vehicle_logs', 'is_valid')) {
                $table->boolean('is_valid')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active']);
        });

        Schema::table('vehicle_logs', function (Blueprint $table) {
            $table->dropColumn([
                'entry_image',
                'exit_image',
                'exit_plate_number',
                'guard_in_id',
                'guard_out_id',
                'is_valid'
            ]);
        });
    }
};
