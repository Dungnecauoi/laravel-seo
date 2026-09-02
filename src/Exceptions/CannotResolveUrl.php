<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use RuntimeException;

final class CannotResolveUrl extends RuntimeException implements SeoException
{
    public static function forModel(string $class): self
    {
        return new self(sprintf(
            'No public URL is known for [%s]. A URL cannot be guessed, so declare one: '
            .'override seoUrl() on the model, or map it in config/seo.php under '
            ."models.%s.route (e.g. ['name' => 'posts.show', 'parameter' => 'post']).",
            $class,
            $class,
        ));
    }

    public static function missingRoute(string $class, string $route): self
    {
        return new self(sprintf(
            'Route [%s] is configured as the URL for [%s] but is not registered.',
            $route,
            $class,
        ));
    }
}
