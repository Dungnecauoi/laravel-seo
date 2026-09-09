<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('seo.broken_links.checks_table', 'seo_link_checks'), function (Blueprint $table): void {
            $table->id();

            // One row per distinct external URL, not per source page — the
            // same URL referenced from ten different posts is checked once,
            // not ten times. seo_external_links.target_hash joins into this
            // by the same hash to answer "who links to this broken URL".
            $table->text('url');
            $table->char('url_hash', 32)->unique('seo_link_checks_url_unique');

            $table->boolean('successful')->default(false);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index('successful', 'seo_link_checks_successful_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seo.broken_links.checks_table', 'seo_link_checks'));
    }
};
