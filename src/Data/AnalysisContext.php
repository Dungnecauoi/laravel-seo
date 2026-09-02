<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

use Duxbo\Seo\Data\Concerns\Copyable;

/**
 * Everything a check needs to measure one page.
 *
 * Checks receive this and nothing else, so a check is a pure function over it —
 * trivially testable, and unable to reach into the database behind the
 * analyser's back.
 */
final class AnalysisContext
{
    use Copyable;

    /**
     * @param  list<string>  $secondaryKeywords
     */
    public function __construct(
        public readonly ExtractedContent $content,
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $url = null,
        public readonly ?string $focusKeyword = null,
        public readonly array $secondaryKeywords = [],
        public readonly ?string $locale = null,
    ) {
    }

    public function hasKeyword(): bool
    {
        return $this->focusKeyword !== null && trim($this->focusKeyword) !== '';
    }

    /**
     * Path portion of the URL, which is where a keyword in the slug lives.
     */
    public function path(): ?string
    {
        if ($this->url === null) {
            return null;
        }

        $path = parse_url($this->url, PHP_URL_PATH);

        return is_string($path) ? $path : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function constructorArgs(): array
    {
        return [
            'content' => $this->content,
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->url,
            'focusKeyword' => $this->focusKeyword,
            'secondaryKeywords' => $this->secondaryKeywords,
            'locale' => $this->locale,
        ];
    }
}
