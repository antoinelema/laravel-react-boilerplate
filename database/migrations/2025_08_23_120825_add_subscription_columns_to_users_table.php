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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('subscription_type', ['free', 'premium'])->default('free');
            $table->timestamp('subscription_expires_at')->nullable();
            $table->integer('daily_searches_count')->default(0);
            $table->timestamp('daily_searches_reset_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_type',
                'subscription_expires_at', 
                'daily_searches_count',
                'daily_searches_reset_at'
            ]);
        });
    }
};
