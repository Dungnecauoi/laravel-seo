<?php

declare(strict_types=1);

namespace Duxbo\Seo\Storage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Row in the metadata table.
 *
 * Deliberately thin: translation between this row and {@see \Duxbo\Seo\Data\SeoData}
 * lives in the repository, so the DTO never learns about Eloquent and the model
 * never learns about the resolution pipeline.
 *
 * @property string $seoable_type
 * @property string $seoable_id
 * @property string|null $locale
 * @property string|null $title
 * @property string|null $description
 * @property string|null $canonical_url
 * @property array<array-key, mixed>|null $robots
 * @property array<array-key, mixed>|null $og
 * @property array<array-key, mixed>|null $twitter
 * @property string|null $focus_keyword
 * @property array<array-key, mixed>|null $secondary_keywords
 * @property int|null $score
 * @property array<array-key, mixed>|null $extra
 */
final class SeoMeta extends Model
{
    protected $guarded = [];

    /**
     * The `$casts` property rather than the `casts()` method, which only
     * exists from Laravel 11.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'robots' => 'array',
        'og' => 'array',
        'twitter' => 'array',
        'secondary_keywords' => 'array',
        'extra' => 'array',
        'score' => 'integer',
        'analysed_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('seo.storage.table', 'seo_meta');
    }

    public function getConnectionName(): ?string
    {
        return config('seo.storage.connection') ?? parent::getConnectionName();
    }

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
