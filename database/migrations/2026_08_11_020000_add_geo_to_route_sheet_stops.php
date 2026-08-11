<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_sheet_stops', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('odometer_km');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('route_sheet_stops', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
