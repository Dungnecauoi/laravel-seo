<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('seo.google_indexing.log_table', 'seo_google_indexing_log'), function (Blueprint $table): void {
            $table->id();

            // One row per URL, unlike seo_indexnow_log's one row per batch
            // call — the Indexing API itself takes exactly one URL per
            // publish call, so that is what "one submission" means here.
            $table->string('url', 2048);
            $table->string('type', 32);

            $table->boolean('successful')->default(false);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('error')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['created_at', 'successful'], 'seo_google_indexing_log_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seo.google_indexing.log_table', 'seo_google_indexing_log'));
    }
};
