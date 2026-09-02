<?php

declare(strict_types=1);

namespace Duxbo\Seo\Resolution\Tokens;

use Duxbo\Seo\Contracts\TokenResolver;
use Duxbo\Seo\Data\SeoContext;

/**
 * Reads one value straight out of the model's `seoAttributes()`.
 *
 * Most built-in tokens are this — `%title%`, `%excerpt%`, `%author%` differ
 * only in which key they read, so they are instances rather than classes.
 */
final class AttributeToken implements TokenResolver
{
    /**
     * @param  list<string>  $attributes  Keys to try, in order.
     */
    public function __construct(
        private readonly string $key,
        private readonly array $attributes,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function resolve(SeoContext $context, ?string $argument = null): ?string
    {
        foreach ($this->attributes as $attribute) {
            $value = $context->attribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }

            // A relation rendered to an array — take the first label present.
            if (is_array($value) && $value !== []) {
                $first = reset($value);

                if (is_string($first) && trim($first) !== '') {
                    return $first;
                }

                if (is_array($first)) {
                    foreach (['name', 'title', 'label'] as $label) {
                        if (isset($first[$label]) && is_string($first[$label])) {
                            return $first[$label];
                        }
                    }
                }
            }
        }

        return null;
    }
}
