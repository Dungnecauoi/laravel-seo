<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use InvalidArgumentException;

final class UnsafeRedirect extends InvalidArgumentException implements SeoException
{
    /**
     * @param  list<string>  $allowed
     */
    public static function hostNotAllowed(string $host, array $allowed): self
    {
        return new self(sprintf(
            'Refusing a redirect to [%s]. Anyone who can write redirect rules could otherwise '
            .'turn a trusted URL on your domain into a phishing link. Allowed hosts: %s. '
            .'Add one under seo.redirects.allowed_hosts if it is genuinely yours.',
            $host,
            $allowed === [] ? 'none besides your own' : implode(', ', $allowed),
        ));
    }

    public static function protocolRelative(string $target): self
    {
        return new self(sprintf(
            'Refusing the protocol-relative target [%s]. It looks like a path but sends visitors '
            .'to another host. Write it as a full URL if that is intended.',
            $target,
        ));
    }

    public static function unparseable(string $target): self
    {
        return new self(sprintf(
            'Redirect target [%s] is neither a path beginning with "/" nor a valid absolute URL.',
            $target,
        ));
    }

    public static function invalidPattern(string $pattern): self
    {
        return new self(sprintf('Redirect pattern [%s] is not a valid regular expression.', $pattern));
    }

    public static function catastrophicPattern(string $pattern): self
    {
        return new self(sprintf(
            'Redirect pattern [%s] nests a quantifier inside another. On a crafted path that '
            .'backtracks exponentially and hangs the request. Rewrite it without the nesting.',
            $pattern,
        ));
    }

    /**
     * @param  list<string>  $chain
     */
    public static function loop(array $chain): self
    {
        return new self('Refusing a redirect loop: '.implode(' → ', $chain));
    }

    /**
     * @param  list<string>  $chain
     */
    public static function chainTooLong(array $chain): self
    {
        return new self(sprintf(
            'Redirect chain is longer than %d hops: %s. Point the first rule at the final '
            .'destination instead; browsers give up, and each hop loses ranking signal.',
            count($chain),
            implode(' → ', $chain),
        ));
    }
}
