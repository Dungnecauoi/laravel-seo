<?php

declare(strict_types=1);

namespace Duxbo\Seo\Resolution\Tokens;

use DateTimeInterface;
use Duxbo\Seo\Contracts\TokenResolver;
use Duxbo\Seo\Data\SeoContext;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Formats a date attribute — `%date%`, `%modified%`.
 *
 * `%date(Y)%` overrides the configured format for one use.
 */
final class DateToken implements TokenResolver
{
    /**
     * @param  list<string>  $attributes
     */
    public function __construct(
        private readonly string $key,
        private readonly array $attributes,
        private readonly Config $config,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function resolve(SeoContext $context, ?string $argument = null): ?string
    {
        $format = $argument ?? $this->defaultFormat();

        foreach ($this->attributes as $attribute) {
            $value = $context->attribute($attribute);

            if ($value instanceof DateTimeInterface) {
                return $value->format($format);
            }

            if (is_string($value) && $value !== '') {
                $timestamp = strtotime($value);

                if ($timestamp !== false) {
                    return date($format, $timestamp);
                }
            }
        }

        return null;
    }

    private function defaultFormat(): string
    {
        $format = $this->config->get('seo.tokens.date_format', 'd/m/Y');

        return is_string($format) ? $format : 'd/m/Y';
    }
}
