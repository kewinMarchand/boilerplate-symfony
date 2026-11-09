<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Catalog\Domain\CatalogLoadException;
use App\Catalog\Domain\CatalogQuery;
use App\Catalog\Domain\CatalogRepository;
use App\Catalog\Domain\CatalogResult;
use App\Catalog\Domain\Category;
use App\Catalog\Domain\CategoryTree;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductDetail;
use App\Catalog\Infrastructure\Http\HttpCatalogRepository;
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
        self::assertSelectorCount(2, '[data-testid="product-description"] p');
        self::assertSame(['Feuillage', 'Persistant', 'Hauteur adulte', "300\u{a0}cm"], $crawler->filter('[data-testid="product-attributes"] > *')->each(static fn ($node): string => $node->text()));
        self::assertSelectorExists('[data-testid="product-page"] img[fetchpriority="high"]');
        self::assertSelectorExists('[data-testid="layout-breadcrumb"] a[href="/catalogue/plantes-interieur/feuillages/monstera"]');
        $jsonLd = $crawler->filter('script[type="application/ld+json"]')->each(static fn ($node): string => $node->text());
        self::assertTrue((bool) array_filter($jsonLd, static fn (string $json): bool => str_contains($json, '"@type":"Product"') && str_contains($json, '"price":"34.90"')));
    }

    public function testHandsTheProductToTheAddToCartButton(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', self::PRODUCT);

        $button = $crawler->filter('[data-testid="product-page"] [data-controller="add-to-cart"]');
        self::assertSame('{"slug":"monstera-deliciosa","name":"Monstera deliciosa","price":3490}', $button->attr('data-add-to-cart-item-value'));
        self::assertSame('/panier', $button->attr('data-add-to-cart-cart-url-value'));
    }

    public function testOffersAnInquiryInsteadOfPricesAndCartWithoutOnlineSales(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', self::PRODUCT, server: ['HTTP_X_SCENARIO' => 'sans-vente']);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-testid="product-price"]');
        self::assertSelectorNotExists('[data-controller="add-to-cart"]');
        $inquiry = $crawler->filter('[data-testid="product-inquiry"]');
        self::assertSame('/contact?produit=monstera-deliciosa', $inquiry->attr('href'));
        self::assertSame('Demande de renseignement sur Monstera deliciosa', $inquiry->text());
        self::assertSelectorNotExists('[data-testid="product-similar"] .carousel__slide p');
        $jsonLd = implode('', $crawler->filter('script[type="application/ld+json"]')->each(static fn ($node): string => $node->text()));
        self::assertStringContainsString('"@type":"Product"', $jsonLd);
        self::assertStringNotContainsString('"offers"', $jsonLd);
        self::assertStringNotContainsString('34,90', (string) $crawler->filter('meta[name="description"]')->attr('content'));
    }

    public function testShowsTheOtherProductsOfItsCategoryAfterTheProduct(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', self::PRODUCT);

        self::assertSelectorTextSame('[data-testid="product-similar"] h2', 'Produits similaires');
        $links = $crawler->filter('[data-testid="product-page"] + [data-testid="product-similar"] [data-testid="carousel-slide-link"]');
        self::assertSame(['/produit/monstera-adansonii', '/produit/monstera-thai-constellation'], $links->each(static fn ($link): ?string => $link->attr('href')));
    }

    public function testHidesTheSimilarProductsWhenTheirListFails(): void
    {
        $client = self::createClient();
        // Une instance à part : le service du conteneur ne peut plus être remplacé une fois créé
        $real = new HttpCatalogRepository(self::getContainer()->get('api.client'));
        self::getContainer()->set(CatalogRepository::class, new readonly class($real) implements CatalogRepository {
            public function __construct(private CatalogRepository $real)
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
                return $this->real->featured();
            }

            public function similar(Product $product, int $limit): array
            {
                throw new CatalogLoadException();
            }

            public function productSlugs(?string $locale = null): array
            {
                return $this->real->productSlugs($locale);
            }
        });
        $client->request('GET', self::PRODUCT);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('[data-testid="product-title"]', 'Monstera deliciosa');
        self::assertSelectorNotExists('[data-testid="product-similar"]');
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

    public function testShowsTheGalleryWithTheMainImageFirst(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', self::PRODUCT);

        $thumbs = $crawler->filter('[data-testid="product-gallery"] [data-testid="product-gallery-thumb"]');
        self::assertSame(['Voir l’image 1 sur 3', 'Voir l’image 2 sur 3', 'Voir l’image 3 sur 3'], $thumbs->each(static fn ($thumb): ?string => $thumb->attr('aria-label')));
        self::assertSame(['true', null, null], $thumbs->each(static fn ($thumb): ?string => $thumb->attr('aria-current')));
        self::assertStringEndsWith('01272a8b6d3f8221cfe5a2a36aefcd35-800.webp', (string) $thumbs->first()->attr('href'));
        self::assertSame('a', $thumbs->first()->nodeName());
        self::assertSelectorCount(3, '[data-testid="product-gallery-thumb"] img[loading="lazy"][alt=""]');
        self::assertSelectorCount(1, '[data-testid="product-gallery"] img[fetchpriority="high"]');
    }

    public function testWithoutGalleryShowsTheMainImageAlone(): void
    {
        $client = self::createClient();
        $client->request('GET', '/produit/monstera-adansonii');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-testid="product-gallery"]');
        self::assertSelectorExists('[data-testid="product-page"] img[fetchpriority="high"]');
    }

    public function testTheMetadataEnteredInTheAdminReplaceTheComputedOnes(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', self::PRODUCT, server: ['HTTP_X_SCENARIO' => 'metas']);

        self::assertPageTitleSame('Titre saisi dans l’admin');
        self::assertStringStartsWith('Description saisie dans l’admin', (string) $crawler->filter('meta[name="description"]')->attr('content'));
        self::assertSame('Titre saisi dans l’admin', $crawler->filter('meta[property="og:title"]')->attr('content'));
        self::assertSelectorTextSame('[data-testid="product-title"]', 'Monstera deliciosa');
    }

    public function testWithoutMetadataKeepsTheComputedTitleAndDescription(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', self::PRODUCT);

        self::assertPageTitleSame('Monstera deliciosa · Boilerplate');
        self::assertStringStartsWith('Monstera deliciosa : exposition mi-ombre', (string) $crawler->filter('meta[name="description"]')->attr('content'));
    }
}
