<?php

declare(strict_types=1);

namespace Duxbo\Seo\Console;

use Duxbo\Seo\NotFound\NotFoundLogger;
use Illuminate\Console\Command;

final class PruneNotFoundCommand extends Command
{
    protected $signature = 'seo:prune-404 {--days=90 : Delete entries not seen for this many days}';

    protected $description = 'Delete stale entries from the 404 monitor';

    public function handle(NotFoundLogger $logger): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('--days must be at least 1.');

            return self::FAILURE;
        }

        $deleted = $logger->prune($days);

        $this->info("Deleted {$deleted} entr(ies) not seen in {$days} days.");

        return self::SUCCESS;
    }
}
