<?php

declare(strict_types=1);

namespace App\Content\Infrastructure\Http;

use App\Content\Domain\SlideRepository;
use App\Content\Domain\SlidesLoadException;
use App\Shared\Infrastructure\Http\InvalidApiPayloadException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpSlideRepository implements SlideRepository
{
    public function __construct(private HttpClientInterface $apiClient)
    {
    }

    public function findAll(): array
    {
        try {
            return SlideTranslation::slides($this->apiClient->request('GET', '/api/slides')->toArray());
        } catch (ExceptionInterface|InvalidApiPayloadException $exception) {
            throw new SlidesLoadException($exception);
        }
    }
}
