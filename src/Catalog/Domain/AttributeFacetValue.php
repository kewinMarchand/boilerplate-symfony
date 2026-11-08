<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

final readonly class AttributeFacetValue
{
    public function __construct(
        public string $value,
        public string $label,
        public int $count,
    ) {
    }
}
