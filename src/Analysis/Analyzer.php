<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Contracts\AnalysisCheck;
use Duxbo\Seo\Contracts\ContentExtractor;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\AnalysisReport;
use Duxbo\Seo\Data\CheckResult;
use Duxbo\Seo\Data\SeoContext;
use Duxbo\Seo\Events\AnalysisCompleted;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Scores a page against the registered checks.
 *
 * Checks are independent and declare which locales they understand, so
 * English-only measures sit out on Vietnamese content instead of producing a
 * confident number that means nothing.
 */
final class Analyzer
{
    /** @var list<AnalysisCheck> */
    private array $checks = [];

    /** @var array<string, int> */
    private array $weightOverrides = [];

    public function __construct(
        private readonly Container $container,
        private readonly ContentExtractor $extractor,
        private readonly Config $config,
        private readonly Dispatcher $events,
    ) {
    }

    /**
     * @param  class-string<AnalysisCheck>|AnalysisCheck  $check
     */
    public function register(string|AnalysisCheck $check): self
    {
        $this->checks[] = is_string($check) ? $this->container->make($check) : $check;

        return $this;
    }

    public function remove(string $id): self
    {
        $this->checks = array_values(array_filter(
            $this->checks,
            static fn (AnalysisCheck $check): bool => $check->id() !== $id,
        ));

        return $this;
    }

    public function setWeight(string $id, int $weight): self
    {
        $this->weightOverrides[$id] = $weight;

        return $this;
    }

    /**
     * @return list<AnalysisCheck>
     */
    public function checks(): array
    {
        return $this->checks;
    }

    /**
     * Analyse raw content against a keyword.
     */
    public function analyze(
        string $content,
        ?string $keyword = null,
        ?string $title = null,
        ?string $description = null,
        ?string $url = null,
        ?string $locale = null,
    ): AnalysisReport {
        return $this->run(new AnalysisContext(
            content: $this->extractor->extract($content),
            title: $title,
            description: $description,
            url: $url,
            focusKeyword: $keyword,
            locale: $locale,
        ));
    }

    /**
     * Analyse a page using its resolved metadata.
     */
    public function analyzeContext(SeoContext $seo, string $content): AnalysisReport
    {
        $data = $seo->data;

        return $this->run(new AnalysisContext(
            content: $this->extractor->extract($content),
            title: $data->title,
            description: $data->description,
            url: $data->canonical ?? $seo->url,
            focusKeyword: $data->focusKeyword,
            secondaryKeywords: $data->secondaryKeywords,
            locale: $seo->locale,
        ));
    }

    public function run(AnalysisContext $context): AnalysisReport
    {
        $results = [];
        $weights = [];

        foreach ($this->checks as $check) {
            if (! $this->applies($check, $context->locale)) {
                continue;
            }

            $results[] = $this->safely($check, $context);
            $weights[$check->id()] = $this->weightOverrides[$check->id()] ?? $check->weight();
        }

        $report = AnalysisReport::fromResults($results, $weights, $context->locale);

        $this->events->dispatch(new AnalysisCompleted($context, $report));

        return $report;
    }

    private function applies(AnalysisCheck $check, ?string $locale): bool
    {
        if ($check instanceof Check) {
            return $check->appliesTo($locale);
        }

        $supported = $check->locales();

        if (in_array('*', $supported, true)) {
            return true;
        }

        if ($locale === null) {
            return false;
        }

        $base = strtok($locale, '-') ?: $locale;

        return in_array($locale, $supported, true) || in_array($base, $supported, true);
    }

    /**
     * A check that throws must not take the whole report down with it.
     *
     * Third-party checks run here too, and a scoring panel that shows nothing
     * because one rule hit an edge case is worse than one honest gap.
     */
    private function safely(AnalysisCheck $check, AnalysisContext $context): CheckResult
    {
        try {
            return $check->run($context);
        } catch (\Throwable $e) {
            if ($this->config->get('app.debug') === true) {
                throw $e;
            }

            return CheckResult::skipped($check->id(), 'seo::analysis.errored');
        }
    }
}
