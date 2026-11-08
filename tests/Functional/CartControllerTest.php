<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CartControllerTest extends WebTestCase
{
    public function testServesTheCartPageShellWithoutIndexingIt(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/panier');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('main h1', 'Panier');
        self::assertSame('noindex, follow', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertSelectorTextContains('[data-testid="layout-breadcrumb"] [aria-current="page"]', 'Panier');
        self::assertStringContainsString('<noscript><p>Le panier demande JavaScript.</p></noscript>', (string) $client->getResponse()->getContent());
        self::assertSelectorExists('[data-controller="cart"]');
    }

    public function testLinksTheHeaderToTheCart(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertSelectorExists('header [data-testid="cart-link"][href="/panier"]');
        self::assertSelectorNotExists('[data-testid="cart-count"]');
    }

    public function testStaysOutOfTheSitemap(): void
    {
        $client = self::createClient();
        $client->request('GET', '/sitemap.xml');

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('/panier', (string) $client->getResponse()->getContent());
    }
}
