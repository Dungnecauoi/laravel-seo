<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('seo.internal_links.table', 'seo_internal_links'), function (Blueprint $table): void {
            $table->id();

            // The page the link was found on.
            $table->string('source_type', 160);
            $table->string('source_id', 64);

            // The page it points to, made absolute at crawl time so it can be
            // compared against Seoable::seoUrl() regardless of whether the
            // content used a relative or absolute href.
            $table->text('target_url');
            $table->char('target_hash', 32);
            $table->text('anchor_text')->nullable();

            $table->timestamp('created_at')->nullable();

            // Every crawl of one source deletes its old rows and inserts the
            // current set, rather than trying to diff and update in place —
            // simpler, and correct even when a link's anchor text changed.
            $table->index(['source_type', 'source_id'], 'seo_internal_links_source_index');
            $table->index('target_hash', 'seo_internal_links_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seo.internal_links.table', 'seo_internal_links'));
    }
};
