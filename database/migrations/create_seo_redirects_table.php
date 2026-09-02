<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('seo.redirects.table', 'seo_redirects'), function (Blueprint $table): void {
            $table->id();

            $table->text('source_path');

            // URLs outgrow every index key limit, so uniqueness and exact-match
            // lookups run against a hash of the path rather than the path.
            $table->char('source_hash', 32);

            $table->string('source_type', 10)->default('exact');
            $table->text('target')->nullable();
            $table->smallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->string('locale', 10)->nullable();
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['source_hash', 'locale'], 'seo_redirects_source_unique');
            $table->index(['is_active', 'source_type'], 'seo_redirects_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seo.redirects.table', 'seo_redirects'));
    }
};
