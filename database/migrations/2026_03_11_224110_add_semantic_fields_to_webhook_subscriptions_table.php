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
        Schema::table('webhook_subscriptions', function (Blueprint $table) {
            $table->text('semantic_query')->nullable()->after('events');
            // We use vector type directly because blueprint doesn't support pgvector natively out of the box in older versions, but actually Laravel 11 handles it.
            $table->vector('embedding', 1536)->nullable()->after('semantic_query');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhook_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['semantic_query', 'embedding']);
        });
    }
};
