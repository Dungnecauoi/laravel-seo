<?php

declare(strict_types=1);

namespace Duxbo\Seo\Redirects;

use Duxbo\Seo\Enums\RedirectMatchType;
use Duxbo\Seo\Enums\RedirectType;
use Illuminate\Database\Eloquent\Model;

/**
 * A redirect rule.
 *
 * Saving goes through {@see RedirectRepository} rather than the model directly,
 * so the safety checks cannot be bypassed by writing a row by hand.
 *
 * @property string $source_path
 * @property string $source_hash
 * @property RedirectMatchType $source_type
 * @property string|null $target
 * @property RedirectType $status_code
 * @property bool $is_active
 * @property string|null $locale
 * @property int $hits
 */
final class Redirect extends Model
{
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'source_type' => RedirectMatchType::class,
        'status_code' => RedirectType::class,
        'is_active' => 'boolean',
        'hits' => 'integer',
        'last_hit_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('seo.redirects.table', 'seo_redirects');
    }

    public function getConnectionName(): ?string
    {
        return config('seo.storage.connection') ?? parent::getConnectionName();
    }
}
