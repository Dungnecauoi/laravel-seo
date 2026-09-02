<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Fixtures;

use Duxbo\Seo\Concerns\HasSeo;
use Duxbo\Seo\Contracts\Seoable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $cover_url
 */
final class Post extends Model implements Seoable
{
    use HasSeo;

    protected $guarded = [];

    /**
     * The property form of the mapping — the shortest of the three ways to
     * declare one.
     *
     * @var array<string, string>
     */
    protected array $seoMap = [
        'title' => 'name',
        'description' => 'excerpt',
        'og.image' => 'cover_url',
    ];

    public function seoUrl(): string
    {
        return 'https://trangcuatoi.vn/bai-viet/'.$this->slug;
    }
}
