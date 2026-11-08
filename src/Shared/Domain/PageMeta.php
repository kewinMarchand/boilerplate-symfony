<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Titre et description d'une page fixe saisis dans l'admin (section 26 de la spec commune), chaînes vides si non saisis.
 */
final readonly class PageMeta
{
    public function __construct(
        public string $title,
        public string $description,
    ) {
    }
}
