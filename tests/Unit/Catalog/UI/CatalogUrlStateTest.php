<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\UI;

use App\Catalog\Domain\Exposure;
use App\Catalog\Domain\ProductSort;
use App\Catalog\Domain\Size;
use App\Catalog\UI\CatalogUrlState;
use App\Catalog\UI\ListView;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CatalogUrlStateTest extends TestCase
{
    public function testParsesRepeatedKeysAndEveryParameter(): void
    {
        $state = CatalogUrlState::fromQueryString('exposition=soleil&exposition=mi-ombre&taille=L&prix_min=10&prix_max=50&en_stock=1&tri=prix-asc&vue=liste&page=2');

        $filters = $state->query->filters;
        self::assertSame([Exposure::Sun, Exposure::PartialShade], $filters->exposures);
        self::assertSame([Size::Large], $filters->sizes);
        self::assertSame(1000, $filters->priceMin?->cents);
        self::assertSame(5000, $filters->priceMax?->cents);
        self::assertTrue($filters->inStockOnly);
        self::assertSame(ProductSort::PriceAscending, $state->query->sort);
        self::assertSame(ListView::List, $state->view);
        self::assertSame(2, $state->query->page);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function canonicalQueries(): iterable
    {
        yield 'vide' => [''];
        yield 'facettes' => ['?exposition=soleil&exposition=mi-ombre&taille=S'];
        yield 'tout' => ['?exposition=ombre&taille=M&taille=L&prix_min=5&prix_max=80&en_stock=1&tri=nom&vue=liste&page=3'];
        yield 'attributs' => ['?taille=S&attr-feuillage=persistant&attr-feuillage=caduc&attr-couleur=rouge&en_stock=1'];
    }

    #[DataProvider('canonicalQueries')]
    public function testRoundTrip(string $query): void
    {
        self::assertSame($query, CatalogUrlState::fromQueryString(ltrim($query, '?'))->queryString());
    }

    public function testIgnoresInvalidAndDefaultValues(): void
    {
        $state = CatalogUrlState::fromQueryString('exposition=lune&taille=XL&prix_min=-3&prix_max=abc&en_stock=oui&tri=pertinence&vue=grille&page=1&inconnu=1');

        self::assertSame('', $state->queryString());
        self::assertFalse($state->hasFiltersOrSort());
    }

    public function testNormalisesOrderAndEmptyFormFields(): void
    {
        $state = CatalogUrlState::fromQueryString('taille=S&exposition=ombre&prix_min=&prix_max=&tri=');

        self::assertSame('?exposition=ombre&taille=S', $state->queryString());
    }

    public function testParsesAttributesInTheirOrderAndIgnoresMalformedOnes(): void
    {
        $state = CatalogUrlState::fromQueryString('attr-feuillage=persistant&attr-Couleur=rouge&attr-=x&attr-feuillage=persistant&attr-feuillage=Caduc&attr-hauteur=&prix_min=5&attr-couleur=rouge');

        self::assertSame(['feuillage' => ['persistant'], 'couleur' => ['rouge']], $state->query->filters->attributes);
        self::assertSame(3, $state->query->filters->activeCount());
        self::assertSame('?attr-feuillage=persistant&attr-couleur=rouge&prix_min=5', $state->queryString());
    }

    public function testRemovingAFilterResetsThePageAndKeepsTheOtherAttributes(): void
    {
        $state = CatalogUrlState::fromQueryString('attr-feuillage=persistant&attr-feuillage=caduc&attr-couleur=rouge&page=2');

        self::assertSame('?attr-feuillage=caduc&attr-couleur=rouge', $state->withoutAttribute('feuillage', 'persistant')->queryString());
        self::assertSame('?attr-feuillage=persistant&attr-feuillage=caduc', $state->withoutAttribute('couleur', 'rouge')->queryString());
        self::assertSame('?exposition=soleil&attr-couleur=rouge', CatalogUrlState::fromQueryString('attr-couleur=rouge&exposition=soleil&prix_min=5')->withoutPriceMin()->queryString());
    }

    public function testChangingFiltersResetsThePageAndKeepsTheView(): void
    {
        $state = CatalogUrlState::fromQueryString('exposition=soleil&taille=S&vue=liste&page=2');

        self::assertSame('?taille=S&vue=liste', $state->withoutExposure(Exposure::Sun)->queryString());
        self::assertSame('?vue=liste', $state->cleared()->queryString());
        self::assertSame('?exposition=soleil&taille=S&page=2', $state->withView(ListView::Grid)->queryString());
    }
}
