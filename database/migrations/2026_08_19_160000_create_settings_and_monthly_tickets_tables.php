<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value')->nullable();
            $table->timestamps();
        });

        DB::table('settings')->insert([
            [
                'key' => 'daily_price_per_hour',
                'value' => '1000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'monthly_price_per_month',
                'value' => '100000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Schema::create('monthly_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 6)->unique();
            $table->string('plate_number');
            $table->unsignedTinyInteger('duration_months')->default(1);
            $table->unsignedInteger('price')->default(0);
            $table->date('starts_on');
            $table->date('expires_on');
            $table->boolean('is_active')->default(true);
            $table->boolean('deleted')->default(false);
            $table->timestamps();
        });

        Schema::table('vehicle_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicle_logs', 'ticket_type')) {
                $table->string('ticket_type', 16)->default('daily')->after('code');
            }
            if (!Schema::hasColumn('vehicle_logs', 'monthly_ticket_id')) {
                $table->unsignedBigInteger('monthly_ticket_id')->nullable()->after('ticket_type');
            }
            if (!Schema::hasColumn('vehicle_logs', 'fee')) {
                $table->unsignedInteger('fee')->nullable()->after('is_valid');
            }
            if (!Schema::hasColumn('vehicle_logs', 'hourly_rate')) {
                $table->unsignedInteger('hourly_rate')->nullable()->after('fee');
            }
            if (!Schema::hasColumn('vehicle_logs', 'monthly_match')) {
                $table->boolean('monthly_match')->nullable()->after('hourly_rate');
            }
            if (!Schema::hasColumn('vehicle_logs', 'monthly_confirmed')) {
                $table->boolean('monthly_confirmed')->nullable()->after('monthly_match');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_logs', function (Blueprint $table) {
            foreach (['ticket_type', 'monthly_ticket_id', 'fee', 'hourly_rate', 'monthly_match', 'monthly_confirmed'] as $col) {
                if (Schema::hasColumn('vehicle_logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::dropIfExists('monthly_tickets');
        Schema::dropIfExists('settings');
    }
};
