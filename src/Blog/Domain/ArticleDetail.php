<?php

declare(strict_types=1);

namespace App\Blog\Domain;

/**
 * Fiche d'un article : l'article et son texte en HTML, converti par l'API depuis le Markdown saisi dans l'admin
 * (HTML saisi échappé, liens javascript: et data: retirés), ses métadonnées saisies (chaînes vides si non saisies) et son
 * slug dans chaque langue activée (vide sur un site monolingue).
 */
final readonly class ArticleDetail
{
    /**
     * @param array<string, string> $alternates
     */
    public function __construct(
        public Article $article,
        public string $html,
        public string $metaTitle,
        public string $metaDescription,
        public array $alternates = [],
    ) {
    }
}
