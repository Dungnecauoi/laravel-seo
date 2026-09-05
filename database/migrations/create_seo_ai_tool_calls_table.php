<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every Write/Destructive AI tool call, propose and apply alike — separate
 * from `seo_ai_log`, which is LLM token/cost accounting and has nothing to
 * do with whether a tool call actually mutated anything. Read tools are not
 * logged here: they run immediately and at whatever frequency a caller
 * polls a dashboard, which this table is not sized for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('seo.ai.tools.table', 'seo_ai_tool_calls'), function (Blueprint $table): void {
            $table->id();

            $table->string('tool', 100);
            $table->string('risk_tier', 20);
            $table->string('status', 20);
            $table->string('proposal_id', 36)->nullable();

            $table->json('input');
            $table->json('output')->nullable();

            $table->string('scope', 191)->nullable();
            $table->json('actor')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->timestamp('applied_at')->nullable();

            $table->index(['tool', 'created_at'], 'seo_ai_tool_calls_tool_index');
            $table->index('proposal_id', 'seo_ai_tool_calls_proposal_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seo.ai.tools.table', 'seo_ai_tool_calls'));
    }
};
