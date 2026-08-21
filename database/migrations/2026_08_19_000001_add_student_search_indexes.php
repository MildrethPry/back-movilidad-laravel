<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('first_name');
            $table->index('last_name');
            $table->index(['first_name', 'last_name']);
        });

        Schema::table('role_user', function (Blueprint $table) {
            $table->index(['role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_first_name_index');
            $table->dropIndex('users_last_name_index');
            $table->dropIndex('users_first_name_last_name_index');
        });

        Schema::table('role_user', function (Blueprint $table) {
            $table->dropIndex('role_user_role_id_user_id_index');
        });
    }
};
