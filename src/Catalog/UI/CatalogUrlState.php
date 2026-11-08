<?php

declare(strict_types=1);

namespace App\Catalog\UI;

use App\Catalog\Domain\CatalogQuery;
use App\Catalog\Domain\Exposure;
use App\Catalog\Domain\Price;
use App\Catalog\Domain\ProductFilters;
use App\Catalog\Domain\ProductSort;
use App\Catalog\Domain\Size;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * État du catalogue porté par la query string, seule source de vérité.
 * Les valeurs invalides sont ignorées, les valeurs par défaut absentes de l'URL. Sans vente en ligne, le filtre et les tris
 * par prix sont ignorés comme une valeur invalide.
 */
final readonly class CatalogUrlState
{
    private const string ATTRIBUTE_PREFIX = 'attr-';

    public function __construct(
        public CatalogQuery $query = new CatalogQuery(),
        public ListView $view = ListView::Grid,
    ) {
    }

    public static function fromQueryString(?string $queryString, bool $withPrices = true): self
    {
        /** @var array<string, list<string>> $params parseQuery() sans crochets : une liste de valeurs par clé */
        $params = null === $queryString || '' === $queryString ? [] : HeaderUtils::parseQuery($queryString, true);
        $first = static fn (string $key): ?string => $params[$key][0] ?? null;

        $filters = new ProductFilters(
            array_values(array_filter(Exposure::cases(), static fn (Exposure $case): bool => \in_array($case->value, $params['exposition'] ?? [], true))),
            array_values(array_filter(Size::cases(), static fn (Size $case): bool => \in_array($case->value, $params['taille'] ?? [], true))),
            $withPrices ? self::euros($first('prix_min')) : null,
            $withPrices ? self::euros($first('prix_max')) : null,
            '1' === $first('en_stock'),
            self::attributes($params),
        );

        return new self(
            new CatalogQuery(
                $filters,
                self::sort($first('tri'), $withPrices),
                self::positiveInt($first('page'), 2) ?? 1,
            ),
            ListView::tryFrom((string) $first('vue')) ?? ListView::Grid,
        );
    }

    public function queryString(): string
    {
        $filters = $this->query->filters;
        $pairs = [
            ...array_map(static fn (Exposure $exposure): array => ['exposition', $exposure->value], $filters->exposures),
            ...array_map(static fn (Size $size): array => ['taille', $size->value], $filters->sizes),
            ...array_merge(...array_map(
                static fn (string $code, array $values): array => array_map(static fn (string $value): array => [self::ATTRIBUTE_PREFIX.$code, $value], $values),
                array_keys($filters->attributes),
                $filters->attributes,
            )),
            ...(null !== $filters->priceMin ? [['prix_min', (string) $filters->priceMin->euros()]] : []),
            ...(null !== $filters->priceMax ? [['prix_max', (string) $filters->priceMax->euros()]] : []),
            ...($filters->inStockOnly ? [['en_stock', '1']] : []),
            ...(ProductSort::Relevance !== $this->query->sort ? [['tri', $this->query->sort->value]] : []),
            ...(ListView::Grid !== $this->view ? [['vue', $this->view->value]] : []),
            ...($this->query->page > 1 ? [['page', (string) $this->query->page]] : []),
        ];

        if ([] === $pairs) {
            return '';
        }

        return '?'.implode('&', array_map(static fn (array $pair): string => rawurlencode($pair[0]).'='.rawurlencode($pair[1]), $pairs));
    }

    public function hasFiltersOrSort(): bool
    {
        return $this->query->filters->activeCount() > 0 || ProductSort::Relevance !== $this->query->sort;
    }

    public function withPage(int $page): self
    {
        return new self(new CatalogQuery($this->query->filters, $this->query->sort, $page), $this->view);
    }

    public function withView(ListView $view): self
    {
        return new self($this->query, $view);
    }

    public function withFilters(ProductFilters $filters): self
    {
        return new self(new CatalogQuery($filters, $this->query->sort), $this->view);
    }

    public function cleared(): self
    {
        return new self(new CatalogQuery(), $this->view);
    }

    public function withoutExposure(Exposure $exposure): self
    {
        $filters = $this->query->filters;

        return $this->withFilters(new ProductFilters(
            array_values(array_filter($filters->exposures, static fn (Exposure $value): bool => $value !== $exposure)),
            $filters->sizes, $filters->priceMin, $filters->priceMax, $filters->inStockOnly, $filters->attributes,
        ));
    }

    public function withoutSize(Size $size): self
    {
        $filters = $this->query->filters;

        return $this->withFilters(new ProductFilters(
            $filters->exposures,
            array_values(array_filter($filters->sizes, static fn (Size $value): bool => $value !== $size)),
            $filters->priceMin, $filters->priceMax, $filters->inStockOnly, $filters->attributes,
        ));
    }

    public function withoutAttribute(string $code, string $value): self
    {
        $filters = $this->query->filters;
        $attributes = $filters->attributes;
        $attributes[$code] = array_values(array_filter($attributes[$code] ?? [], static fn (string $checked): bool => $checked !== $value));

        return $this->withFilters(new ProductFilters(
            $filters->exposures, $filters->sizes, $filters->priceMin, $filters->priceMax, $filters->inStockOnly,
            array_filter($attributes, static fn (array $values): bool => [] !== $values),
        ));
    }

    public function withoutPriceMin(): self
    {
        $filters = $this->query->filters;

        return $this->withFilters(new ProductFilters($filters->exposures, $filters->sizes, null, $filters->priceMax, $filters->inStockOnly, $filters->attributes));
    }

    public function withoutPriceMax(): self
    {
        $filters = $this->query->filters;

        return $this->withFilters(new ProductFilters($filters->exposures, $filters->sizes, $filters->priceMin, null, $filters->inStockOnly, $filters->attributes));
    }

    public function withoutInStock(): self
    {
        $filters = $this->query->filters;

        return $this->withFilters(new ProductFilters($filters->exposures, $filters->sizes, $filters->priceMin, $filters->priceMax, false, $filters->attributes));
    }

    /**
     * Les codes et valeurs ne sont connus de l'API qu'à la requête : seule leur forme est vérifiée ici, l'API ignore les inconnus.
     * Ordre d'apparition gardé (celui des facettes, donc du formulaire), doublons retirés.
     *
     * @param array<string, list<string>> $params
     *
     * @return array<string, list<string>>
     */
    private static function attributes(array $params): array
    {
        $attributes = [];
        foreach ($params as $key => $values) {
            $code = substr((string) $key, \strlen(self::ATTRIBUTE_PREFIX));
            if (!str_starts_with((string) $key, self::ATTRIBUTE_PREFIX) || 1 !== preg_match('/^[a-z][a-z0-9-]{0,39}$/', $code)) {
                continue;
            }

            $valid = array_values(array_unique(array_filter($values, static fn (string $value): bool => 1 === preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $value))));
            if ([] !== $valid) {
                $attributes[$code] = $valid;
            }
        }

        return $attributes;
    }

    private static function sort(?string $value, bool $withPrices): ProductSort
    {
        $sort = ProductSort::tryFrom((string) $value) ?? ProductSort::Relevance;

        return $withPrices || !$sort->byPrice() ? $sort : ProductSort::Relevance;
    }

    private static function euros(?string $value): ?Price
    {
        $euros = self::positiveInt($value, 0);

        return null === $euros ? null : Price::fromEuros($euros);
    }

    private static function positiveInt(?string $value, int $min): ?int
    {
        if (null === $value || 1 !== preg_match('/^\d{1,6}$/', $value)) {
            return null;
        }

        $int = (int) $value;

        return $int >= $min ? $int : null;
    }
}
