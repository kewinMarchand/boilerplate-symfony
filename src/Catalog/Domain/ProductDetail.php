<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * Fiche d'un produit : le produit, sa description et le chemin de sa catégorie, de la racine à elle (fil d'Ariane).
 */
final readonly class ProductDetail
{
    /**
     * @param list<CategoryLink> $categoryPath
     * @param list<string>       $description  paragraphes, vide si l'admin n'en a pas écrit
     */
    public function __construct(
        public Product $product,
        public array $categoryPath,
        public array $description,
    ) {
    }

    public function category(): ?CategoryLink
    {
        return [] === $this->categoryPath ? null : $this->categoryPath[array_key_last($this->categoryPath)];
    }
}
