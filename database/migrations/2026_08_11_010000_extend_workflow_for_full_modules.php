<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobilization_requests', function (Blueprint $table) {
            $table->foreignId('secretaria_approver_id')->nullable()->after('rectorate_approver_id')
                ->constrained('users')->nullOnDelete();
            $table->text('secretaria_observation')->nullable()->after('secretaria_approver_id');
            $table->timestamp('confirmation_deadline')->nullable()->after('secretaria_observation');
        });

        Schema::create('request_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('mobilization_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('action', 80);
            $table->text('observation')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('route_sheets', function (Blueprint $table) {
            $table->string('driver_response', 20)->default('pendiente')->after('trip_status');
            $table->text('driver_reject_reason')->nullable()->after('driver_response');
            $table->timestamp('driver_responded_at')->nullable()->after('driver_reject_reason');
        });

        Schema::table('passenger_manifests', function (Blueprint $table) {
            $table->string('invitation_status', 20)->default('invitado')->after('user_id');
            $table->text('reject_reason')->nullable()->after('invitation_status');
            $table->timestamp('responded_at')->nullable()->after('reject_reason');
        });

        Schema::table('service_stations', function (Blueprint $table) {
            $table->decimal('price_per_liter', 8, 3)->nullable()->after('address');
            $table->decimal('monthly_quota_liters', 10, 2)->nullable()->after('price_per_liter');
            $table->decimal('consumed_liters', 10, 2)->default(0)->after('monthly_quota_liters');
            $table->date('contract_start')->nullable()->after('consumed_liters');
            $table->date('contract_end')->nullable()->after('contract_start');
        });

        Schema::table('route_sheet_stops', function (Blueprint $table) {
            $table->unsignedInteger('sequence')->default(1)->after('route_sheet_id');
            $table->string('location', 150)->nullable()->after('sequence');
            $table->timestamp('departure_at')->nullable()->after('location');
            $table->unsignedInteger('odometer_km')->nullable()->after('arrival_time');
            $table->text('notes')->nullable()->after('odometer_km');
        });

        // Datos previos: internas pendientes pasan a bandeja de secretaría
        DB::table('mobilization_requests')
            ->where('status', 'pendiente')
            ->update(['status' => 'pendiente_secretaria']);
    }

    public function down(): void
    {
        Schema::table('route_sheet_stops', function (Blueprint $table) {
            $table->dropColumn(['sequence', 'location', 'departure_at', 'odometer_km', 'notes']);
        });

        Schema::table('service_stations', function (Blueprint $table) {
            $table->dropColumn([
                'price_per_liter',
                'monthly_quota_liters',
                'consumed_liters',
                'contract_start',
                'contract_end',
            ]);
        });

        Schema::table('passenger_manifests', function (Blueprint $table) {
            $table->dropColumn(['invitation_status', 'reject_reason', 'responded_at']);
        });

        Schema::table('route_sheets', function (Blueprint $table) {
            $table->dropColumn(['driver_response', 'driver_reject_reason', 'driver_responded_at']);
        });

        Schema::dropIfExists('request_status_histories');

        Schema::table('mobilization_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('secretaria_approver_id');
            $table->dropColumn(['secretaria_observation', 'confirmation_deadline']);
        });
    }
};
