<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Blog\Domain\ArticleDetail;
use App\Blog\Domain\ArticleLoadException;
use App\Blog\Domain\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ArticleControllerTest extends WebTestCase
{
    private const string ARTICLE = '/blog/un-carrousel-accessible-sans-dependance';

    public function testListsEveryArticleFromTheApi(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/blog');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(3, '[data-testid="blog-list"] [data-testid="blog-card"]');
        self::assertSame('/blog/le-mode-accessibilite-renforcee', $crawler->filter('[data-testid="blog-card"] a')->first()->attr('href'));
        self::assertSelectorExists('[data-testid="blog-card"] img[fetchpriority="high"]');
        self::assertSelectorCount(3, '[data-testid="blog-card"] h2');
    }

    public function testListShowsAFriendlyMessageWhenThereIsNoArticle(): void
    {
        $client = self::createClient();
        $client->request('GET', '/blog', server: ['HTTP_X_SCENARIO' => 'empty']);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="blog-empty"]');
        self::assertSelectorNotExists('[data-testid="blog-card"]');
    }

    public function testListShowsTheErrorWithARetryWhenTheApiIsDown(): void
    {
        $client = self::createClient();
        $client->request('GET', '/blog', server: ['HTTP_X_SCENARIO' => 'error']);

        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('[data-testid="blog-error"]', 'Impossible de charger les articles');
        self::assertSelectorExists('[data-testid="blog-retry"][href="/blog"]');
    }

    public function testShowsTheArticleReadFromTheApi(): void
    {
        $client = self::createClient();
        $client->request('GET', self::ARTICLE);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('[data-testid="article-title"]', 'Un carrousel accessible sans dépendance');
        self::assertSelectorExists('[data-testid="article-page"] time[datetime="2026-09-22"]');
        self::assertSelectorTextContains('[data-testid="article-body"] h2', 'Partir du défilement natif');
        self::assertSelectorCount(3, '[data-testid="article-body"] li');
        self::assertSelectorExists('[data-testid="article-page"] img[fetchpriority="high"]');
        self::assertSelectorTextSame('[data-testid="layout-breadcrumb"] a[href="/blog"]', 'Blog');
        self::assertSelectorTextContains('[data-testid="layout-breadcrumb"] [aria-current="page"]', 'Un carrousel accessible sans dépendance');
        self::assertSelectorTextSame('[data-testid="article-page"] a[href="/blog"]', 'Tous les articles');
        self::assertSelectorExists('meta[property="og:type"][content="article"]');
    }

    public function testLinksEachHomeCardToItsArticle(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        self::assertSame(self::ARTICLE, $crawler->filter('[data-testid="home-blog-card"] a')->eq(1)->attr('href'));
    }

    public function testAnswers404ForAnUnknownArticle(): void
    {
        $client = self::createClient(['debug' => false]);
        $client->request('GET', '/blog/article-inconnu');

        self::assertResponseStatusCodeSame(404);
    }

    public function testShowsTheErrorWhenTheApiIsDown(): void
    {
        $client = self::createClient();
        self::getContainer()->set(ArticleRepository::class, new class implements ArticleRepository {
            public function findLatest(int $limit): array
            {
                return [];
            }

            public function find(string $slug): ?ArticleDetail
            {
                throw new ArticleLoadException();
            }
        });
        $client->request('GET', self::ARTICLE);

        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('[data-testid="article-error"]', 'Impossible de charger cet article');
    }
}
