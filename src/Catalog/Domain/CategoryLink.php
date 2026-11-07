<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * Étape du chemin d'un produit dans l'arbre : nom de la catégorie et valeur du paramètre d'URL du catalogue
 * (« plantes-interieur/feuillages »).
 */
final readonly class CategoryLink
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $path,
    ) {
    }
}
