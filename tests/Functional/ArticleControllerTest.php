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
        self::assertSelectorTextContains('[data-testid="layout-breadcrumb"]', 'Un carrousel accessible sans dépendance');
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
