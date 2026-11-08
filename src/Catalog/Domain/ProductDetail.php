<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\Image;

/**
 * Fiche d'un produit : le produit, sa description, le chemin de sa catégorie, de la racine à elle (fil d'Ariane), les
 * images de sa galerie et ses métadonnées saisies (chaînes vides si non saisies).
 */
final readonly class ProductDetail
{
    /**
     * @param list<CategoryLink> $categoryPath
     * @param list<string>       $description  paragraphes, vide si l'admin n'en a pas écrit
     * @param list<Image>        $gallery      images en plus de l'image principale, dans l'ordre
     */
    public function __construct(
        public Product $product,
        public array $categoryPath,
        public array $description,
        public array $gallery,
        public string $metaTitle,
        public string $metaDescription,
    ) {
    }

    public function category(): ?CategoryLink
    {
        return [] === $this->categoryPath ? null : $this->categoryPath[array_key_last($this->categoryPath)];
    }
}
