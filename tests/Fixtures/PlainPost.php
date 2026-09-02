<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Fixtures;

use Duxbo\Seo\Concerns\HasSeo;
use Duxbo\Seo\Contracts\Seoable;
use Illuminate\Database\Eloquent\Model;

/**
 * Same table as Post, but declares no mapping at all — so resolution has to
 * reach the template and global default stages.
 *
 * @property string $name
 * @property string $slug
 */
final class PlainPost extends Model implements Seoable
{
    use HasSeo;

    protected $table = 'posts';

    protected $guarded = [];

    public function seoUrl(): string
    {
        return 'https://trangcuatoi.vn/bai-viet/'.$this->slug;
    }
}
