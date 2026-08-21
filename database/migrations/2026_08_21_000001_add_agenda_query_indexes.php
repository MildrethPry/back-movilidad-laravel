<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobilization_requests', function (Blueprint $table) {
            $table->index('departure_date');
            $table->index(['status', 'departure_date']);
        });

        Schema::table('route_sheets', function (Blueprint $table) {
            $table->index('trip_status');
            $table->index('driver_response');
        });
    }

    public function down(): void
    {
        Schema::table('route_sheets', function (Blueprint $table) {
            $table->dropIndex(['driver_response']);
            $table->dropIndex(['trip_status']);
        });

        Schema::table('mobilization_requests', function (Blueprint $table) {
            $table->dropIndex(['status', 'departure_date']);
            $table->dropIndex(['departure_date']);
        });
    }
};
