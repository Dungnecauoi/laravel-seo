<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('seo.audit.batches_table', 'seo_audit_batches'), function (Blueprint $table): void {
            $table->id();

            $table->string('model', 191);
            $table->string('locale', 10)->nullable();

            $table->unsignedInteger('total_records')->default(0);
            $table->decimal('average_score', 5, 2)->nullable();
            $table->unsignedTinyInteger('min_score')->nullable();
            $table->unsignedTinyInteger('max_score')->nullable();

            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();

            $table->index(['model', 'started_at'], 'seo_audit_batches_model_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seo.audit.batches_table', 'seo_audit_batches'));
    }
};
