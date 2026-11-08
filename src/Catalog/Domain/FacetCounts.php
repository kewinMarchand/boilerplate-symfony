<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

final readonly class FacetCounts
{
    /**
     * @param array<string, int>   $exposures  nombre de produits par valeur d'exposition
     * @param array<string, int>   $sizes      nombre de produits par taille
     * @param array<string, int>   $categories nombre de produits par sous-catégorie directe
     * @param list<AttributeFacet> $attributes une facette par attribut à choix filtrable de la branche
     */
    public function __construct(
        public array $exposures,
        public array $sizes,
        public array $categories,
        public array $attributes = [],
    ) {
    }

    public function attribute(string $code): ?AttributeFacet
    {
        foreach ($this->attributes as $facet) {
            if ($facet->code === $code) {
                return $facet;
            }
        }

        return null;
    }
}
