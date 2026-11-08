<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * Caractéristique d'un produit définie dans l'admin (feuillage, hauteur adulte…), affichée telle que l'API la libelle.
 */
final readonly class ProductAttribute
{
    public function __construct(
        public string $code,
        public string $label,
        public string $valueLabel,
        public ?string $unit,
    ) {
    }
}
