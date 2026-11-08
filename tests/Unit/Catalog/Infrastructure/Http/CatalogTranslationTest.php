<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Http;

use App\Catalog\Domain\AttributeFacetValue;
use App\Catalog\Domain\CatalogQuery;
use App\Catalog\Domain\Category;
use App\Catalog\Domain\CategoryPath;
use App\Catalog\Domain\Exposure;
use App\Catalog\Domain\Price;
use App\Catalog\Domain\ProductAttribute;
use App\Catalog\Domain\ProductFilters;
use App\Catalog\Domain\ProductSort;
use App\Catalog\Domain\Size;
use App\Catalog\Infrastructure\Http\CatalogTranslation;
use App\Shared\Infrastructure\Http\InvalidApiPayloadException;
use PHPUnit\Framework\TestCase;

final class CatalogTranslationTest extends TestCase
{
    public function testTranslatesTheCategoryTreeUnderARoot(): void
    {
        $tree = CatalogTranslation::tree(self::payload('categories'));

        self::assertSame('Catalogue', $tree->root->name);
        self::assertSame('Feuillages', CategoryPath::resolve($tree, 'plantes-interieur/feuillages')?->category()->name);
    }

    public function testTranslatesAProductPageWithPricesInCentsAndEveryFacetValue(): void
    {
        $root = CatalogTranslation::tree(self::payload('categories'))->root;
        $result = CatalogTranslation::result(self::payload('products'), self::payload('product-facets'), $root, 1);

        self::assertSame(24, $result->page->total);
        self::assertSame(2, $result->page->pageCount);
        self::assertCount(12, $result->page->items);
        self::assertSame(3490, $result->page->items[0]->price->cents);
        self::assertSame(['soleil', 'mi-ombre', 'ombre'], array_keys($result->facets->exposures));
        self::assertSame(['S', 'M', 'L'], array_keys($result->facets->sizes));
        self::assertSame(['plantes-interieur' => 12, 'plantes-exterieur' => 9, 'plantes-aquatiques' => 3], $result->facets->categories);
    }

    public function testTranslatesTheAttributeFacetsWithTheirValuesInOrder(): void
    {
        $result = CatalogTranslation::result(self::payload('products'), self::payload('product-facets'), new Category('', 'Catalogue'), 1);

        $facet = $result->facets->attribute('feuillage');
        self::assertNotNull($facet);
        self::assertSame('Feuillage', $facet->label);
        self::assertSame(
            [['persistant', 'Persistant', 6], ['caduc', 'Caduc', 2]],
            array_map(static fn (AttributeFacetValue $value): array => [$value->value, $value->label, $value->count], $facet->values),
        );
        self::assertSame('Caduc', $facet->valueLabel('caduc'));
        self::assertNull($facet->valueLabel('inconnu'));
        self::assertNull($result->facets->attribute('hauteur-adulte'));
        self::assertSame([], CatalogTranslation::result([], ['totalItems' => 0], new Category('', 'Catalogue'), 1)->facets->attributes);
    }

    public function testTranslatesTheProductAttributesWithTheirUnit(): void
    {
        $product = CatalogTranslation::detail(self::payload('product-monstera-deliciosa'))->product;

        self::assertEquals(
            [new ProductAttribute('feuillage', 'Feuillage', 'Persistant', null), new ProductAttribute('hauteur-adulte', 'Hauteur adulte', '300', 'cm')],
            $product->attributes,
        );
        self::assertSame([], CatalogTranslation::products(self::payload('featured-products'))[0]->attributes);
    }

    public function testAnEmptyResultStillHasOnePage(): void
    {
        $result = CatalogTranslation::result(self::payload('products-empty'), self::payload('product-facets-empty'), new Category('', 'Catalogue'), 1);

        self::assertSame(0, $result->page->total);
        self::assertSame(1, $result->page->pageCount);
    }

    public function testComputesThePageCountFromTheFacetTotal(): void
    {
        $result = CatalogTranslation::result([], ['totalItems' => 25], new Category('', 'Catalogue'), 3);

        self::assertSame(25, $result->page->total);
        self::assertSame(3, $result->page->page);
        self::assertSame(3, $result->page->pageCount);
    }

    public function testSplitsTheProductDescriptionIntoParagraphsAtBlankLines(): void
    {
        $payload = self::payload('product-monstera-deliciosa');
        self::assertIsArray($payload);

        self::assertCount(2, CatalogTranslation::detail($payload)->description);

        $payload['description'] = "Rustique.\n\n  \nRésiste au vent,\naux embruns.";
        self::assertSame(['Rustique.', "Résiste au vent,\naux embruns."], CatalogTranslation::detail($payload)->description);

        $payload['description'] = '';
        self::assertSame([], CatalogTranslation::detail($payload)->description);
    }

    public function testTranslatesTheGalleryInOrderAndTheMetadata(): void
    {
        $detail = CatalogTranslation::detail(self::payload('product-monstera-deliciosa'));

        self::assertCount(2, $detail->gallery);
        self::assertSame([800, 800], [$detail->gallery[0]->width, $detail->gallery[0]->height]);
        self::assertStringContainsString('a32d6e246a7553a3f0304c6fc434a220', $detail->gallery[0]->src);
        self::assertSame(['', ''], [$detail->metaTitle, $detail->metaDescription]);
        self::assertSame([], CatalogTranslation::detail(self::payload('product-monstera-adansonii'))->gallery);
    }

    public function testBuildsTheSearchQueryInTheLanguageOfTheApi(): void
    {
        $query = new CatalogQuery(
            new ProductFilters([Exposure::Sun, Exposure::Shade], [Size::Large], Price::fromEuros(10), Price::fromEuros(50), true, ['feuillage' => ['persistant', 'caduc'], 'couleur' => ['rouge']]),
            ProductSort::PriceDescending,
            2,
        );

        self::assertSame(
            'category=feuillages&exposure%5B%5D=soleil&exposure%5B%5D=ombre&size%5B%5D=L&attributes%5Bfeuillage%5D%5B%5D=persistant&attributes%5Bfeuillage%5D%5B%5D=caduc&attributes%5Bcouleur%5D%5B%5D=rouge&priceMin=1000&priceMax=5000&inStock=true&order%5Bprice%5D=desc&page=2&itemsPerPage=12',
            CatalogTranslation::searchQuery(new Category('feuillages', 'Feuillages'), $query),
        );
        self::assertSame('page=1&itemsPerPage=12', CatalogTranslation::searchQuery(new Category('', 'Catalogue'), new CatalogQuery()));
        self::assertSame(
            'category=feuillages&exposure%5B%5D=soleil&exposure%5B%5D=ombre&size%5B%5D=L&attributes%5Bfeuillage%5D%5B%5D=persistant&attributes%5Bfeuillage%5D%5B%5D=caduc&attributes%5Bcouleur%5D%5B%5D=rouge&priceMin=1000&priceMax=5000&inStock=true',
            CatalogTranslation::facetsQuery(new Category('feuillages', 'Feuillages'), $query),
        );
    }

    public function testRejectsAnUnknownExposure(): void
    {
        $payload = self::payload('products');
        self::assertIsArray($payload);
        self::assertIsArray($payload[0]);
        $payload[0]['exposure'] = 'lune';

        $this->expectException(InvalidApiPayloadException::class);

        CatalogTranslation::result($payload, self::payload('product-facets'), new Category('', 'Catalogue'), 1);
    }

    private static function payload(string $name): mixed
    {
        return json_decode((string) file_get_contents(__DIR__.'/../../../../Fixtures/api/'.$name.'.json'), true, flags: \JSON_THROW_ON_ERROR);
    }
}
