<?php

declare(strict_types=1);

namespace Duxbo\Seo\Enums;

/**
 * `<changefreq>` values defined by the sitemaps.org protocol.
 */
enum ChangeFrequency: string
{
    case Always = 'always';
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Never = 'never';
}
