<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\SiteIdentity;
use App\Shared\Domain\SiteIdentityLoadException;
use App\Shared\Domain\SiteIdentityRepository;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpSiteIdentityRepository implements SiteIdentityRepository
{
    public function __construct(#[Target('api.client')] private HttpClientInterface $apiClient)
    {
    }

    public function current(): SiteIdentity
    {
        try {
            return SiteIdentityTranslation::identity($this->apiClient->request('GET', '/api/site-identity')->toArray());
        } catch (ExceptionInterface|InvalidApiPayloadException $exception) {
            throw new SiteIdentityLoadException($exception);
        }
    }
}
