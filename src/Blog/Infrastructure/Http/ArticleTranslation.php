<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Http;

use App\Blog\Domain\Article;
use App\Shared\Infrastructure\Http\ApiPayload;
use App\Shared\Infrastructure\Http\InvalidApiPayloadException;

final class ArticleTranslation
{
    /**
     * @return list<Article>
     */
    public static function articles(mixed $payload): array
    {
        return array_map(static function (array $article): Article {
            $publishedAt = \DateTimeImmutable::createFromFormat('!Y-m-d', ApiPayload::string($article, 'publishedAt'));
            if (false === $publishedAt) {
                throw new InvalidApiPayloadException('publishedAt', 'une date AAAA-MM-JJ');
            }

            return new Article(ApiPayload::id($article, 'id'), ApiPayload::string($article, 'title'), ApiPayload::string($article, 'excerpt'), $publishedAt, ApiPayload::image($article, 'image'));
        }, ApiPayload::objects($payload, 'articles'));
    }
}
