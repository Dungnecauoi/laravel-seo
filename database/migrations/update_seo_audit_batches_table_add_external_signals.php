<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('seo.audit.batches_table', 'seo_audit_batches'), function (Blueprint $table): void {
            // Aggregated from the same per-record signals seo_audits gained
            // alongside this — average_pagespeed_score is null, not 0, when
            // no audited record in the batch had any stored PageSpeed data
            // yet, the same reasoning average_score is already nullable for
            // an empty batch. records_not_indexed / records_with_broken_links
            // only ever count records that *had* GSC / broken-link data to
            // judge — a record with none contributes to neither the good nor
            // the bad count, since "unknown" is not the same claim as "fine".
            $table->decimal('average_pagespeed_score', 5, 2)->nullable()->after('max_score');
            $table->unsignedInteger('records_not_indexed')->nullable()->after('average_pagespeed_score');
            $table->unsignedInteger('records_with_broken_links')->nullable()->after('records_not_indexed');
        });
    }

    public function down(): void
    {
        Schema::table(config('seo.audit.batches_table', 'seo_audit_batches'), function (Blueprint $table): void {
            $table->dropColumn(['average_pagespeed_score', 'records_not_indexed', 'records_with_broken_links']);
        });
    }
};
