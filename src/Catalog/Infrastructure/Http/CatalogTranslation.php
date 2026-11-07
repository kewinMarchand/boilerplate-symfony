<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use App\Catalog\Domain\CatalogQuery;
use App\Catalog\Domain\CatalogResult;
use App\Catalog\Domain\Category;
use App\Catalog\Domain\CategoryTree;
use App\Catalog\Domain\Exposure;
use App\Catalog\Domain\FacetCounts;
use App\Catalog\Domain\Price;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductPage;
use App\Catalog\Domain\ProductSort;
use App\Catalog\Domain\Size;
use App\Shared\Infrastructure\Http\ApiPayload;
use App\Shared\Infrastructure\Http\InvalidApiPayloadException;

/**
 * Traduction entre le langage de l'API et le modèle du catalogue, dans les deux sens : paramètres de
 * recherche (centimes, `exposure[]`, `order[price]`) et réponses (arbre, page de produits, facettes).
 */
final class CatalogTranslation
{
    public static function tree(mixed $payload): CategoryTree
    {
        return new CategoryTree(new Category('', 'Catalogue', self::categories($payload, 'categories')));
    }

    /**
     * Query string de GET /api/products, clés répétées à la manière de l'API (`exposure[]=…`).
     */
    public static function searchQuery(Category $category, CatalogQuery $query): string
    {
        $filters = $query->filters;
        $pairs = [
            ...('' !== $category->slug ? [['category', $category->slug]] : []),
            ...array_map(static fn (Exposure $exposure): array => ['exposure[]', $exposure->value], $filters->exposures),
            ...array_map(static fn (Size $size): array => ['size[]', $size->value], $filters->sizes),
            ...(null !== $filters->priceMin ? [['priceMin', (string) $filters->priceMin->cents]] : []),
            ...(null !== $filters->priceMax ? [['priceMax', (string) $filters->priceMax->cents]] : []),
            ...($filters->inStockOnly ? [['inStock', 'true']] : []),
            ...match ($query->sort) {
                ProductSort::Relevance => [],
                ProductSort::PriceAscending => [['order[price]', 'asc']],
                ProductSort::PriceDescending => [['order[price]', 'desc']],
                ProductSort::Name => [['order[name]', 'asc']],
            },
            ['page', (string) $query->page],
            ['itemsPerPage', (string) CatalogQuery::PER_PAGE],
        ];

        return implode('&', array_map(static fn (array $pair): string => rawurlencode($pair[0]).'='.rawurlencode($pair[1]), $pairs));
    }

    public static function result(mixed $payload, Category $category, int $page): CatalogResult
    {
        $data = ApiPayload::object($payload, 'products');
        $facets = ApiPayload::object($data['facets'] ?? null, 'facets');

        return new CatalogResult(
            new ProductPage(
                array_map(self::product(...), ApiPayload::objects($data['items'] ?? null, 'items')),
                ApiPayload::int($data, 'totalItems'),
                $page,
                max(1, ApiPayload::int($data, 'totalPages')),
            ),
            new FacetCounts(
                self::counts(array_map(static fn (Exposure $case): string => $case->value, Exposure::cases()), $facets['exposure'] ?? null, 'value'),
                self::counts(array_map(static fn (Size $case): string => $case->value, Size::cases()), $facets['size'] ?? null, 'value'),
                self::counts(array_map(static fn (Category $child): string => $child->slug, $category->children), $facets['category'] ?? null, 'slug'),
            ),
        );
    }

    /**
     * @return list<Category>
     */
    private static function categories(mixed $payload, string $path): array
    {
        return array_map(
            static fn (array $category): Category => new Category(
                ApiPayload::string($category, 'slug'),
                ApiPayload::string($category, 'name'),
                self::categories($category['children'] ?? [], $path.'.children'),
            ),
            ApiPayload::objects($payload, $path),
        );
    }

    /**
     * @param array<string, mixed> $product
     */
    private static function product(array $product): Product
    {
        $exposure = Exposure::tryFrom(ApiPayload::string($product, 'exposure')) ?? throw new InvalidApiPayloadException('exposure', 'soleil, mi-ombre ou ombre');
        $size = Size::tryFrom(ApiPayload::string($product, 'size')) ?? throw new InvalidApiPayloadException('size', 'S, M ou L');

        return new Product(
            ApiPayload::id($product, 'id'),
            ApiPayload::string($product, 'slug'),
            ApiPayload::string($product, 'name'),
            ApiPayload::string($product, 'categorySlug'),
            Price::fromCents(ApiPayload::int($product, 'price')),
            $exposure,
            $size,
            ApiPayload::bool($product, 'inStock'),
            ApiPayload::image($product, 'image'),
        );
    }

    /**
     * Compteurs par valeur, à zéro pour toute valeur attendue que l'API n'a pas citée.
     *
     * @param list<string> $expected
     *
     * @return array<string, int>
     */
    private static function counts(array $expected, mixed $facet, string $key): array
    {
        $counts = array_fill_keys($expected, 0);
        foreach (ApiPayload::objects($facet ?? [], 'facets') as $entry) {
            $counts[ApiPayload::string($entry, $key)] = ApiPayload::int($entry, 'count');
        }

        return $counts;
    }
}
