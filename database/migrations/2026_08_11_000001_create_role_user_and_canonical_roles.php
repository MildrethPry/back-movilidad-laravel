<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'role_id']);
        });

        $canonical = [
            'secretaria' => 'Secretaría / Administrativo — eje central operativo.',
            'conductor' => 'Conductor de vehículos institucionales.',
            'mecanico' => 'Encargado de mantenimiento y patio.',
            'docente' => 'Docente / tutor que solicita movilizaciones.',
            'responsable_facultad' => 'Autoridad de facultad que supervisa solicitudes de su unidad.',
            'vicerrector' => 'Aprueba viajes externos tras autorización de secretaría.',
            'estudiante' => 'Estudiante participante en viajes académicos.',
        ];

        foreach ($canonical as $name => $description) {
            DB::table('roles')->updateOrInsert(
                ['name' => $name],
                ['description' => $description]
            );
        }

        $aliasMap = [
            'jefe_transporte' => 'secretaria',
            'chofer' => 'conductor',
            'solicitante' => 'docente',
            'rector' => 'vicerrector',
            'pasajero' => 'estudiante',
        ];

        foreach ($aliasMap as $legacy => $canonicalName) {
            $legacyRole = DB::table('roles')->where('name', $legacy)->first();
            $canonicalRole = DB::table('roles')->where('name', $canonicalName)->first();
            if (! $legacyRole || ! $canonicalRole) {
                continue;
            }

            DB::table('users')
                ->where('role_id', $legacyRole->id)
                ->update(['role_id' => $canonicalRole->id]);
        }

        $users = DB::table('users')->whereNotNull('role_id')->get(['id', 'role_id']);
        foreach ($users as $user) {
            DB::table('role_user')->updateOrInsert(
                ['user_id' => $user->id, 'role_id' => $user->role_id],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
