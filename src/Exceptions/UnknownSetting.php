<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use InvalidArgumentException;

final class UnknownSetting extends InvalidArgumentException implements SeoException
{
    /**
     * @param  list<string>  $allowed
     */
    public static function named(string $key, array $allowed): self
    {
        return new self(sprintf(
            'No dynamic setting named [%s]. Allowed: %s. Add it to seo.settings.keys to make '
            .'it settable — arbitrary config keys are never accepted, the same reason '
            .'seo.api.models allowlists which model types the API can touch.',
            $key,
            $allowed === [] ? 'none configured' : implode(', ', $allowed),
        ));
    }
}
