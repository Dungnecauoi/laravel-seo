<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            $table->id();

            // 160 rather than Laravel's usual 255: under utf8mb4 a 255-char
            // column is 1020 bytes, past the 767-byte per-column index limit
            // on MySQL 5.7 without innodb_large_prefix. 160 chars is 640 bytes
            // and indexes on every engine. Morph maps keep values far shorter
            // than that anyway.
            $table->string('seoable_type', 160);

            // A string, not an integer, so UUID and ULID keyed models work
            // without a second table shape. Values are cast on the way in.
            $table->string('seoable_id', 64);

            // Null means the shared record that applies to every language.
            $table->string('locale', 10)->nullable();

            $table->text('title')->nullable();
            $table->text('description')->nullable();
            $table->text('canonical_url')->nullable();
            $table->json('robots')->nullable();
            $table->json('og')->nullable();
            $table->json('twitter')->nullable();
            $table->string('focus_keyword', 191)->nullable();
            $table->json('secondary_keywords')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->timestamp('analysed_at')->nullable();
            $table->json('extra')->nullable();
            $table->timestamps();

            // Named explicitly: the generated name would exceed MySQL's 64
            // character identifier limit once a long table prefix is in play.
            $table->unique(['seoable_type', 'seoable_id', 'locale'], 'seo_meta_seoable_locale_unique');
            $table->index(['seoable_type', 'seoable_id'], 'seo_meta_seoable_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return config('seo.storage.table', 'seo_meta');
    }
};
