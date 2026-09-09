<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('seo.pagespeed.table', 'seo_pagespeed_stats'), function (Blueprint $table): void {
            $table->id();

            $table->text('url');

            // Same reasoning as seo_search_console_stats: uniqueness and
            // lookups run against a hash, since a URL outgrows every index
            // key limit MySQL allows on a text column directly.
            $table->char('url_hash', 32);

            $table->string('strategy', 16);
            $table->date('date');

            $table->unsignedTinyInteger('performance_score')->nullable();
            $table->unsignedInteger('lcp_ms')->nullable();
            $table->decimal('cls_score', 5, 3)->nullable();
            $table->unsignedInteger('tbt_ms')->nullable();
            $table->unsignedInteger('fcp_ms')->nullable();
            $table->unsignedInteger('speed_index_ms')->nullable();

            // Field data (real Chrome UX Report visitors) is a separate
            // thing from the lab metrics above, and only present once a URL
            // has enough traffic — a null cwv_category means "not enough
            // data yet", not "failing".
            $table->boolean('field_data_available')->default(false);
            $table->string('cwv_category', 16)->nullable();

            $table->timestamps();

            // A day's run replaces that day's row when re-run, same as
            // Search Console's own sync — not accumulated into a second row.
            $table->unique(['url_hash', 'strategy', 'date'], 'seo_pagespeed_stats_unique');
            $table->index('date', 'seo_pagespeed_stats_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seo.pagespeed.table', 'seo_pagespeed_stats'));
    }
};
