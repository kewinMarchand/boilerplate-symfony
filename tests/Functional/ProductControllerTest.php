<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProductControllerTest extends WebTestCase
{
    private const string PRODUCT = '/produit/monstera-deliciosa';

    public function testShowsTheProductReadFromTheApi(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', self::PRODUCT);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('[data-testid="product-title"]', 'Monstera deliciosa');
        self::assertSelectorTextContains('[data-testid="product-price"]', '34,90');
        self::assertSelectorExists('[data-testid="product-page"] img[fetchpriority="high"]');
        self::assertSelectorExists('[data-testid="layout-breadcrumb"] a[href="/catalogue/plantes-interieur/feuillages/monstera"]');
        $jsonLd = $crawler->filter('script[type="application/ld+json"]')->each(static fn ($node): string => $node->text());
        self::assertTrue((bool) array_filter($jsonLd, static fn (string $json): bool => str_contains($json, '"@type":"Product"') && str_contains($json, '"price":"34.90"')));
    }

    public function testLinksEachCatalogCardToItsProduct(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/catalogue');

        $link = $crawler->filter('[data-testid="catalog-product-link"]')->first();
        self::assertSame('/produit/monstera-deliciosa', $link->attr('href'));
        // La liste vit dans un cadre Turbo : le lien en sort pour ouvrir la fiche en page entière
        self::assertSame('_top', $link->attr('data-turbo-frame'));
    }

    public function testAnswers404ForAnUnknownProduct(): void
    {
        $client = self::createClient(['debug' => false]);
        $client->request('GET', '/produit/produit-inconnu');

        self::assertResponseStatusCodeSame(404);
    }

    public function testShowsTheErrorWhenTheApiIsDown(): void
    {
        $client = self::createClient();
        $client->request('GET', self::PRODUCT, server: ['HTTP_X_SCENARIO' => 'error']);

        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('[data-testid="product-error"]', 'Impossible de charger ce produit pour le moment.');
    }
}
