<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Fixtures;

use Duxbo\Seo\Concerns\HasSeo;
use Duxbo\Seo\Contracts\HasSitemapVideo;
use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Data\SitemapVideo;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $slug
 */
final class VideoPost extends Model implements HasSitemapVideo, Seoable
{
    use HasSeo;

    protected $table = 'posts';

    protected $guarded = [];

    protected array $seoMap = ['title' => 'name'];

    public function seoUrl(): string
    {
        return 'https://trangcuatoi.vn/video/'.$this->slug;
    }

    /**
     * @return list<SitemapVideo>
     */
    public function seoSitemapVideos(): array
    {
        return [
            new SitemapVideo(
                thumbnailLoc: 'https://trangcuatoi.vn/thumb/'.$this->slug.'.jpg',
                title: $this->name,
                description: 'Mô tả video.',
                contentLoc: 'https://trangcuatoi.vn/video-file/'.$this->slug.'.mp4',
                durationSeconds: 120,
            ),
        ];
    }
}
