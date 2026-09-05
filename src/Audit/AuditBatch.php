<?php

declare(strict_types=1);

namespace Duxbo\Seo\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One run of `php artisan seo:audit` — aggregates over every {@see Audit}
 * row it produced, so a dashboard can chart average score over time without
 * re-scanning every individual audit row for each batch.
 *
 * @property string $model
 * @property string|null $locale
 * @property int $total_records
 * @property float|null $average_score
 * @property int|null $min_score
 * @property int|null $max_score
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 */
final class AuditBatch extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'average_score' => 'float',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('seo.audit.batches_table', 'seo_audit_batches');
    }

    public function getConnectionName(): ?string
    {
        return config('seo.storage.connection') ?? parent::getConnectionName();
    }

    /**
     * @return HasMany<Audit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class, 'batch_id');
    }
}
