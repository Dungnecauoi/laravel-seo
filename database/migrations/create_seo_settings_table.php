<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('seo.settings.table', 'seo_settings'), function (Blueprint $table): void {
            // The dot-notated config key it overrides, e.g. 'defaults.title'
            // or 'verification.google' — never the whole seo.php tree, only
            // whichever keys seo.settings.keys allowlists.
            $table->string('key', 191)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seo.settings.table', 'seo_settings'));
    }
};
