<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SiteIdentityTest extends WebTestCase
{
    public function testTheNameAndTaglineComeFromTheApi(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        self::assertSelectorTextSame('[data-testid="layout-logo"]', 'Boilerplate');
        self::assertSelectorTextSame('[data-testid="layout-footer"] .footer__brand', 'Boilerplate');
        self::assertSelectorTextSame('h1', 'Boilerplate');
        self::assertSelectorTextContains('.hero__lead', 'Modèle de site avec boilerplates-ui');
        self::assertSame('Boilerplate', $crawler->filter('meta[property="og:site_name"]')->attr('content'));
        self::assertStringStartsWith('Modèle de site', (string) $crawler->filter('meta[name="description"]')->attr('content'));
        self::assertStringContainsString('"name":"Boilerplate"', $crawler->filter('script[type="application/ld+json"]')->text());
        self::assertSame('auto', $crawler->filter('html')->attr('data-color-scheme'));
    }

    public function testFallsBackOnTheSiteConfigWhenTheApiFails(): void
    {
        $client = self::createClient();
        $client->request('GET', '/contact', server: ['HTTP_X_SCENARIO' => 'error']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('[data-testid="layout-logo"]', 'Boilerplate Symfony');
        self::assertPageTitleSame('Contact · Boilerplate Symfony');
    }

    public function testWithoutLogoTheHeaderShowsThePictogramAndTheName(): void
    {
        $client = self::createClient();
        $client->request('GET', '/contact');

        self::assertSelectorNotExists('[data-testid="layout-logo-image"]');
        self::assertSelectorExists('[data-testid="layout-logo"] img[alt=""]');
    }

    public function testWithLogoTheHeaderShowsOnlyTheLogo(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/contact', server: ['HTTP_X_SCENARIO' => 'identite-logo']);

        $logo = $crawler->filter('[data-testid="layout-logo"] img');
        self::assertCount(1, $logo);
        self::assertSame('Oraki', $logo->attr('alt'));
        self::assertSame(['40', '133', 'high'], [$logo->attr('height'), $logo->attr('width'), $logo->attr('fetchpriority')]);
        self::assertSame('133px', $logo->attr('sizes'));
        self::assertSelectorNotExists('[data-testid="layout-logo"] span');
        self::assertPageTitleSame('Contact · Oraki');
    }

    public function testWithoutColorsNoStyleBlockNorNonce(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/contact');

        self::assertCount(0, $crawler->filter('head style'));
        self::assertCount(0, $crawler->filter('meta[name="csp-nonce"]'));
        self::assertStringNotContainsString('nonce-', (string) $client->getResponse()->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function testWithColorsTheStyleBlockIsAllowedByItsNonce(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/contact', server: ['HTTP_X_SCENARIO' => 'identite-couleurs']);

        $style = $crawler->filter('head style');
        $nonce = (string) $style->attr('nonce');
        self::assertStringContainsString('--bui-color-primary:#c2410c;', $style->text());
        self::assertSame($nonce, $crawler->filter('meta[name="csp-nonce"]')->attr('content'));
        self::assertStringContainsString("style-src 'self' 'unsafe-inline' 'nonce-".$nonce."';", (string) $client->getResponse()->headers->get('Content-Security-Policy-Report-Only'));
        self::assertSame('dark', $crawler->filter('html')->attr('data-color-scheme'));
    }

    public function testTheManifestTakesTheNameAndThePrimaryColor(): void
    {
        $client = self::createClient();
        $client->request('GET', '/manifest.webmanifest', server: ['HTTP_X_SCENARIO' => 'identite-couleurs']);

        $manifest = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);
        self::assertSame(['Oraki', 'Oraki', '#c2410c'], [$manifest['name'], $manifest['short_name'], $manifest['theme_color']]);
    }

    public function testWithoutIconNorShareImageTheSiteFilesAreServed(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/contact');

        self::assertSame('/favicon.ico', $crawler->filter('link[rel="icon"]')->first()->attr('href'));
        self::assertSame('/apple-touch-icon.png', $crawler->filter('link[rel="apple-touch-icon"]')->attr('href'));
        self::assertStringEndsWith('/og-image.jpg', (string) $crawler->filter('meta[property="og:image"]')->attr('content'));
    }

    public function testWithIconAndShareImageTheApiFilesAreServed(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/contact', server: ['HTTP_X_SCENARIO' => 'identite-icone']);

        $icons = $crawler->filter('link[rel="icon"]');
        self::assertCount(1, $icons);
        self::assertSame(['http://localhost:3232/media/icone-oraki-32.png', 'image/png', '32x32'], [$icons->attr('href'), $icons->attr('type'), $icons->attr('sizes')]);
        self::assertSame('http://localhost:3232/media/icone-oraki-180.png', $crawler->filter('link[rel="apple-touch-icon"]')->attr('href'));
        self::assertSame('http://localhost:3232/media/partage-oraki-1200.webp', $crawler->filter('meta[property="og:image"]')->attr('content'));
        self::assertSame('http://localhost:3232/media/partage-oraki-1200.webp', $crawler->filter('meta[name="twitter:image"]')->attr('content'));
        self::assertSame('Serre de plantes tropicales Oraki', $crawler->filter('meta[property="og:image:alt"]')->attr('content'));
    }

    public function testThePageImageWinsOverTheShareImage(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/produit/monstera-deliciosa', server: ['HTTP_X_SCENARIO' => 'identite-icone']);

        self::assertStringEndsWith('01272a8b6d3f8221cfe5a2a36aefcd35-800.webp', (string) $crawler->filter('meta[property="og:image"]')->attr('content'));
    }

    public function testTheManifestIconsComeFromTheApiWhenSet(): void
    {
        $client = self::createClient();
        $client->request('GET', '/manifest.webmanifest', server: ['HTTP_X_SCENARIO' => 'identite-icone']);
        $withIcon = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $client->request('GET', '/manifest.webmanifest');
        $withoutIcon = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertIsArray($withIcon);
        self::assertIsArray($withIcon['icons']);
        self::assertIsArray($withoutIcon);
        self::assertIsArray($withoutIcon['icons']);
        self::assertSame(['http://localhost:3232/media/icone-oraki-192.png', 'http://localhost:3232/media/icone-oraki-512.png'], array_column($withIcon['icons'], 'src'));
        self::assertSame(['/icon-192.png', '/icon-512.png'], array_column($withoutIcon['icons'], 'src'));
    }

    public function testTheOrganizationLogoIsTheApiIconWhenSet(): void
    {
        $client = self::createClient();
        $withIcon = $client->request('GET', '/', server: ['HTTP_X_SCENARIO' => 'identite-icone']);
        self::assertStringContainsString('"logo":"http://localhost:3232/media/icone-oraki-512.png"', $withIcon->filter('script[type="application/ld+json"]')->first()->text());

        $withoutIcon = $client->request('GET', '/');
        self::assertMatchesRegularExpression('#"logo":"https?://[^"]+/icon-512\.png"#', $withoutIcon->filter('script[type="application/ld+json"]')->first()->text());
    }
}
