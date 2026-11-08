<?php

declare(strict_types=1);

namespace App\Blog\Domain;

interface ArticleRepository
{
    /**
     * @param string|null $section slug de rubrique : seulement ses articles (aucun si l'API ne la connaît pas)
     *
     * @return list<Article>
     *
     * @throws ArticlesLoadException
     */
    public function findLatest(int $limit, ?string $section = null): array;

    /**
     * Rubriques par position, même vides.
     *
     * @return list<ArticleTerm>
     *
     * @throws ArticlesLoadException
     */
    public function sections(): array;

    /**
     * Null si l'API ne connaît pas l'article (404).
     *
     * @throws ArticleLoadException
     */
    public function find(string $slug): ?ArticleDetail;
}
