<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Catalog\Domain\CatalogLoadException;
use App\Catalog\Domain\CatalogQuery;
use App\Catalog\Domain\CatalogRepository;
use App\Catalog\Domain\CatalogResult;
use App\Catalog\Domain\Category;
use App\Catalog\Domain\CategoryTree;
use App\Catalog\Infrastructure\Http\CatalogTranslation;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CatalogControllerTest extends WebTestCase
{
    public function testRootListsTheFirstTwelveOfTwentyFourProducts(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/catalogue');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('[data-testid="catalog-results-count"]', '24 produits');
        self::assertSelectorCount(12, '[data-testid="catalog-product"]');
        self::assertSelectorExists('[data-testid="catalog-pagination"] [aria-current="page"]');
        self::assertSelectorNotExists('[data-testid="catalog-active-filters"]');
        self::assertSame('index, follow', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertSame('http://localhost/catalogue?page=2', $crawler->filter('link[rel="next"]')->attr('href'));
    }

    public function testShowsNoPriceNorPriceFilterOrSortWithoutOnlineSales(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/catalogue', server: ['HTTP_X_SCENARIO' => 'sans-vente']);

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(12, '[data-testid="catalog-product"]');
        self::assertSelectorNotExists('[data-testid="catalog-product-price"]');
        self::assertSelectorNotExists('[data-testid="catalog-filter-price-min"]');
        self::assertSelectorNotExists('[data-testid="catalog-filter-price-max"]');
        self::assertSame(['pertinence', 'nom'], $crawler->filter('[data-testid="catalog-sort"] option')->each(static fn ($option): ?string => $option->attr('value')));

        $client->request('GET', '/catalogue?prix_min=10&tri=prix-asc', server: ['HTTP_X_SCENARIO' => 'sans-vente']);
        self::assertResponseRedirects('/catalogue', 301);
    }

    public function testCategoryBranchListsItsProductsAndBreadcrumb(): void
    {
        $client = self::createClient();
        $client->request('GET', '/catalogue/plantes-interieur/feuillages');

        self::assertSelectorTextSame('[data-testid="catalog-results-count"]', '6 produits');
        self::assertSelectorTextSame('[data-testid="layout-breadcrumb"] [aria-current="page"]', 'Feuillages');
        self::assertSelectorNotExists('[data-testid="catalog-pagination"]');
    }

    public function testFiltersAreNoindexWithACanonicalWithoutFilters(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/catalogue/plantes-interieur?exposition=mi-ombre');

        self::assertResponseIsSuccessful();
        self::assertSame('noindex, follow', $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertSame('http://localhost/catalogue/plantes-interieur', $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertSelectorCount(1, '[data-testid="catalog-active-filter"]');
        self::assertSelectorTextContains('[data-testid="catalog-active-filter"]', 'Retirer le filtre : Mi-ombre');
        self::assertSelectorExists('[data-testid="catalog-filter-exposure-mi-ombre"][checked]');
    }

    public function testAttributeFacetIsAFormFieldAndItsActiveValueAChip(): void
    {
        $client = self::createClient();
        $client->request('GET', '/catalogue?attr-feuillage=caduc');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="attr-feuillage"][value="caduc"][checked][data-testid="catalog-filter-attribute-feuillage-caduc"]');
        self::assertSelectorTextContains('label[for="filter-attribute-feuillage-persistant"]', 'Persistant (6)');
        self::assertSelectorTextContains('[data-testid="catalog-active-filter"]', 'Retirer le filtre : Feuillage : Caduc');
        self::assertSame('/catalogue', $client->getCrawler()->filter('[data-testid="catalog-active-filter"]')->attr('href'));
    }

    public function testNonCanonicalQueriesAreRedirected(): void
    {
        $client = self::createClient();
        $client->request('GET', '/catalogue?taille=S&exposition=soleil&prix_min=&tri=pertinence&page=1&inconnu=x');

        self::assertResponseRedirects('/catalogue?exposition=soleil&taille=S', 301);
    }

    public function testUnknownCategoryAndOutOfRangePageAreReal404(): void
    {
        $client = self::createClient();
        $client->request('GET', '/catalogue/inconnu');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/catalogue?page=9');
        self::assertResponseStatusCodeSame(404);
    }

    public function testEmptyResultShowsAFriendlyMessage(): void
    {
        $client = self::createClient();
        $client->request('GET', '/catalogue?prix_max=1');

        self::assertSelectorTextContains('[data-testid="catalog-empty"]', 'Aucun produit ne correspond à ces filtres.');
        self::assertSelectorTextSame('[data-testid="catalog-results-count"]', '0 produit');
    }

    public function testLoadingFailureShowsARetry(): void
    {
        $client = self::createClient();
        self::getContainer()->set(CatalogRepository::class, new class implements CatalogRepository {
            public function categoryTree(): CategoryTree
            {
                return CatalogTranslation::tree(json_decode((string) file_get_contents(__DIR__.'/../Fixtures/api/categories.json'), true, flags: \JSON_THROW_ON_ERROR));
            }

            public function search(Category $category, CatalogQuery $query): CatalogResult
            {
                throw new CatalogLoadException();
            }

            public function findProduct(string $slug): ?\App\Catalog\Domain\ProductDetail
            {
                return null;
            }

            public function featured(): array
            {
                return [];
            }

            public function similar(\App\Catalog\Domain\Product $product, int $limit): array
            {
                return [];
            }

            public function productSlugs(): array
            {
                return [];
            }
        });
        $client->request('GET', '/catalogue');

        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('[data-testid="catalog-error"]', 'Impossible de charger les produits');
        self::assertSelectorExists('[data-testid="catalog-retry"]');
    }

    public function testItemListJsonLdDescribesThePage(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/catalogue/plantes-aquatiques');

        $types = $crawler->filter('script[type="application/ld+json"]')->each(static fn ($script): mixed => json_decode($script->text(), true));
        $itemList = array_values(array_filter($types, static fn (mixed $data): bool => \is_array($data) && 'ItemList' === $data['@type']))[0] ?? null;
        self::assertIsArray($itemList);
        self::assertSame(3, $itemList['numberOfItems']);
    }
}
