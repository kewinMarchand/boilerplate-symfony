<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

enum ProductSort: string
{
    case Relevance = 'pertinence';
    case PriceAscending = 'prix-asc';
    case PriceDescending = 'prix-desc';
    case Name = 'nom';

    public function byPrice(): bool
    {
        return self::PriceAscending === $this || self::PriceDescending === $this;
    }

    public function label(): string
    {
        return match ($this) {
            self::Relevance => 'Pertinence',
            self::PriceAscending => 'Prix croissant',
            self::PriceDescending => 'Prix décroissant',
            self::Name => 'Nom',
        };
    }
}
