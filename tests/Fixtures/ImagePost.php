<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Fixtures;

use Duxbo\Seo\Concerns\HasSeo;
use Duxbo\Seo\Contracts\HasSitemapImages;
use Duxbo\Seo\Contracts\Seoable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $slug
 */
final class ImagePost extends Model implements HasSitemapImages, Seoable
{
    use HasSeo;

    protected $table = 'posts';

    protected $guarded = [];

    protected array $seoMap = ['title' => 'name'];

    public function seoUrl(): string
    {
        return 'https://trangcuatoi.vn/anh/'.$this->slug;
    }

    /**
     * @return list<string>
     */
    public function seoSitemapImages(): array
    {
        return [
            'https://trangcuatoi.vn/anh/'.$this->slug.'-1.jpg',
            'https://trangcuatoi.vn/anh/'.$this->slug.'-2.jpg',
        ];
    }
}
