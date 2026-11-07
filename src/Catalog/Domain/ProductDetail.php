<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * Fiche d'un produit : le produit et le chemin de sa catégorie, de la racine à elle (fil d'Ariane).
 */
final readonly class ProductDetail
{
    /**
     * @param list<CategoryLink> $categoryPath
     */
    public function __construct(
        public Product $product,
        public array $categoryPath,
    ) {
    }

    public function category(): ?CategoryLink
    {
        return [] === $this->categoryPath ? null : $this->categoryPath[array_key_last($this->categoryPath)];
    }
}
