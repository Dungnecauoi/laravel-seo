<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('seo.search_console.table', 'seo_search_console_stats'), function (Blueprint $table): void {
            $table->id();

            $table->text('url');

            // Uniqueness and lookups run against a hash, the same reason
            // seo_redirects hashes its source path — a URL outgrows every
            // index key limit MySQL allows on a text column directly.
            $table->char('url_hash', 32);

            $table->date('date');

            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 6, 4)->default(0);
            $table->decimal('position', 6, 2)->nullable();

            $table->timestamps();

            // A day's row is replaced wholesale when re-synced (Search
            // Console itself revises a day's numbers for about 48 hours
            // after it happens), not accumulated into a second row.
            $table->unique(['url_hash', 'date'], 'seo_search_console_stats_unique');
            $table->index('date', 'seo_search_console_stats_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seo.search_console.table', 'seo_search_console_stats'));
    }
};
