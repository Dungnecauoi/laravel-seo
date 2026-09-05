<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks\Universal;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Analysis\Tokenizer;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;
use Duxbo\Seo\Support\Text;
use Illuminate\Contracts\Config\Repository as Config;

final class ContentLength extends Check
{
    public function __construct(
        private readonly Config $config,
        private readonly int $minimum = 600,
    ) {
    }

    public function id(): string
    {
        return 'content-length';
    }

    public function weight(): int
    {
        return 2;
    }

    public function run(AnalysisContext $context): CheckResult
    {
        $text = $context->content->plainText;

        // Syllables rather than words for a space-delimited script (in
        // Vietnamese those differ by roughly a third, so a word count
        // borrowed from English guidance misleads); letters for Chinese,
        // Japanese or Thai, which run words together with no separator a
        // whitespace split could ever find. $minimum below is tuned for the
        // first case only — see the config comment for why the second gets
        // its own, admittedly heuristic, threshold.
        $spaceDelimited = Text::isSpaceDelimitedScript($text);
        $count = Tokenizer::count($text);
        $minimum = $spaceDelimited
            ? $this->minimum
            : (int) $this->config->get('seo.analysis.content_length_cjk_minimum', 800);

        $data = ['count' => $count, 'minimum' => $minimum];

        if ($count === 0) {
            return CheckResult::skipped($this->id());
        }

        if ($count < $minimum) {
            return CheckResult::warning(
                $this->id(),
                'seo::analysis.content_length.short',
                'seo::analysis.content_length.hint',
                $data,
            );
        }

        return CheckResult::pass($this->id(), 'seo::analysis.content_length.pass', $data);
    }
}
