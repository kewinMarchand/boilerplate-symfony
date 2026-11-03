<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use App\Catalog\Domain\CatalogLoadException;
use App\Catalog\Domain\CatalogQuery;
use App\Catalog\Domain\CatalogRepository;
use App\Catalog\Domain\CatalogResult;
use App\Catalog\Domain\Category;
use App\Catalog\Domain\CategoryTree;
use App\Catalog\Domain\InvalidPriceException;
use App\Shared\Infrastructure\Http\InvalidApiPayloadException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpCatalogRepository implements CatalogRepository
{
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
            $payload = $this->apiClient->request('GET', '/api/products?'.CatalogTranslation::searchQuery($category, $query))->toArray();

            return CatalogTranslation::result($payload, $category, $query->page);
        } catch (ExceptionInterface|InvalidApiPayloadException|InvalidPriceException $exception) {
            throw new CatalogLoadException($exception);
        }
    }
}
