<?php

declare(strict_types=1);

namespace Duxbo\Seo\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * One record's result within an {@see AuditBatch} — the history `seo_meta`
 * itself does not keep, since a stored metadata row only ever holds the
 * latest state, not a trend line.
 *
 * @property int $batch_id
 * @property string $seoable_type
 * @property string $seoable_id
 * @property string|null $locale
 * @property int $score
 * @property list<string>|null $failed_checks
 */
final class Audit extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'failed_checks' => 'array',
        'created_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('seo.audit.audits_table', 'seo_audits');
    }

    public function getConnectionName(): ?string
    {
        return config('seo.storage.connection') ?? parent::getConnectionName();
    }
}
