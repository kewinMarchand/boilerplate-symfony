<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Http;

use App\Blog\Domain\Article;
use App\Blog\Domain\ArticleAudio;
use App\Blog\Domain\ArticleDetail;
use App\Blog\Domain\ArticleTerm;
use App\Shared\Infrastructure\Http\ApiPayload;
use App\Shared\Infrastructure\Http\InvalidApiPayloadException;

final class ArticleTranslation
{
    /**
     * @return list<Article>
     */
    public static function articles(mixed $payload): array
    {
        return array_map(self::article(...), ApiPayload::objects($payload, 'articles'));
    }

    public static function detail(mixed $payload): ArticleDetail
    {
        $article = ApiPayload::object($payload, 'article');

        return new ArticleDetail(
            self::article($article),
            ApiPayload::string($article, 'html'),
            ApiPayload::string($article, 'metaTitle'),
            ApiPayload::string($article, 'metaDescription'),
        );
    }

    /**
     * @return list<ArticleTerm>
     */
    public static function terms(mixed $payload): array
    {
        return array_map(self::term(...), ApiPayload::objects($payload, 'terms'));
    }

    /**
     * @param array<string, mixed> $article
     */
    private static function article(array $article): Article
    {
        $publishedAt = \DateTimeImmutable::createFromFormat('!Y-m-d', ApiPayload::string($article, 'publishedAt'));
        if (false === $publishedAt) {
            throw new InvalidApiPayloadException('publishedAt', 'une date AAAA-MM-JJ');
        }

        return new Article(
            ApiPayload::id($article, 'id'),
            ApiPayload::string($article, 'slug'),
            ApiPayload::string($article, 'title'),
            ApiPayload::string($article, 'excerpt'),
            $publishedAt,
            ApiPayload::image($article, 'image'),
            null === ($article['section'] ?? null) ? null : self::term(ApiPayload::object($article['section'], 'section')),
            null === ($article['format'] ?? null) ? null : self::term(ApiPayload::object($article['format'], 'format')),
            null === ($article['audio'] ?? null) ? null : self::audio(ApiPayload::object($article['audio'], 'audio')),
            ApiPayload::string($article, 'speaker'),
        );
    }

    /**
     * @param array<string, mixed> $term
     */
    private static function term(array $term): ArticleTerm
    {
        return new ArticleTerm(ApiPayload::string($term, 'slug'), ApiPayload::string($term, 'name'));
    }

    /**
     * @param array<string, mixed> $audio
     */
    private static function audio(array $audio): ArticleAudio
    {
        return new ArticleAudio(ApiPayload::string($audio, 'src'), ApiPayload::string($audio, 'type'), ApiPayload::int($audio, 'durationSeconds'));
    }
}
