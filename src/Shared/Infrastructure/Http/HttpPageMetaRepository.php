<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\PageMetaRepository;
use App\Shared\Domain\PageMetasLoadException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpPageMetaRepository implements PageMetaRepository
{
    public function __construct(#[Target('api.client')] private HttpClientInterface $apiClient)
    {
    }

    public function all(): array
    {
        try {
            return PageMetaTranslation::pageMetas($this->apiClient->request('GET', '/api/page-metas')->toArray());
        } catch (ExceptionInterface|InvalidApiPayloadException $exception) {
            throw new PageMetasLoadException($exception);
        }
    }
}
