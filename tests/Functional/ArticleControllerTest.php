<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Blog\Domain\Article;
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
            public function findLatest(int $limit, ?string $section = null, ?string $locale = null): array
            {
                return [];
            }

            public function sections(): array
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

    public function testCardsShowTheirSectionAboveTheTitle(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/blog');

        self::assertSame(['Actualités', 'Conseils', 'Conseils'], $crawler->filter('[data-testid="blog-card"] [data-testid="article-section"]')->each(static fn ($node): string => $node->text()));

        $client->request('GET', '/');
        self::assertSelectorCount(3, '[data-testid="home-blog-card"] [data-testid="article-section"]');
    }

    public function testFiltersTheListBySection(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/blog');

        $links = $crawler->filter('nav[aria-label="Rubriques"][data-testid="blog-sections"] [data-testid="blog-section-link"]');
        self::assertSame(['Toutes', 'Conseils', 'Actualités'], $links->each(static fn ($link): string => $link->text()));
        self::assertSame(['/blog', '/blog?rubrique=conseils', '/blog?rubrique=actualites'], $links->each(static fn ($link): ?string => $link->attr('href')));
        self::assertSame('page', $links->first()->attr('aria-current'));

        $crawler = $client->request('GET', '/blog?rubrique=conseils');
        self::assertSelectorCount(2, '[data-testid="blog-card"]');
        self::assertSame([null, 'page', null], $crawler->filter('[data-testid="blog-section-link"]')->each(static fn ($link): ?string => $link->attr('aria-current')));
    }

    public function testAnUnknownSectionListsNoArticle(): void
    {
        $client = self::createClient();
        $client->request('GET', '/blog?rubrique=inconnue');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="blog-empty"]', 'Aucun article dans cette rubrique');
        self::assertSelectorExists('[data-testid="blog-sections"]');
    }

    public function testWithoutSectionNoFilter(): void
    {
        $client = self::createClient();
        $client->request('GET', '/blog', server: ['HTTP_X_SCENARIO' => 'empty']);

        self::assertSelectorNotExists('[data-testid="blog-sections"]');
    }

    public function testTheBlogDescriptionComesFromTheAdmin(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/blog');

        self::assertPageTitleSame('Blog · Boilerplate');
        self::assertStringStartsWith('Conseils et actualités du modèle', (string) $crawler->filter('meta[name="description"]')->attr('content'));
    }

    public function testTheBlogTitleEnteredInTheAdminIsServedAsIs(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/blog', server: ['HTTP_X_SCENARIO' => 'metas']);

        self::assertPageTitleSame('Le blog du modèle : rendu serveur et accessibilité');
        self::assertSame('Le blog du modèle : rendu serveur et accessibilité', $crawler->filter('meta[property="og:title"]')->attr('content'));
        self::assertSelectorTextSame('h1', 'Blog');
    }

    public function testShowsTheSectionAndTheAudioPlayer(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', self::ARTICLE);

        self::assertSelectorTextSame('[data-testid="article-page"] [data-testid="article-section"]', 'Rubrique : Conseils');
        self::assertSelectorNotExists('[data-testid="article-format"]');
        $audio = $crawler->filter('audio[data-testid="article-audio"]');
        self::assertSame(['', 'none'], [$audio->attr('controls'), $audio->attr('preload')]);
        self::assertNull($audio->attr('autoplay'));
        self::assertStringEndsWith('.mp3', (string) $audio->filter('source')->attr('src'));
        self::assertSelectorTextSame('[data-testid="article-audio-duration"]', '4 s');
        self::assertSelectorTextSame('[data-testid="article-audio-speaker"]', 'Présenté par Hina Teriierooiterai');
    }

    public function testTheArticleMetadataEnteredInTheAdminReplaceTheComputedOnes(): void
    {
        $client = self::createClient();
        $client->request('GET', self::ARTICLE, server: ['HTTP_X_SCENARIO' => 'metas']);

        self::assertPageTitleSame('Titre saisi dans l’admin');
        self::assertSelectorTextSame('[data-testid="article-title"]', 'Un carrousel accessible sans dépendance');
    }

    public function testWithoutSectionFormatNorAudioTheArticleShowsNone(): void
    {
        $client = self::createClient();
        self::getContainer()->set(ArticleRepository::class, new class implements ArticleRepository {
            public function findLatest(int $limit, ?string $section = null, ?string $locale = null): array
            {
                return [];
            }

            public function sections(): array
            {
                return [];
            }

            public function find(string $slug): ArticleDetail
            {
                return new ArticleDetail(new Article('1', $slug, 'Sans rubrique', 'Extrait', new \DateTimeImmutable('2026-10-01'), null, null, null, null, ''), '<p>Texte</p>', '', '');
            }
        });
        $client->request('GET', '/blog/sans-rubrique');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-testid="article-section"]');
        self::assertSelectorNotExists('[data-testid="article-format"]');
        self::assertSelectorNotExists('[data-testid="article-audio"]');
    }
}
