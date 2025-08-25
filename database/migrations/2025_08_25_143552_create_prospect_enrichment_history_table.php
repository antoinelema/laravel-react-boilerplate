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
        Schema::create('prospect_enrichment_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prospect_id');
            $table->string('enrichment_type', 50)->default('web'); // 'web', 'manual', 'import'
            $table->enum('status', ['started', 'completed', 'failed'])->default('started');
            $table->json('contacts_found')->nullable();
            $table->integer('execution_time_ms')->nullable();
            $table->json('services_used')->nullable(); // ['duckduckgo', 'google_search']
            $table->text('error_message')->nullable();
            $table->enum('triggered_by', ['user', 'auto', 'bulk'])->default('user');
            $table->unsignedBigInteger('triggered_by_user_id')->nullable();
            $table->timestamps();

            // Clés étrangères
            $table->foreign('prospect_id')->references('id')->on('prospects')->onDelete('cascade');
            $table->foreign('triggered_by_user_id')->references('id')->on('users')->onDelete('set null');

            // Index pour optimiser les requêtes
            $table->index(['prospect_id', 'status'], 'idx_prospect_status');
            $table->index(['status', 'created_at'], 'idx_status_created');
            $table->index('created_at', 'idx_created_at');
            $table->index('triggered_by', 'idx_triggered_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospect_enrichment_history');
    }
};
