<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use App\Catalog\Domain\AttributeFacet;
use App\Catalog\Domain\AttributeFacetValue;
use App\Catalog\Domain\CatalogQuery;
use App\Catalog\Domain\CatalogResult;
use App\Catalog\Domain\Category;
use App\Catalog\Domain\CategoryLink;
use App\Catalog\Domain\CategoryTree;
use App\Catalog\Domain\Exposure;
use App\Catalog\Domain\FacetCounts;
use App\Catalog\Domain\Price;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductAttribute;
use App\Catalog\Domain\ProductDetail;
use App\Catalog\Domain\ProductPage;
use App\Catalog\Domain\ProductSort;
use App\Catalog\Domain\Size;
use App\Shared\Infrastructure\Http\ApiPayload;
use App\Shared\Infrastructure\Http\InvalidApiPayloadException;

/**
 * Traduction entre le langage de l'API et le modèle du catalogue, dans les deux sens : paramètres de
 * recherche (centimes, `exposure[]`, `attributes[<code>][]`, `order[price]`) et réponses (arbre, page de produits, facettes).
 */
final class CatalogTranslation
{
    public static function tree(mixed $payload): CategoryTree
    {
        return new CategoryTree(new Category('', 'Catalogue', self::categories($payload, 'categories')));
    }

    /**
     * Query string de GET /api/product-facets : les filtres, en centimes et en clés répétées à la manière de l'API (`exposure[]=…`).
     */
    public static function facetsQuery(Category $category, CatalogQuery $query): string
    {
        return self::queryString(self::filterPairs($category, $query));
    }

    /**
     * Query string de GET /api/products : les mêmes filtres, plus le tri et la page.
     */
    public static function searchQuery(Category $category, CatalogQuery $query): string
    {
        return self::queryString([
            ...self::filterPairs($category, $query),
            ...match ($query->sort) {
                ProductSort::Relevance => [],
                ProductSort::PriceAscending => [['order[price]', 'asc']],
                ProductSort::PriceDescending => [['order[price]', 'desc']],
                ProductSort::Name => [['order[name]', 'asc']],
            },
            ['page', (string) $query->page],
            ['itemsPerPage', (string) CatalogQuery::PER_PAGE],
        ]);
    }

    /**
     * Nombre de pages calculé depuis le total des facettes, au moins une.
     */
    public static function result(mixed $products, mixed $facets, Category $category, int $page): CatalogResult
    {
        $counts = ApiPayload::object($facets, 'facets');
        $total = ApiPayload::int($counts, 'totalItems');

        return new CatalogResult(
            new ProductPage(
                self::products($products),
                $total,
                $page,
                max(1, (int) ceil($total / CatalogQuery::PER_PAGE)),
            ),
            new FacetCounts(
                self::counts(array_map(static fn (Exposure $case): string => $case->value, Exposure::cases()), $counts['exposure'] ?? null, 'value'),
                self::counts(array_map(static fn (Size $case): string => $case->value, Size::cases()), $counts['size'] ?? null, 'value'),
                self::counts(array_map(static fn (Category $child): string => $child->slug, $category->children), $counts['category'] ?? null, 'slug'),
                self::attributeFacets($counts['attributes'] ?? []),
            ),
        );
    }

    /**
     * Fiche d'un produit : chaque étape de `categoryPath` reçoit la valeur du paramètre d'URL du catalogue,
     * et la description est découpée en paragraphes aux lignes vides, comme le contrat les sépare.
     */
    public static function detail(mixed $payload): ProductDetail
    {
        $data = ApiPayload::object($payload, 'product');
        $slugs = [];
        $path = [];
        foreach (ApiPayload::objects($data['categoryPath'] ?? null, 'categoryPath') as $step) {
            $slugs[] = ApiPayload::string($step, 'slug');
            $path[] = new CategoryLink(ApiPayload::string($step, 'slug'), ApiPayload::string($step, 'name'), implode('/', $slugs));
        }

        $paragraphs = array_map(trim(...), preg_split('/\n\s*\n/', ApiPayload::string($data, 'description')) ?: []);

        return new ProductDetail(
            self::product($data),
            $path,
            array_values(array_filter($paragraphs, static fn (string $paragraph): bool => '' !== $paragraph)),
            ApiPayload::images($data, 'gallery'),
            ApiPayload::string($data, 'metaTitle'),
            ApiPayload::string($data, 'metaDescription'),
            ApiPayload::alternates($data),
        );
    }

    /**
     * @return list<Product>
     */
    public static function products(mixed $payload): array
    {
        return array_map(self::product(...), ApiPayload::objects($payload, 'products'));
    }

    /**
     * @return list<array{string, string}>
     */
    private static function filterPairs(Category $category, CatalogQuery $query): array
    {
        $filters = $query->filters;

        return [
            ...('' !== $category->slug ? [['category', $category->slug]] : []),
            ...array_map(static fn (Exposure $exposure): array => ['exposure[]', $exposure->value], $filters->exposures),
            ...array_map(static fn (Size $size): array => ['size[]', $size->value], $filters->sizes),
            ...array_merge(...array_map(
                static fn (string $code, array $values): array => array_map(static fn (string $value): array => ['attributes['.$code.'][]', $value], $values),
                array_keys($filters->attributes),
                $filters->attributes,
            )),
            ...(null !== $filters->priceMin ? [['priceMin', (string) $filters->priceMin->cents]] : []),
            ...(null !== $filters->priceMax ? [['priceMax', (string) $filters->priceMax->cents]] : []),
            ...($filters->inStockOnly ? [['inStock', 'true']] : []),
        ];
    }

    /**
     * @param list<array{string, string}> $pairs
     */
    private static function queryString(array $pairs): string
    {
        return implode('&', array_map(static fn (array $pair): string => rawurlencode($pair[0]).'='.rawurlencode($pair[1]), $pairs));
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
                ApiPayload::alternates($category),
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
            array_map(
                static fn (array $attribute): ProductAttribute => new ProductAttribute(
                    ApiPayload::string($attribute, 'code'),
                    ApiPayload::string($attribute, 'label'),
                    ApiPayload::string($attribute, 'valueLabel'),
                    null === ($attribute['unit'] ?? null) ? null : ApiPayload::string($attribute, 'unit'),
                ),
                ApiPayload::objects($product['attributes'] ?? null, 'attributes'),
            ),
        );
    }

    /**
     * @return list<AttributeFacet>
     */
    private static function attributeFacets(mixed $facets): array
    {
        return array_map(
            static fn (array $facet): AttributeFacet => new AttributeFacet(
                ApiPayload::string($facet, 'code'),
                ApiPayload::string($facet, 'label'),
                array_map(
                    static fn (array $entry): AttributeFacetValue => new AttributeFacetValue(ApiPayload::string($entry, 'value'), ApiPayload::string($entry, 'label'), ApiPayload::int($entry, 'count')),
                    ApiPayload::objects($facet['values'] ?? null, 'attributes.values'),
                ),
            ),
            ApiPayload::objects($facets, 'attributes'),
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
