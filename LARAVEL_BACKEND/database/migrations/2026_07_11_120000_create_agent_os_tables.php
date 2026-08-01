<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'agent_commerce_enabled')) {
                $table->boolean('agent_commerce_enabled')->default(false)->after('learn_from_conversations');
            }
        });

        Schema::create('customer_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('customer_phone', 30);
            $table->string('memory_key', 120);
            $table->text('memory_value');
            $table->string('category', 40)->default('preference');
            $table->decimal('confidence', 3, 2)->default(0.80);
            $table->string('source', 40)->default('agent');
            $table->timestamps();

            $table->unique(['company_id', 'customer_phone', 'memory_key'], 'customer_memories_unique');
            $table->index(['company_id', 'customer_phone']);
        });

        Schema::create('agent_tool_invocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tool_name', 80);
            $table->json('arguments')->nullable();
            $table->json('result')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->boolean('success')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
            $table->index(['chat_id', 'created_at']);
        });

        Schema::create('agent_reflections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reflection_type', 40);
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'reflection_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_reflections');
        Schema::dropIfExists('agent_tool_invocations');
        Schema::dropIfExists('customer_memories');

        Schema::table('company_settings', function (Blueprint $table) {
            if (Schema::hasColumn('company_settings', 'agent_commerce_enabled')) {
                $table->dropColumn('agent_commerce_enabled');
            }
        });
    }
};
