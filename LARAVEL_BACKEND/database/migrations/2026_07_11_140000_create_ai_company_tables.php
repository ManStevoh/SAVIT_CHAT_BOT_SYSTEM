<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'digital_twin')) {
                $table->json('digital_twin')->nullable()->after('agent_proactive_enabled');
            }
            if (! Schema::hasColumn('company_settings', 'agent_council_enabled')) {
                $table->boolean('agent_council_enabled')->default(false)->after('digital_twin');
            }
        });

        Schema::table('chats', function (Blueprint $table) {
            if (! Schema::hasColumn('chats', 'detected_sentiment')) {
                $table->string('detected_sentiment', 30)->nullable()->after('detected_language');
            }
        });

        Schema::create('agent_reasoning_traces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_id')->nullable()->constrained()->nullOnDelete();
            $table->text('incoming_message');
            $table->json('trace')->nullable();
            $table->string('chosen_plan', 500)->nullable();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
            $table->index(['chat_id', 'created_at']);
        });

        Schema::create('customer_intent_chains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('customer_phone', 30);
            $table->string('primary_intent', 80)->default('exploring');
            $table->string('stage', 80)->default('discover');
            $table->json('journey')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'customer_phone'], 'customer_intent_chains_unique');
        });

        Schema::create('agent_operating_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 120);
            $table->text('guidance');
            $table->string('source', 40)->default('reflection');
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'topic'], 'agent_operating_guides_unique');
        });

        Schema::create('commerce_briefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('brief_date');
            $table->text('summary');
            $table->json('metrics')->nullable();
            $table->json('recommendations')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'brief_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_briefs');
        Schema::dropIfExists('agent_operating_guides');
        Schema::dropIfExists('customer_intent_chains');
        Schema::dropIfExists('agent_reasoning_traces');

        Schema::table('chats', function (Blueprint $table) {
            if (Schema::hasColumn('chats', 'detected_sentiment')) {
                $table->dropColumn('detected_sentiment');
            }
        });

        Schema::table('company_settings', function (Blueprint $table) {
            if (Schema::hasColumn('company_settings', 'agent_council_enabled')) {
                $table->dropColumn('agent_council_enabled');
            }
            if (Schema::hasColumn('company_settings', 'digital_twin')) {
                $table->dropColumn('digital_twin');
            }
        });
    }
};
