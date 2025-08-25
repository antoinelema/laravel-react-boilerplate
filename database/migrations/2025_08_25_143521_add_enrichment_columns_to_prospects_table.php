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
        Schema::table('prospects', function (Blueprint $table) {
            // Colonnes d'enrichissement
            $table->timestamp('last_enrichment_at')->nullable()->after('updated_at');
            $table->integer('enrichment_attempts')->default(0)->after('last_enrichment_at');
            $table->enum('enrichment_status', ['never', 'pending', 'completed', 'failed', 'skipped'])
                  ->default('never')->after('enrichment_attempts');
            $table->float('enrichment_score', 5, 2)->default(0)->after('enrichment_status');
            $table->boolean('auto_enrich_enabled')->default(true)->after('enrichment_score');
            $table->timestamp('enrichment_blacklisted_at')->nullable()->after('auto_enrich_enabled');
            $table->json('enrichment_data')->nullable()->after('enrichment_blacklisted_at');
            $table->float('data_completeness_score', 5, 2)->default(0)->after('enrichment_data');
            
            // Index pour optimiser les requêtes d'éligibilité
            $table->index(['enrichment_status', 'last_enrichment_at'], 'idx_enrichment_eligibility');
            $table->index(['auto_enrich_enabled', 'enrichment_blacklisted_at'], 'idx_enrichment_filters');
            $table->index('data_completeness_score', 'idx_completeness_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            // Supprimer les index d'abord
            $table->dropIndex('idx_enrichment_eligibility');
            $table->dropIndex('idx_enrichment_filters');
            $table->dropIndex('idx_completeness_score');
            
            // Supprimer les colonnes
            $table->dropColumn([
                'last_enrichment_at',
                'enrichment_attempts',
                'enrichment_status',
                'enrichment_score',
                'auto_enrich_enabled',
                'enrichment_blacklisted_at',
                'enrichment_data',
                'data_completeness_score'
            ]);
        });
    }
};
