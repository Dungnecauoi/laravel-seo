<?php

declare(strict_types=1);

namespace Duxbo\Seo\Redirects;

use Duxbo\Seo\Enums\RedirectMatchType;
use Duxbo\Seo\Enums\RedirectType;
use Illuminate\Database\Eloquent\Model;

/**
 * A redirect rule.
 *
 * {@see RedirectRepository} is still the intended way to write one — it
 * normalises the source path and flushes the matcher cache, which a bare
 * `save()` does not. What it no longer has a monopoly on is *safety*: a
 * `saving` hook below re-runs {@see RedirectGuard} through
 * {@see RedirectSaveGuard} whenever a column the checks care about is dirty,
 * so a hand-written row — a seeder, `Redirect::create()` in tinker, an
 * integration that never learned the repository exists — cannot store an
 * unsafe target or a loop either. The one path this cannot close is a raw
 * query-builder write (`Redirect::query()->update(...)`,
 * `DB::table(...)->update(...)`): Eloquent never fires model events for
 * those, on this model or any other, which is why {@see RedirectRepository}
 * itself no longer uses them (see its `disable()`/`setActive()`/`delete()`).
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
     * @var list<string>
     */
    private const GUARDED_COLUMNS = ['source_path', 'source_type', 'target', 'status_code', 'is_active'];

    protected static function booted(): void
    {
        static::saving(static function (self $redirect): void {
            if (! $redirect->isDirty(self::GUARDED_COLUMNS)) {
                return;
            }

            app(RedirectSaveGuard::class)->assertSafe($redirect);
        });
    }

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
