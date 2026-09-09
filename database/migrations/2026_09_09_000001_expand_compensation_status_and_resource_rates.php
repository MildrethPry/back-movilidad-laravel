<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_compensations', function (Blueprint $table): void {
            $table->string('payment_status', 50)->change();
        });

        DB::table('rate_configurations')->insertOrIgnore([
            ['rate_key' => 'alojamiento_diario', 'rate_value' => 45.00],
            ['rate_key' => 'alimentacion_diaria', 'rate_value' => 35.00],
        ]);
    }

    public function down(): void
    {
        DB::table('rate_configurations')
            ->whereIn('rate_key', ['alojamiento_diario', 'alimentacion_diaria'])
            ->delete();

        Schema::table('driver_compensations', function (Blueprint $table): void {
            $table->string('payment_status', 20)->change();
        });
    }
};
