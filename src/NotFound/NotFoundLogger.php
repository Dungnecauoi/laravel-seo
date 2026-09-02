<?php

declare(strict_types=1);

namespace Duxbo\Seo\NotFound;

use Duxbo\Seo\Events\NotFoundLogged;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records broken links, without letting the table eat the database.
 *
 * A crawler probing for `/wp-admin` and `/.env` generates tens of thousands of
 * distinct paths a day. Every guard here exists because of that traffic, not
 * because of real visitors.
 */
final class NotFoundLogger
{
    public function __construct(
        private readonly Config $config,
        private readonly Dispatcher $events,
    ) {
    }

    public function log(Request $request): void
    {
        if (! $this->enabled()) {
            return;
        }

        $path = '/'.ltrim($request->getPathInfo(), '/');

        if ($this->isExcluded($path) || ! $this->sampled()) {
            return;
        }

        $hash = md5($path);
        $now = Carbon::now();

        // An upsert incrementing a counter, so one path is one row however many
        // times it is hit.
        $table = $this->table();
        $affected = DB::table($table)
            ->where('path_hash', $hash)
            ->update([
                'hits' => DB::raw('hits + 1'),
                'last_seen_at' => $now,
                'referrer' => $this->truncate($request->headers->get('referer')),
                'user_agent' => $this->truncate($request->userAgent()),
            ]);

        if ($affected === 0) {
            DB::table($table)->insert([
                'path' => $path,
                'path_hash' => $hash,
                'hits' => 1,
                'referrer' => $this->truncate($request->headers->get('referer')),
                'user_agent' => $this->truncate($request->userAgent()),
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);

            $this->enforceRowLimit();
        }

        $this->events->dispatch(new NotFoundLogged($path, $request));
    }

    /**
     * Delete rows older than the retention window.
     */
    public function prune(int $days): int
    {
        return DB::table($this->table())
            ->where('last_seen_at', '<', Carbon::now()->subDays($days))
            ->delete();
    }

    private function enforceRowLimit(): void
    {
        $limit = (int) $this->config->get('seo.not_found.max_rows', 10000);

        if ($limit <= 0) {
            return;
        }

        $table = $this->table();
        $count = DB::table($table)->count();

        if ($count <= $limit) {
            return;
        }

        // Drop the least useful rows first: seen rarely, and long ago.
        $ids = DB::table($table)
            ->orderBy('hits')
            ->orderBy('last_seen_at')
            ->limit($count - $limit)
            ->pluck('id');

        DB::table($table)->whereIn('id', $ids)->delete();
    }

    private function isExcluded(string $path): bool
    {
        /** @var list<string> $patterns */
        $patterns = $this->config->get('seo.not_found.exclude', []);

        foreach ($patterns as $pattern) {
            if (@preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * On a busy site, record a fraction of hits rather than all of them.
     */
    private function sampled(): bool
    {
        $rate = (float) $this->config->get('seo.not_found.sample_rate', 1.0);

        return $rate >= 1.0 || mt_rand() / mt_getrandmax() < $rate;
    }

    private function truncate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr($value, 0, 500);
    }

    private function enabled(): bool
    {
        return $this->config->get('seo.not_found.enabled', true) === true;
    }

    private function table(): string
    {
        /** @var string $table */
        $table = $this->config->get('seo.not_found.table', 'seo_not_found');

        return $table;
    }
}
