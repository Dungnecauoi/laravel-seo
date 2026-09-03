<?php

declare(strict_types=1);

namespace Duxbo\Seo\Console;

use Duxbo\Seo\Exceptions\IndexNowSubmissionFailed;
use Duxbo\Seo\IndexNow\IndexNowSubmitter;
use Illuminate\Console\Command;

/**
 * Manual or scripted submission — `php artisan seo:indexnow /bai-viet-moi`
 * after a deploy script imports content, or from a queued job listening for
 * whatever event the host application fires on publish. This package does
 * not listen for its own save events to auto-submit: that would mean a
 * blocking outbound request on every panel save, for every project, whether
 * or not IndexNow is even relevant to it.
 */
final class IndexNowCommand extends Command
{
    protected $signature = 'seo:indexnow {urls* : Absolute or site-relative URLs to submit}';

    protected $description = 'Notify Bing, Yandex and other IndexNow-participating engines that URLs changed';

    public function handle(IndexNowSubmitter $submitter): int
    {
        /** @var list<string> $urls */
        $urls = $this->argument('urls');

        if (config('seo.indexnow.enabled', false) !== true) {
            $this->error('seo.indexnow.enabled is false — nothing was sent.');

            return self::FAILURE;
        }

        try {
            $submitted = $submitter->submit($urls);
        } catch (IndexNowSubmissionFailed $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $submitted) {
            $this->error('Nothing was submitted.');

            return self::FAILURE;
        }

        $this->info(count($urls).' URL(s) submitted to IndexNow.');

        return self::SUCCESS;
    }
}
