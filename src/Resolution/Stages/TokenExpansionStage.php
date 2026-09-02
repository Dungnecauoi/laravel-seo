<?php

declare(strict_types=1);

namespace Duxbo\Seo\Resolution\Stages;

use Closure;
use Duxbo\Seo\Contracts\ResolverStage;
use Duxbo\Seo\Data\SeoContext;
use Duxbo\Seo\Resolution\TokenExpander;

/**
 * Turns templates into text.
 *
 * Runs after every stage that can supply a value, so a token written into a
 * stored title expands too — people do put `%sitename%` in the panel.
 */
final class TokenExpansionStage implements ResolverStage
{
    public function __construct(private readonly TokenExpander $expander)
    {
    }

    public function handle(SeoContext $context, Closure $next): SeoContext
    {
        $data = $context->data;

        $expanded = $data->with(
            title: $this->expander->expand($data->title, $context),
            description: $this->expander->expand($data->description, $context),
        );

        $og = $data->openGraph;

        if ($og !== null) {
            $expanded = $expanded->with(openGraph: $og->with(
                title: $this->expander->expand($og->title, $context),
                description: $this->expander->expand($og->description, $context),
            ));
        }

        $twitter = $data->twitter;

        if ($twitter !== null) {
            $expanded = $expanded->with(twitter: $twitter->with(
                title: $this->expander->expand($twitter->title, $context),
                description: $this->expander->expand($twitter->description, $context),
            ));
        }

        return $next($context->withData($expanded));
    }
}
