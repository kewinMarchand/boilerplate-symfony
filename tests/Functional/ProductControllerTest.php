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

            public function productSlugs(): array
            {
                return $this->real->productSlugs();
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
}
