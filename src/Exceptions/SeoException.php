<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use Throwable;

/**
 * Marker for every exception this package throws.
 *
 * An interface rather than a base class, so exceptions can still extend the
 * most accurate SPL type (InvalidArgumentException, RuntimeException, …)
 * while remaining catchable as one group.
 */
interface SeoException extends Throwable
{
}
