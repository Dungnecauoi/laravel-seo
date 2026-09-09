<?php

declare(strict_types=1);

namespace Duxbo\Seo\Console;

use Duxbo\Seo\Exceptions\GoogleIndexingSubmissionFailed;
use Duxbo\Seo\GoogleIndexing\GoogleIndexingClient;
use Illuminate\Console\Command;

/**
 * Manual or scripted submission — `php artisan seo:google-indexing /bai-viet-moi`
 * after a deploy script imports content. Mirrors {@see IndexNowCommand}: this
 * package does not listen for its own save events to auto-submit, so an
 * outbound request to a third party never fires just because a project
 * installed this package.
 */
final class GoogleIndexingCommand extends Command
{
    protected $signature = 'seo:google-indexing
        {urls* : Absolute or site-relative URLs to submit}
        {--deleted : Notify Google the URL(s) were removed instead of updated}';

    protected $description = "Notify Google's Indexing API that URL(s) were updated (or removed)";

    public function handle(GoogleIndexingClient $client): int
    {
        /** @var list<string> $urls */
        $urls = $this->argument('urls');

        if (! $client->enabled()) {
            $this->error('seo.google_indexing.enabled is false — nothing was sent.');

            return self::FAILURE;
        }

        $type = $this->option('deleted')
            ? GoogleIndexingClient::TYPE_URL_DELETED
            : GoogleIndexingClient::TYPE_URL_UPDATED;

        try {
            $results = $client->submit($urls, $type);
        } catch (GoogleIndexingSubmissionFailed $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $failed = array_filter($results, static fn (array $result): bool => ! $result['successful']);

        foreach ($failed as $result) {
            $this->error("{$result['url']}: {$result['error']}");
        }

        $successCount = count($results) - count($failed);
        $this->info("{$successCount}/".count($results).' URL(s) submitted to Google Indexing API.');

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
