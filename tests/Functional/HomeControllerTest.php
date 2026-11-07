<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Blog\Domain\ArticleDetail;
use App\Blog\Domain\ArticleRepository;
use App\Blog\Domain\ArticlesLoadException;
use App\Content\Domain\SlideRepository;
use App\Content\Domain\SlidesLoadException;
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
        self::assertSelectorTextContains('[data-testid="carousel-slide"]', 'Heliconia');
        self::assertStringStartsWith('http://localhost:8090/media/', (string) $crawler->filter('[data-testid="carousel-slide"] img')->attr('src'));
        self::assertSelectorNotExists('[data-testid="home-carousel-error"]');
        self::assertSelectorCount(3, '[data-testid="home-blog-card"]');
        self::assertSelectorCount(3, '[data-testid="home-blog-card"] time[datetime]');
    }

    public function testShowsAFriendlyMessageWhenThereIsNoArticle(): void
    {
        $client = self::createClient();
        self::getContainer()->set(ArticleRepository::class, new class implements ArticleRepository {
            public function findLatest(int $limit): array
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

    public function testShowsTheErrorWhenArticlesCannotBeLoaded(): void
    {
        $client = self::createClient();
        self::getContainer()->set(ArticleRepository::class, new class implements ArticleRepository {
            public function findLatest(int $limit): array
            {
                throw new ArticlesLoadException();
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

    public function testHidesTheCarouselWhenThereIsNoSlide(): void
    {
        $client = self::createClient();
        self::getContainer()->set(SlideRepository::class, new class implements SlideRepository {
            public function findAll(): array
            {
                return [];
            }
        });
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-testid="home-carousel"]');
        self::assertSelectorNotExists('[data-testid="home-carousel-error"]');
        self::assertSelectorCount(3, '[data-testid="home-blog-card"]');
    }

    public function testShowsTheErrorWhenSlidesCannotBeLoaded(): void
    {
        $client = self::createClient();
        self::getContainer()->set(SlideRepository::class, new class implements SlideRepository {
            public function findAll(): array
            {
                throw new SlidesLoadException();
            }
        });
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="home-carousel-error"]', 'Impossible de charger le carrousel pour le moment.');
        self::assertSelectorNotExists('[data-testid="home-carousel"]');
        self::assertSelectorCount(3, '[data-testid="home-blog-card"]');
    }
}
