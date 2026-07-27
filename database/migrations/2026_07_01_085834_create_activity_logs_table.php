<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action'); // login, profile_updated, account_created...
            // model + model_id — a polymorphic relationship without Eloquent polymorphism.
            // e.g. model = “Account”, model_id = 5 — means that the action was performed on account #5.
            // Its just simplier
            // Should check also morphTo()
            $table->string('model')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('ip', 45)->nullable(); // IPv6 max
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
