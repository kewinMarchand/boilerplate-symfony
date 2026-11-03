<?php

declare(strict_types=1);

namespace App\Content\Infrastructure\Http;

use App\Content\Domain\LegalPage;
use App\Content\Domain\LegalPageLoadException;
use App\Content\Domain\LegalPageRepository;
use App\Shared\Infrastructure\Http\InvalidApiPayloadException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpLegalPageRepository implements LegalPageRepository
{
    public function __construct(#[Target('api.client')] private HttpClientInterface $apiClient)
    {
    }

    public function find(string $slug): ?LegalPage
    {
        try {
            $response = $this->apiClient->request('GET', '/api/legal_pages/'.rawurlencode($slug));
            if (Response::HTTP_NOT_FOUND === $response->getStatusCode()) {
                return null;
            }

            return LegalPageTranslation::legalPage($response->toArray());
        } catch (ExceptionInterface|InvalidApiPayloadException $exception) {
            throw new LegalPageLoadException($exception);
        }
    }
}
