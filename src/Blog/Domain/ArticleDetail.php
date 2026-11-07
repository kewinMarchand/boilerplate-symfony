<?php

declare(strict_types=1);

namespace App\Blog\Domain;

/**
 * Fiche d'un article : l'article et son texte en HTML, converti par l'API depuis le Markdown saisi dans l'admin
 * (HTML saisi échappé, liens javascript: et data: retirés).
 */
final readonly class ArticleDetail
{
    public function __construct(
        public Article $article,
        public string $html,
    ) {
    }
}
