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
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('sector')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('address')->nullable();
            $table->json('contact_info')->nullable(); // emails, phones, website, social_networks
            $table->text('description')->nullable();
            $table->integer('relevance_score')->default(0);
            $table->enum('status', ['new', 'contacted', 'interested', 'qualified', 'converted', 'rejected'])->default('new');
            $table->string('source')->nullable(); // pages_jaunes, google_maps, manual
            $table->string('external_id')->nullable(); // ID from external API
            $table->json('raw_data')->nullable(); // Original data from API
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'sector']);
            $table->index(['user_id', 'city']);
            $table->index('relevance_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
