<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Http;

use App\Blog\Domain\ArticleRepository;
use App\Blog\Domain\ArticlesLoadException;
use App\Shared\Infrastructure\Http\InvalidApiPayloadException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpArticleRepository implements ArticleRepository
{
    public function __construct(#[Target('api.client')] private HttpClientInterface $apiClient)
    {
    }

    public function findLatest(int $limit): array
    {
        try {
            $articles = ArticleTranslation::articles($this->apiClient->request('GET', '/api/articles')->toArray());
        } catch (ExceptionInterface|InvalidApiPayloadException $exception) {
            throw new ArticlesLoadException($exception);
        }

        return \array_slice($articles, 0, $limit);
    }
}
