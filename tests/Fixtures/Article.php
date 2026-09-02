<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Fixtures;

use Duxbo\Seo\Concerns\HasSeo;
use Duxbo\Seo\Contracts\HasBreadcrumbs;
use Duxbo\Seo\Contracts\HasSchema;
use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Data\SeoContext;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $cover_url
 */
final class Article extends Model implements HasBreadcrumbs, HasSchema, Seoable
{
    use HasSeo;

    protected $table = 'posts';

    protected $guarded = [];

    protected array $seoMap = [
        'title' => 'name',
        'description' => 'excerpt',
        'og.image' => 'cover_url',
    ];

    public function seoUrl(): string
    {
        return 'https://trangcuatoi.vn/bai-viet/'.$this->slug;
    }

    /**
     * @return array<string, mixed>
     */
    public function seoSchema(SeoContext $context): array
    {
        return [
            '@type' => 'Article',
            'headline' => $this->name,
            // Deliberately not ISO 8601, and a relative image path — the
            // assembler is expected to fix both.
            'datePublished' => '2026-03-17 09:30:00',
            'image' => '/storage/anh.jpg',
            'author' => ['@type' => 'Person', 'name' => 'Nguyễn Văn A'],
            'wordCount' => null,
        ];
    }

    /**
     * @return list<mixed>
     */
    public function seoBreadcrumbs(): array
    {
        return [
            ['Trang chủ' => '/'],
            ['name' => 'Tin tức', 'url' => '/tin-tuc'],
            $this->name,
        ];
    }
}
