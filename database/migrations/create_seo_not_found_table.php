<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('seo.not_found.table', 'seo_not_found'), function (Blueprint $table): void {
            $table->id();

            $table->text('path');
            $table->char('path_hash', 32);

            // One row per path with a counter, not one row per request. Bot
            // scanning would otherwise add tens of thousands of rows a day.
            $table->unsignedBigInteger('hits')->default(1);

            $table->text('referrer')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->unique('path_hash', 'seo_not_found_path_unique');
            $table->index('last_seen_at', 'seo_not_found_seen_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seo.not_found.table', 'seo_not_found'));
    }
};
