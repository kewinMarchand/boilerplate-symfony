<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

interface CatalogRepository
{
    public function categoryTree(): CategoryTree;

    /**
     * @throws CatalogLoadException
     */
    public function search(Category $category, CatalogQuery $query): CatalogResult;

    /**
     * Null si l'API ne connaît pas le produit (404).
     *
     * @throws ProductLoadException
     */
    public function findProduct(string $slug): ?ProductDetail;

    /**
     * Produits « À la une » : le carrousel de l'accueil.
     *
     * @return list<Product>
     *
     * @throws FeaturedProductsLoadException
     */
    public function featured(): array;

    /**
     * Autres produits de la catégorie du produit, sans lui : le carrousel de la fiche.
     *
     * @return list<Product>
     *
     * @throws CatalogLoadException
     */
    public function similar(Product $product, int $limit): array;

    /**
     * Slugs de tous les produits, page après page (sitemap).
     *
     * @return list<string>
     *
     * @throws CatalogLoadException
     */
    public function productSlugs(): array;
}
