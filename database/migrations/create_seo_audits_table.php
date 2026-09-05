<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('seo.audit.audits_table', 'seo_audits'), function (Blueprint $table): void {
            $table->id();

            $table->foreignId('batch_id');

            // Same shape as seo_meta's own polymorphic pair — one record can
            // be looked up across both tables the same way.
            $table->string('seoable_type', 160);
            $table->string('seoable_id', 64);
            $table->string('locale', 10)->nullable();

            $table->unsignedTinyInteger('score');

            // The check ids that failed or warned on this run, not the full
            // report — the report's messages are translation keys resolved
            // for display, not something worth freezing into a history row.
            $table->json('failed_checks')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['batch_id'], 'seo_audits_batch_index');
            $table->index(['seoable_type', 'seoable_id'], 'seo_audits_seoable_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seo.audit.audits_table', 'seo_audits'));
    }
};
