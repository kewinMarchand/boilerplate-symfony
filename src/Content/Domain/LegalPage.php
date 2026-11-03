<?php

declare(strict_types=1);

namespace App\Content\Domain;

/**
 * Page légale administrée dans l'API. Introduction et sections sont du HTML produit par l'API depuis le
 * Markdown de l'admin : l'API échappe le HTML saisi et retire les liens javascript: et data:.
 */
final readonly class LegalPage
{
    /**
     * @param list<LegalPageSection> $sections
     */
    public function __construct(
        public string $title,
        public string $introduction,
        public array $sections,
    ) {
    }
}
