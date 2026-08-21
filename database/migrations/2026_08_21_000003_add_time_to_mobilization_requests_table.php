<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobilization_requests', function (Blueprint $table) {
            $table->time('departure_time')->nullable()->after('departure_date');
            $table->time('return_time')->nullable()->after('return_date');
        });
    }

    public function down(): void
    {
        Schema::table('mobilization_requests', function (Blueprint $table) {
            $table->dropColumn(['departure_time', 'return_time']);
        });
    }
};
