<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('seo.indexnow.log_table', 'seo_indexnow_log'), function (Blueprint $table): void {
            $table->id();

            // One row per API call, not per URL — that is what IndexNow itself
            // bills as one submission, and a batch of a thousand URLs pushed
            // in one request should not become a thousand rows.
            $table->json('urls');
            $table->unsignedInteger('url_count')->default(0);

            $table->boolean('successful')->default(false);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('error')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['created_at', 'successful'], 'seo_indexnow_log_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seo.indexnow.log_table', 'seo_indexnow_log'));
    }
};
