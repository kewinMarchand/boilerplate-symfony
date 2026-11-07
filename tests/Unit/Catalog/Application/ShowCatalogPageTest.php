<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application;

use App\Catalog\Application\ShowCatalogPage;
use App\Catalog\Domain\CatalogLoadException;
use App\Catalog\Domain\CatalogQuery;
use App\Catalog\Domain\CatalogRepository;
use App\Catalog\Domain\CatalogResult;
use App\Catalog\Domain\Category;
use App\Catalog\Domain\CategoryTree;
use App\Catalog\Domain\FacetCounts;
use App\Catalog\Domain\ProductFilters;
use App\Catalog\Domain\ProductPage;
use App\Catalog\Domain\ProductSort;
use PHPUnit\Framework\TestCase;

final class ShowCatalogPageTest extends TestCase
{
    private static function repository(?\Throwable $failure = null, int $pageCount = 2, ?\Throwable $treeFailure = null): CatalogRepository
    {
        return new readonly class($failure, $pageCount, $treeFailure) implements CatalogRepository {
            public function __construct(private ?\Throwable $failure, private int $pageCount, private ?\Throwable $treeFailure)
            {
            }

            public function categoryTree(): CategoryTree
            {
                if (null !== $this->treeFailure) {
                    throw $this->treeFailure;
                }

                return new CategoryTree(new Category('', 'Catalogue', [new Category('palmiers', 'Palmiers')]));
            }

            public function search(Category $category, CatalogQuery $query): CatalogResult
            {
                if (null !== $this->failure) {
                    throw $this->failure;
                }

                return new CatalogResult(new ProductPage([], 24, $query->page, $this->pageCount), new FacetCounts([], [], []));
            }

            public function findProduct(string $slug): ?\App\Catalog\Domain\ProductDetail
            {
                return null;
            }

            public function featured(): array
            {
                return [];
            }

            public function productSlugs(): array
            {
                return [];
            }
        };
    }

    private static function query(int $page = 1): CatalogQuery
    {
        return new CatalogQuery(new ProductFilters(), ProductSort::Relevance, $page);
    }

    public function testLoadsThePageOfAKnownCategory(): void
    {
        $page = (new ShowCatalogPage(self::repository()))('palmiers', self::query());

        self::assertNotNull($page);
        self::assertSame('Palmiers', $page->categoryPath->category()->name);
        self::assertSame(24, $page->result?->page->total);
        self::assertNull($page->errorMessage);
    }

    public function testUnknownCategoryIsNotFound(): void
    {
        self::assertNull((new ShowCatalogPage(self::repository()))('inconnu', self::query()));
    }

    public function testPageBeyondTheLastOneIsNotFound(): void
    {
        $showCatalogPage = new ShowCatalogPage(self::repository(pageCount: 2));

        self::assertNotNull($showCatalogPage('', self::query(2)));
        self::assertNull($showCatalogPage('', self::query(3)));
    }

    public function testLoadingFailureGivesAPageWithTheBusinessMessage(): void
    {
        $page = (new ShowCatalogPage(self::repository(new CatalogLoadException())))('palmiers', self::query());

        self::assertNotNull($page);
        self::assertNull($page->result);
        self::assertSame('Impossible de charger les produits pour le moment.', $page->errorMessage);
    }

    public function testCategoryTreeFailureGivesAPageInError(): void
    {
        $page = (new ShowCatalogPage(self::repository(treeFailure: new CatalogLoadException())))('palmiers', self::query());

        self::assertNotNull($page);
        self::assertTrue($page->categoryPath->isRoot());
        self::assertSame('Impossible de charger les produits pour le moment.', $page->errorMessage);
    }
}
