<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enable pgvector extension
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('memories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->string('key')->nullable();
            $table->text('value');
            $table->jsonb('metadata')->default('{}');
            $table->enum('visibility', ['private', 'shared', 'public'])->default('private');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Composite index for fast per-agent key lookup
            $table->unique(['agent_id', 'key']);
            $table->index(['agent_id', 'visibility']);
            $table->index('visibility'); // for commons search
        });

        // Add the vector column separately (pgvector syntax)
        // 1536 dimensions = OpenAI text-embedding-3-small
        DB::statement('ALTER TABLE memories ADD COLUMN embedding vector(1536)');

        // IVFFlat index for approximate nearest-neighbor search
        // Lists = sqrt(expected_row_count), tune later
        DB::statement('CREATE INDEX memories_embedding_idx ON memories USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};
