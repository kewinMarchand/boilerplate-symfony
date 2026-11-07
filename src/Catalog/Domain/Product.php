<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\Image;

final readonly class Product
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public string $categorySlug,
        public Price $price,
        public Exposure $exposure,
        public Size $size,
        public bool $inStock,
        public ?Image $image,
    ) {
    }
}
