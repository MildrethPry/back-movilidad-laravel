<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rate_configurations', function (Blueprint $table): void {
            $table->string('rate_label', 120)->nullable();
            $table->string('rate_group', 30)->default('other');
        });

        $metadata = [
            'viatico_diario' => ['Viático diario general', 'allowance'],
            'alojamiento_diario' => ['Alojamiento fuera de sede', 'allowance'],
            'alimentacion_diaria' => ['Alimentación diaria', 'allowance'],
            'extra_50' => ['Hora suplementaria (50%)', 'allowance'],
            'extra_100' => ['Hora extraordinaria (100%)', 'allowance'],
            'precio_diesel' => ['Combustible diésel', 'fuel'],
            'precio_extra' => ['Combustible extra', 'fuel'],
            'precio_super' => ['Combustible súper', 'fuel'],
        ];

        foreach ($metadata as $key => [$label, $group]) {
            DB::table('rate_configurations')
                ->where('rate_key', $key)
                ->update(['rate_label' => $label, 'rate_group' => $group]);
        }
    }

    public function down(): void
    {
        Schema::table('rate_configurations', function (Blueprint $table): void {
            $table->dropColumn(['rate_label', 'rate_group']);
        });
    }
};
