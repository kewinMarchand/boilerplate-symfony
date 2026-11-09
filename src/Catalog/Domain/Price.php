<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * Prix en centimes d'euro, entier positif ou nul, comme dans l'API. Les euros n'existent que dans
 * l'URL (filtres de prix) et à l'affichage : fromEuros() et euros() sont les seules conversions.
 */
final readonly class Price
{
    private function __construct(public int $cents)
    {
    }

    public static function fromCents(int $cents): self
    {
        if ($cents < 0) {
            throw new InvalidPriceException($cents);
        }

        return new self($cents);
    }

    public static function fromEuros(int $euros): self
    {
        return self::fromCents($euros * 100);
    }

    public function euros(): int
    {
        return intdiv($this->cents, 100);
    }

    /**
     * @param string $locale langue de la page, « fr_FR » : « 34,90 € », « en_US » : « €34.90 »
     */
    public function format(string $locale = 'fr_FR'): string
    {
        return (string) (new \NumberFormatter($locale, \NumberFormatter::CURRENCY))->formatCurrency($this->cents / 100, 'EUR');
    }
}
