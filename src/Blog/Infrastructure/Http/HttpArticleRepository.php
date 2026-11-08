<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Http;

use App\Blog\Domain\ArticleDetail;
use App\Blog\Domain\ArticleLoadException;
use App\Blog\Domain\ArticleRepository;
use App\Blog\Domain\ArticlesLoadException;
use App\Shared\Infrastructure\Http\InvalidApiPayloadException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpArticleRepository implements ArticleRepository
{
    public function __construct(#[Target('api.client')] private HttpClientInterface $apiClient)
    {
    }

    public function findLatest(int $limit, ?string $section = null): array
    {
        try {
            $query = null === $section ? [] : ['query' => ['section' => $section]];
            $articles = ArticleTranslation::articles($this->apiClient->request('GET', '/api/articles', $query)->toArray());
        } catch (ExceptionInterface|InvalidApiPayloadException $exception) {
            throw new ArticlesLoadException($exception);
        }

        return \array_slice($articles, 0, $limit);
    }

    public function sections(): array
    {
        try {
            return ArticleTranslation::terms($this->apiClient->request('GET', '/api/article-sections')->toArray());
        } catch (ExceptionInterface|InvalidApiPayloadException $exception) {
            throw new ArticlesLoadException($exception);
        }
    }

    public function find(string $slug): ?ArticleDetail
    {
        try {
            $response = $this->apiClient->request('GET', '/api/articles/'.rawurlencode($slug));
            if (Response::HTTP_NOT_FOUND === $response->getStatusCode()) {
                return null;
            }

            return ArticleTranslation::detail($response->toArray());
        } catch (ExceptionInterface|InvalidApiPayloadException $exception) {
            throw new ArticleLoadException($exception);
        }
    }
}
