<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('seo.search_console.inspections_table', 'seo_url_inspections'), function (Blueprint $table): void {
            $table->id();

            $table->text('url');

            // Same reasoning as seo_search_console_stats and
            // seo_pagespeed_stats: uniqueness and lookups run against a
            // hash, since a URL outgrows every index key limit MySQL allows
            // on a text column directly.
            $table->char('url_hash', 32);
            $table->date('date');

            $table->string('verdict', 16)->nullable();
            $table->string('coverage_state', 64)->nullable();
            $table->string('robots_txt_state', 32)->nullable();
            $table->string('indexing_state', 32)->nullable();
            $table->string('page_fetch_state', 32)->nullable();
            $table->text('google_canonical')->nullable();
            $table->text('user_canonical')->nullable();
            $table->string('mobile_usability_verdict', 16)->nullable();
            $table->json('mobile_usability_issues')->nullable();
            $table->string('rich_results_verdict', 16)->nullable();

            $table->timestamps();

            // A day's inspection replaces that day's row when re-run, same
            // as the performance sync — not accumulated into a second row.
            $table->unique(['url_hash', 'date'], 'seo_url_inspections_unique');
            $table->index('date', 'seo_url_inspections_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seo.search_console.inspections_table', 'seo_url_inspections'));
    }
};
