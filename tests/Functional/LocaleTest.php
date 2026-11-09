<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Langues du site (section 29 bis de la spec commune) : un site monolingue n'a ni préfixe, ni sélecteur, ni hreflang ; un site
 * bilingue (`X-Scenario: bilingual`, identité en français et en anglais) sert l'anglais sous /en.
 */
final class LocaleTest extends WebTestCase
{
    private const array BILINGUAL = ['HTTP_X_SCENARIO' => 'bilingual'];

    public function testMonolingualSiteHasNoLanguageSwitcherNorHreflang(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/contact');

        self::assertResponseIsSuccessful();
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertSame('fr_FR', $crawler->filter('meta[property="og:locale"]')->attr('content'));
        self::assertCount(0, $crawler->filter('[data-testid="layout-languages"]'));
        self::assertCount(0, $crawler->filter('link[hreflang]'));
        self::assertCount(0, $crawler->filter('meta[property="og:locale:alternate"]'));
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function unservedPrefixes(): iterable
    {
        yield 'langue non activée d’un site monolingue' => ['/en/contact', []];
        yield 'langue par défaut' => ['/fr/contact', self::BILINGUAL];
        yield 'langue non activée' => ['/de/contact', self::BILINGUAL];
        yield 'sitemap sous un préfixe' => ['/en/sitemap.xml', self::BILINGUAL];
    }

    /**
     * @param array<string, string> $server
     */
    #[DataProvider('unservedPrefixes')]
    public function testUnservedPrefixIsNotFound(string $path, array $server): void
    {
        $client = self::createClient(['debug' => false]);
        $client->request('GET', $path, server: $server);

        self::assertResponseStatusCodeSame(404);
    }

    public function testEnglishPageIsPrefixedTranslatedAndLinkedToItsFrenchVersion(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/en/contact', server: self::BILINGUAL);

        self::assertResponseIsSuccessful();
        self::assertSame('en', $crawler->filter('html')->attr('lang'));
        self::assertSelectorTextSame('h1', 'Contact');
        self::assertSelectorTextContains('[data-testid="contact-submit"]', 'Send');
        self::assertSame('http://localhost/en/contact', $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertSame('/en', $crawler->filter('[data-testid="layout-logo"]')->attr('href'));
        self::assertSame(
            ['fr' => 'http://localhost/contact', 'en' => 'http://localhost/en/contact', 'x-default' => 'http://localhost/contact'],
            array_combine(
                $crawler->filter('link[hreflang]')->each(static fn ($link): string => (string) $link->attr('hreflang')),
                $crawler->filter('link[hreflang]')->each(static fn ($link): string => (string) $link->attr('href')),
            ),
        );
        self::assertSame('en_US', $crawler->filter('meta[property="og:locale"]')->attr('content'));
        self::assertSame('fr_FR', $crawler->filter('meta[property="og:locale:alternate"]')->attr('content'));

        $french = $crawler->filter('[data-testid="layout-language-fr"]');
        self::assertSame(['Français', '/contact', 'fr', 'fr', null], [$french->text(), $french->attr('href'), $french->attr('lang'), $french->attr('hreflang'), $french->attr('aria-current')]);
        self::assertSame('true', $crawler->filter('[data-testid="layout-language-en"]')->attr('aria-current'));
    }

    public function testEnglishContactValidatesInEnglish(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/en/contact', server: self::BILINGUAL);
        $client->submit($crawler->filter('#contact-form')->form(['contact[name]' => 'Ada', 'contact[email]' => 'ada', 'contact[message]' => 'Hello there, this is long enough.']), [], self::BILINGUAL);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.bui-field__error', 'Enter a valid email address');
    }

    public function testFrenchPageOfABilingualSiteKeepsItsPath(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/contact', server: self::BILINGUAL);

        self::assertResponseIsSuccessful();
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertSame('/en/contact', $crawler->filter('[data-testid="layout-language-en"]')->attr('href'));
        self::assertSame('English', $crawler->filter('[data-testid="layout-language-en"]')->text());
    }

    public function testBilingualSitemapListsEachAddressInEachLanguage(): void
    {
        $client = self::createClient();
        $client->request('GET', '/sitemap.xml', server: self::BILINGUAL);
        $sitemap = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $sitemap);
        self::assertStringContainsString('<loc>http://localhost/en/contact</loc>', $sitemap);
        self::assertStringContainsString('hreflang="x-default" href="http://localhost/contact"', $sitemap);
    }

    public function testMonolingualSitemapHasNoAlternates(): void
    {
        $client = self::createClient();
        $client->request('GET', '/sitemap.xml');

        self::assertStringNotContainsString('xhtml', (string) $client->getResponse()->getContent());
    }
}
