<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Fixtures;

use Duxbo\Seo\Concerns\HasSeo;
use Duxbo\Seo\Contracts\Seoable;
use Illuminate\Database\Eloquent\Model;

/**
 * Deliberately does not override seoUrl() and is never mapped under
 * seo.models.*.route — HasSeo's default seoUrl() then has no way to build
 * one, and throws. Exists to exercise that failure path: code that must
 * degrade gracefully when a model's URL genuinely cannot be resolved yet,
 * rather than assuming every Seoable model always has a working seoUrl().
 *
 * @property string $name
 * @property string $slug
 */
final class UnroutedPost extends Model implements Seoable
{
    use HasSeo;

    protected $table = 'posts';

    protected $guarded = [];
}
