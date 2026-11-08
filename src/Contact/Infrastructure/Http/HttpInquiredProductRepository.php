<?php

declare(strict_types=1);

namespace App\Contact\Infrastructure\Http;

use App\Contact\Domain\InquiredProduct;
use App\Contact\Domain\InquiredProductRepository;
use App\Shared\Infrastructure\Http\InvalidApiPayloadException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpInquiredProductRepository implements InquiredProductRepository
{
    private const string SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(#[Target('api.client')] private HttpClientInterface $apiClient)
    {
    }

    public function find(string $slug): ?InquiredProduct
    {
        if (1 !== preg_match(self::SLUG, $slug)) {
            return null;
        }

        try {
            return ContactTranslation::product($this->apiClient->request('GET', '/api/products/'.$slug)->toArray());
        } catch (ExceptionInterface|InvalidApiPayloadException) {
            return null;
        }
    }
}
