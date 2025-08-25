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
        Schema::create('prospect_category_prospect', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained()->onDelete('cascade');
            $table->foreignId('prospect_category_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['prospect_id', 'prospect_category_id'], 'prospect_category_unique');
            $table->index('prospect_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospect_category_prospect');
    }
};
