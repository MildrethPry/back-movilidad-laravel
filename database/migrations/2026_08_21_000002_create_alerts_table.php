<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type', 40)->index();
            $table->string('severity', 20)->index();
            $table->string('title');
            $table->text('message');
            $table->string('route')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->timestamps();
        });

        Schema::create('alert_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained('alerts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();
            $table->unique(['alert_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_reads');
        Schema::dropIfExists('alerts');
    }
};
