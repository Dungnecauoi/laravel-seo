<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('seo.ai.table', 'seo_ai_log'), function (Blueprint $table): void {
            $table->id();

            $table->string('driver', 40);
            $table->string('model', 120)->nullable();
            $table->string('purpose', 60)->nullable();

            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);

            // Cost is stored as computed at the time, not derived on read:
            // published prices change, and a historic total should not move
            // when they do.
            $table->decimal('cost', 12, 6)->nullable();
            $table->string('currency', 3)->nullable();

            $table->boolean('from_cache')->default(false);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['created_at', 'driver'], 'seo_ai_log_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seo.ai.table', 'seo_ai_log'));
    }
};
