<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('seo.audit.audits_table', 'seo_audits'), function (Blueprint $table): void {
            // Joined in from whatever seo_pagespeed_stats / seo_url_inspections
            // / seo_external_links+seo_link_checks already had stored for this
            // record's URL at audit time — never a live API call of its own,
            // since seo:audit runs over every record of a model and both
            // Google APIs involved are rate-limited far below what a batch
            // that size would need. Null means "no data available yet", not
            // "checked and fine" — seo:pagespeed / seo:search-console:inspect
            // / seo:broken-links were never run for this URL, or this
            // particular record has no external links to begin with (both
            // read the same as "no rows found" in the source tables).
            $table->unsignedTinyInteger('pagespeed_score')->nullable()->after('score');
            $table->string('gsc_verdict', 16)->nullable()->after('pagespeed_score');
            $table->unsignedInteger('broken_links_count')->nullable()->after('gsc_verdict');
        });
    }

    public function down(): void
    {
        Schema::table(config('seo.audit.audits_table', 'seo_audits'), function (Blueprint $table): void {
            $table->dropColumn(['pagespeed_score', 'gsc_verdict', 'broken_links_count']);
        });
    }
};
