<?php

declare(strict_types=1);

namespace App\Blog\Domain;

/**
 * Rubrique ou format d'un article, listes administrables dans l'API (section 25 de la spec commune).
 */
final readonly class ArticleTerm
{
    public function __construct(
        public string $slug,
        public string $name,
    ) {
    }
}
