<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use App\Catalog\Domain\CatalogLoadException;
use App\Catalog\Domain\CatalogQuery;
use App\Catalog\Domain\CatalogRepository;
use App\Catalog\Domain\CatalogResult;
use App\Catalog\Domain\Category;
use App\Catalog\Domain\CategoryTree;
use App\Catalog\Domain\FeaturedProductsLoadException;
use App\Catalog\Domain\InvalidPriceException;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductDetail;
use App\Catalog\Domain\ProductLoadException;
use App\Shared\Infrastructure\Http\ApiPayload;
use App\Shared\Infrastructure\Http\InvalidApiPayloadException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpCatalogRepository implements CatalogRepository
{
    /** Plus grande page acceptée par l'API. */
    private const int MAX_PAGE_SIZE = 48;

    private ?CategoryTree $tree = null;

    public function __construct(#[Target('api.client')] private readonly HttpClientInterface $apiClient)
    {
    }

    /**
     * L'arbre sert à chaque page (en-tête, menu mobile, fil d'Ariane) : un seul appel par requête.
     */
    public function categoryTree(): CategoryTree
    {
        try {
            return $this->tree ??= CatalogTranslation::tree($this->apiClient->request('GET', '/api/categories')->toArray());
        } catch (ExceptionInterface|InvalidApiPayloadException $exception) {
            throw new CatalogLoadException($exception);
        }
    }

    public function search(Category $category, CatalogQuery $query): CatalogResult
    {
        try {
            $products = $this->apiClient->request('GET', '/api/products?'.CatalogTranslation::searchQuery($category, $query));
            $facets = $this->apiClient->request('GET', '/api/product-facets?'.CatalogTranslation::facetsQuery($category, $query));

            return CatalogTranslation::result($products->toArray(), $facets->toArray(), $category, $query->page);
        } catch (ExceptionInterface|InvalidApiPayloadException|InvalidPriceException $exception) {
            throw new CatalogLoadException($exception);
        }
    }

    public function findProduct(string $slug): ?ProductDetail
    {
        try {
            $response = $this->apiClient->request('GET', '/api/products/'.rawurlencode($slug));
            if (Response::HTTP_NOT_FOUND === $response->getStatusCode()) {
                return null;
            }

            return CatalogTranslation::detail($response->toArray());
        } catch (ExceptionInterface|InvalidApiPayloadException|InvalidPriceException $exception) {
            throw new ProductLoadException($exception);
        }
    }

    public function featured(): array
    {
        try {
            return CatalogTranslation::products($this->apiClient->request('GET', '/api/featured-products')->toArray());
        } catch (ExceptionInterface|InvalidApiPayloadException|InvalidPriceException $exception) {
            throw new FeaturedProductsLoadException($exception);
        }
    }

    public function similar(Product $product, int $limit): array
    {
        try {
            $payload = $this->apiClient->request('GET', '/api/products?category='.rawurlencode($product->categorySlug).'&itemsPerPage='.($limit + 1))->toArray();
            $others = array_filter(
                CatalogTranslation::products($payload),
                static fn (Product $other): bool => $other->slug !== $product->slug,
            );

            return \array_slice(array_values($others), 0, $limit);
        } catch (ExceptionInterface|InvalidApiPayloadException|InvalidPriceException $exception) {
            throw new CatalogLoadException($exception);
        }
    }

    public function productSlugs(): array
    {
        $slugs = [];
        try {
            for ($page = 1;; ++$page) {
                $items = ApiPayload::objects($this->apiClient->request('GET', '/api/products?page='.$page.'&itemsPerPage='.self::MAX_PAGE_SIZE)->toArray(), 'products');
                foreach ($items as $item) {
                    $slugs[] = ApiPayload::string($item, 'slug');
                }
                if (\count($items) < self::MAX_PAGE_SIZE) {
                    return $slugs;
                }
            }
        } catch (ExceptionInterface|InvalidApiPayloadException $exception) {
            throw new CatalogLoadException($exception);
        }
    }
}
