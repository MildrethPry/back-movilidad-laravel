<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobilization_requests', function (Blueprint $table) {
            $table->string('destination_address', 255)->nullable()->after('destination');
            $table->decimal('destination_latitude', 10, 7)->nullable()->after('destination_address');
            $table->decimal('destination_longitude', 10, 7)->nullable()->after('destination_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('mobilization_requests', function (Blueprint $table) {
            $table->dropColumn([
                'destination_address',
                'destination_latitude',
                'destination_longitude',
            ]);
        });
    }
};
