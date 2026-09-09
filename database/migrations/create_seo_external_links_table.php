<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('seo.broken_links.external_table', 'seo_external_links'), function (Blueprint $table): void {
            $table->id();

            // The page the link was found on — same shape as
            // seo_internal_links, just the other half of Link::internal.
            $table->string('source_type', 160);
            $table->string('source_id', 64);

            $table->text('target_url');
            $table->char('target_hash', 32);
            $table->text('anchor_text')->nullable();

            $table->timestamp('created_at')->nullable();

            // Every crawl of one source deletes its old rows and inserts the
            // current set, same reasoning as seo_internal_links.
            $table->index(['source_type', 'source_id'], 'seo_external_links_source_index');
            $table->index('target_hash', 'seo_external_links_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seo.broken_links.external_table', 'seo_external_links'));
    }
};
