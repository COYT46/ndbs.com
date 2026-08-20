<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_logs', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });

        Schema::table('vehicle_logs', function (Blueprint $table) {
            $table->index('code');
        });

        DB::statement("
            UPDATE vehicle_logs vl
            INNER JOIN monthly_tickets mt ON mt.id = vl.monthly_ticket_id
            SET vl.code = mt.code
            WHERE vl.ticket_type = 'monthly'
              AND vl.monthly_ticket_id IS NOT NULL
              AND mt.code IS NOT NULL
              AND mt.code <> ''
        ");
    }

    public function down(): void
    {
        Schema::table('vehicle_logs', function (Blueprint $table) {
            $table->dropIndex(['code']);
        });

        Schema::table('vehicle_logs', function (Blueprint $table) {
            $table->unique('code');
        });
    }
};
