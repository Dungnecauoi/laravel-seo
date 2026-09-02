<?php

declare(strict_types=1);

namespace Duxbo\Seo\Console;

use Duxbo\Seo\Sitemap\SitemapGenerator;
use Illuminate\Console\Command;

final class SitemapCommand extends Command
{
    protected $signature = 'seo:sitemap
        {--path= : Directory to write into, defaults to the public path}
        {--list : Show the registered sources without writing anything}';

    protected $description = 'Write the sitemap index and its source files to disk';

    public function handle(SitemapGenerator $generator): int
    {
        $sources = $generator->sources();

        if ($sources === []) {
            $this->warn('No sitemap sources are registered.');
            $this->line('Sources are opt-in — add them under seo.sitemap.sources in config/seo.php.');

            return self::SUCCESS;
        }

        if ($this->option('list')) {
            foreach ($sources as $source) {
                $this->line("  {$source->name()}  →  ".$generator->urlFor($source->name()));
            }

            return self::SUCCESS;
        }

        $path = $this->option('path');
        $directory = is_string($path) ? $path : public_path();

        $this->info('Writing sitemaps to '.$directory);

        $written = $generator->writeTo($directory);

        foreach ($written as $file) {
            $this->line('  '.basename($file));
        }

        $this->newLine();
        $this->info(count($written).' file(s) written.');

        return self::SUCCESS;
    }
}
