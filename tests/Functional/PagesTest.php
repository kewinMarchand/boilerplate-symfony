<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PagesTest extends WebTestCase
{
    private const string SITE = 'Boilerplate';

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function pages(): iterable
    {
        yield 'accueil' => ['/', self::SITE, self::SITE];
        yield 'catalogue' => ['/catalogue', 'Catalogue', 'Catalogue · '.self::SITE];
        yield 'sous-catégorie' => ['/catalogue/plantes-interieur/feuillages', 'Feuillages', 'Feuillages · Catalogue · '.self::SITE];
        yield 'tâches' => ['/taches', 'Tâches', 'Tâches · '.self::SITE];
        yield 'contact' => ['/contact', 'Contact', 'Contact · '.self::SITE];
        yield 'mentions légales' => ['/mentions-legales', 'Mentions légales', 'Mentions légales · '.self::SITE];
        yield 'données personnelles' => ['/donnees-personnelles', 'Données personnelles', 'Données personnelles · '.self::SITE];
        yield 'accessibilité' => ['/accessibilite', "Déclaration d'accessibilité", "Déclaration d'accessibilité · ".self::SITE];
        yield 'plan du site' => ['/plan-du-site', 'Plan du site', 'Plan du site · '.self::SITE];
    }

    #[DataProvider('pages')]
    public function testPageRendersWithSeoMetadata(string $path, string $heading, string $title): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', $heading);
        self::assertCount(1, $crawler->filter('h1'));
        self::assertPageTitleSame($title);
        self::assertSame('http://localhost'.$path, $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertSame($title, $crawler->filter('meta[property="og:title"]')->attr('content'));
        self::assertSame('http://localhost/og-image.jpg', $crawler->filter('meta[property="og:image"]')->attr('content'));
        $description = (string) $crawler->filter('meta[name="description"]')->attr('content');
        self::assertGreaterThanOrEqual(50, mb_strlen($description));
        self::assertLessThanOrEqual(160, mb_strlen($description));
        foreach ($crawler->filter('script[type="application/ld+json"]') as $script) {
            self::assertIsArray(json_decode((string) $script->textContent, true, flags: \JSON_THROW_ON_ERROR));
        }
    }

    public function testHomeHasOrganizationJsonLdAndNoBreadcrumb(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        $types = $crawler->filter('script[type="application/ld+json"]')->each(static function ($script): mixed {
            $data = json_decode($script->text(), true);

            return \is_array($data) ? $data['@type'] : null;
        });
        self::assertSame(['Organization', 'WebSite'], $types);
        self::assertSelectorNotExists('[data-testid="layout-breadcrumb"]');
        self::assertSame('page', $crawler->filter('[data-testid="layout-logo"]')->attr('aria-current'));
    }

    public function testInternalPageHasBreadcrumbMatchingItsJsonLd(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/contact');

        self::assertSelectorTextSame('[data-testid="layout-breadcrumb"] [aria-current="page"]', 'Contact');
        $jsonLd = json_decode($crawler->filter('script[type="application/ld+json"]')->last()->text(), true);
        self::assertIsArray($jsonLd);
        self::assertSame('BreadcrumbList', $jsonLd['@type']);
        self::assertIsArray($jsonLd['itemListElement']);
        self::assertCount(2, $jsonLd['itemListElement']);
    }

    public function testActiveNavigationLinkHasAriaCurrent(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/contact');

        $nav = $crawler->filter('nav[aria-label="Navigation principale"]');
        self::assertSame('page', $nav->selectLink('Contact')->attr('aria-current'));
        self::assertNull($nav->selectLink('Accueil')->attr('aria-current'));
    }

    public function testFooterListsLegalPagesWithComputedComplianceStatus(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        $links = $crawler->filter('nav[aria-label="Liens légaux"] a');
        self::assertCount(4, $links);
        self::assertSame('Accessibilité : non conforme', $links->eq(2)->text());
    }

    public function testAccessibilityStatementIsNonCompliantWithoutAudit(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/accessibilite');

        $compliance = $crawler->filter('[data-testid="legal-section"]')->first()->text();
        self::assertStringContainsString('non conforme', $compliance);
        self::assertStringContainsString("Aucun audit n'a encore été réalisé.", $compliance);
    }

    public function testRobotsPointsToSitemap(): void
    {
        $client = self::createClient();
        $client->request('GET', '/robots.txt');

        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        self::assertStringContainsString('Sitemap: http://localhost/sitemap.xml', (string) $client->getResponse()->getContent());
    }

    public function testSitemapListsEveryPageAndCategory(): void
    {
        $client = self::createClient();
        $client->request('GET', '/sitemap.xml');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        foreach (self::pages() as [$path]) {
            self::assertStringContainsString('<loc>http://localhost'.$path.'</loc>', $content);
        }
        self::assertStringContainsString('<loc>http://localhost/catalogue/plantes-exterieur/arbustes-a-fleurs/heliconia</loc>', $content);
        self::assertStringNotContainsString('charte-graphique', $content);
    }

    public function testManifestDescribesTheSite(): void
    {
        $client = self::createClient();
        $client->request('GET', '/manifest.webmanifest');

        self::assertResponseHeaderSame('Content-Type', 'application/manifest+json');
        $manifest = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($manifest);
        self::assertSame('fr', $manifest['lang']);
        self::assertIsArray($manifest['icons']);
        self::assertCount(2, $manifest['icons']);
    }
}
