<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

/**
 * A model that knows the trail leading to it.
 *
 * Optional and separate from {@see Seoable}, because a trail describes the
 * route a visitor took rather than a property of the record: a product reached
 * through two category paths has two trails, and a model alone cannot always
 * say which. Where the model does know, implement this; where it does not, set
 * `breadcrumbs` on the context from the controller instead.
 */
interface HasBreadcrumbs
{
    /**
     * Accepted per item: a {@see \Duxbo\Seo\Data\Breadcrumb}, a plain string,
     * `['Trang chủ' => '/']`, or `['name' => …, 'url' => …]`. The final item is
     * the current page and its URL is dropped — Google flags a self-referential
     * last crumb as an error.
     *
     * @return list<mixed>
     */
    public function seoBreadcrumbs(): array;
}
