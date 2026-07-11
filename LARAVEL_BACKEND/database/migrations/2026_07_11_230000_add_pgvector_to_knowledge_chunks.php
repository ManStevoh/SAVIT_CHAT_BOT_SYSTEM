<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        } catch (\Throwable) {
            // Extension may require superuser — column add still allows future enablement
        }

        if (! Schema::hasColumn('knowledge_chunks', 'embedding_vector')) {
            DB::statement('ALTER TABLE knowledge_chunks ADD COLUMN embedding_vector vector(1536) NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS knowledge_chunks_embedding_vector_idx ON knowledge_chunks USING ivfflat (embedding_vector vector_cosine_ops) WITH (lists = 100)');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (Schema::hasColumn('knowledge_chunks', 'embedding_vector')) {
            Schema::table('knowledge_chunks', function (Blueprint $table) {
                $table->dropColumn('embedding_vector');
            });
        }
    }
};
