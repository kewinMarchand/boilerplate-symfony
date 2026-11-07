<?php

declare(strict_types=1);

namespace App\Blog\Domain;

interface ArticleRepository
{
    /**
     * @return list<Article>
     *
     * @throws ArticlesLoadException
     */
    public function findLatest(int $limit): array;

    /**
     * Null si l'API ne connaît pas l'article (404).
     *
     * @throws ArticleLoadException
     */
    public function find(string $slug): ?ArticleDetail;
}
