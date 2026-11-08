<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * Facette d'un attribut à choix filtrable : ses valeurs dans l'ordre de l'admin, avec leur compteur.
 */
final readonly class AttributeFacet
{
    /**
     * @param list<AttributeFacetValue> $values
     */
    public function __construct(
        public string $code,
        public string $label,
        public array $values,
    ) {
    }

    public function valueLabel(string $value): ?string
    {
        foreach ($this->values as $entry) {
            if ($entry->value === $value) {
                return $entry->label;
            }
        }

        return null;
    }
}
