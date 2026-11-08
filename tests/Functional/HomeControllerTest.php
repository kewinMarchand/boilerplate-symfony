<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Blog\Domain\Article;
use App\Blog\Domain\ArticleDetail;
use App\Blog\Domain\ArticleRepository;
use App\Blog\Domain\ArticlesLoadException;
use App\Catalog\Domain\CatalogQuery;
use App\Catalog\Domain\CatalogRepository;
use App\Catalog\Domain\CatalogResult;
use App\Catalog\Domain\Category;
use App\Catalog\Domain\CategoryTree;
use App\Catalog\Domain\FeaturedProductsLoadException;
use App\Catalog\Domain\ProductDetail;
use App\Catalog\Infrastructure\Http\HttpCatalogRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testRendersHeroCarouselAndBlog(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        self::assertSelectorExists('[data-testid="home-hero"] img[fetchpriority="high"]');
        self::assertNull($crawler->filter('[data-testid="home-hero"] img')->attr('loading'));
        self::assertSelectorCount(5, '[data-testid="carousel-slide"]');
        self::assertSelectorTextContains('[data-testid="carousel-slide"]', 'Anthurium andreanum rouge');
        self::assertSame('/produit/anthurium-andreanum-rouge', $crawler->filter('[data-testid="carousel-slide-link"]')->attr('href'));
        self::assertStringStartsWith('http://localhost:8090/media/', (string) $crawler->filter('[data-testid="carousel-slide"] img')->attr('src'));
        self::assertSelectorNotExists('[data-testid="home-carousel-error"]');
        self::assertSelectorCount(3, '[data-testid="home-blog-card"]');
        self::assertSelectorCount(3, '[data-testid="home-blog-card"] time[datetime]');
    }

    public function testShowsAFriendlyMessageWhenThereIsNoArticle(): void
    {
        $client = self::createClient();
        self::getContainer()->set(ArticleRepository::class, new class implements ArticleRepository {
            public function findLatest(int $limit, ?string $section = null): array
            {
                return [];
            }

            public function sections(): array
            {
                return [];
            }

            public function find(string $slug): ?ArticleDetail
            {
                return null;
            }
        });
        $client->request('GET', '/');

        self::assertSelectorExists('[data-testid="home-blog-empty"]');
        self::assertSelectorNotExists('[data-testid="home-blog-card"]');
    }

    public function testAsksOnlyForTheThreeLatestArticles(): void
    {
        $client = self::createClient();
        $repository = new class implements ArticleRepository {
            public ?int $limit = null;

            public function findLatest(int $limit, ?string $section = null): array
            {
                $this->limit = $limit;

                return [];
            }

            public function sections(): array
            {
                return [];
            }

            public function find(string $slug): ?ArticleDetail
            {
                return null;
            }
        };
        self::getContainer()->set(ArticleRepository::class, $repository);
        $client->request('GET', '/');

        self::assertSame(3, $repository->limit);
    }

    public function testShowsTheErrorWhenArticlesCannotBeLoaded(): void
    {
        $client = self::createClient();
        self::getContainer()->set(ArticleRepository::class, new class implements ArticleRepository {
            public function findLatest(int $limit, ?string $section = null): array
            {
                throw new ArticlesLoadException();
            }

            public function sections(): array
            {
                return [];
            }

            public function find(string $slug): ?ArticleDetail
            {
                return null;
            }
        });
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="home-blog-error"]', 'Impossible de charger les articles');
    }

    public function testHidesTheCarouselWithoutFeaturedProduct(): void
    {
        $client = self::createClient();
        self::replaceFeatured(static fn (): array => []);
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-testid="home-carousel"]');
        self::assertSelectorNotExists('[data-testid="home-carousel-error"]');
        self::assertSelectorCount(3, '[data-testid="home-blog-card"]');
    }

    public function testShowsTheErrorWhenFeaturedProductsCannotBeLoaded(): void
    {
        $client = self::createClient();
        self::replaceFeatured(static fn (): array => throw new FeaturedProductsLoadException());
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="home-carousel-error"]', 'Impossible de charger le carrousel pour le moment.');
        self::assertSelectorNotExists('[data-testid="home-carousel"]');
        self::assertSelectorCount(3, '[data-testid="home-blog-card"]');
    }

    /**
     * Garde le dépôt réel (arbre de l'en-tête) et remplace seulement les produits à la une.
     *
     * @param \Closure(): list<\App\Catalog\Domain\Product> $featured
     */
    private static function replaceFeatured(\Closure $featured): void
    {
        // Une instance à part : le service du conteneur ne peut plus être remplacé une fois créé
        $real = new HttpCatalogRepository(self::getContainer()->get('api.client'));
        self::getContainer()->set(CatalogRepository::class, new readonly class($real, $featured) implements CatalogRepository {
            /**
             * @param \Closure(): list<\App\Catalog\Domain\Product> $featured
             */
            public function __construct(private CatalogRepository $real, private \Closure $featured)
            {
            }

            public function categoryTree(): CategoryTree
            {
                return $this->real->categoryTree();
            }

            public function search(Category $category, CatalogQuery $query): CatalogResult
            {
                return $this->real->search($category, $query);
            }

            public function findProduct(string $slug): ?ProductDetail
            {
                return $this->real->findProduct($slug);
            }

            public function featured(): array
            {
                return ($this->featured)();
            }

            public function similar(\App\Catalog\Domain\Product $product, int $limit): array
            {
                return $this->real->similar($product, $limit);
            }

            public function productSlugs(): array
            {
                return $this->real->productSlugs();
            }
        });
    }

    public function testACardWithoutSectionHasNoOvertitle(): void
    {
        $client = self::createClient();
        self::getContainer()->set(ArticleRepository::class, new class implements ArticleRepository {
            public function findLatest(int $limit, ?string $section = null): array
            {
                return [new Article('1', 'sans-rubrique', 'Sans rubrique', 'Extrait', new \DateTimeImmutable('2026-10-01'), null, null, null, null, '')];
            }

            public function sections(): array
            {
                return [];
            }

            public function find(string $slug): ?ArticleDetail
            {
                return null;
            }
        });
        $client->request('GET', '/');

        self::assertSelectorCount(1, '[data-testid="home-blog-card"]');
        self::assertSelectorNotExists('[data-testid="article-section"]');
    }
}
