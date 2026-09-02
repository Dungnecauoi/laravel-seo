<?php

declare(strict_types=1);

namespace Duxbo\Seo\Resolution;

use Duxbo\Seo\Contracts\TokenResolver;
use Duxbo\Seo\Data\SeoContext;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Expands `%token%` placeholders in a template.
 *
 * The tidying afterwards is the part that matters. A template like
 * `%title% %sep% %category% %sep% %sitename%` must not render as
 * "Bài viết -  - Trang chủ" when the record has no category, so unresolved
 * tokens take the surrounding separator with them.
 */
final class TokenExpander
{
    /**
     * Matches `%name%` and `%name(argument)%`.
     */
    private const PATTERN = '/%([a-z_]+)(?:\(([^)]*)\))?%/i';

    /** @var array<string, TokenResolver> */
    private array $resolvers = [];

    public function __construct(private readonly Config $config)
    {
    }

    public function register(TokenResolver $resolver): void
    {
        $this->resolvers[strtolower($resolver->key())] = $resolver;
    }

    public function forget(string $key): void
    {
        unset($this->resolvers[strtolower($key)]);
    }

    public function has(string $key): bool
    {
        return isset($this->resolvers[strtolower($key)]);
    }

    /**
     * @return array<string, TokenResolver>
     */
    public function all(): array
    {
        return $this->resolvers;
    }

    public function expand(?string $template, SeoContext $context): ?string
    {
        if ($template === null || $template === '') {
            return $template;
        }

        $expanded = preg_replace_callback(
            self::PATTERN,
            function (array $matches) use ($context): string {
                $resolver = $this->resolvers[strtolower($matches[1])] ?? null;

                if ($resolver === null) {
                    // An unknown token is dropped rather than left in place:
                    // a literal "%categry%" reaching a page title is worse
                    // than a slightly shorter title.
                    return '';
                }

                return $resolver->resolve($context, $matches[2] ?? null) ?? '';
            },
            $template,
        );

        if ($expanded === null) {
            return $template;
        }

        $tidied = $this->tidy($expanded);

        return $tidied === '' ? null : $tidied;
    }

    /**
     * Collapse the gaps left by tokens that resolved to nothing.
     */
    public function tidy(string $value): string
    {
        $separator = $this->separator();

        $value = (string) preg_replace('/\s+/u', ' ', $value);

        if ($separator !== '') {
            $quoted = preg_quote($separator, '/');

            // Repeated separators, however many, become one.
            $value = (string) preg_replace("/(?:{$quoted}\s*){2,}/u", $separator.' ', $value);

            // And a separator at either end has nothing left to separate.
            $value = (string) preg_replace("/^\s*(?:{$quoted}\s*)+/u", '', $value);
            $value = (string) preg_replace("/(?:\s*{$quoted})+\s*$/u", '', $value);
        }

        return trim($value);
    }

    private function separator(): string
    {
        $separator = $this->config->get('seo.separator', '-');

        return is_string($separator) ? trim($separator) : '-';
    }
}
