<?php

declare(strict_types=1);

namespace App\Contact\Infrastructure\Http;

use App\Contact\Domain\ContactMessage;
use App\Contact\Domain\ContactMessageNotSentException;
use App\Contact\Domain\ContactMessageRejectedException;
use App\Contact\Domain\ContactMessageSender;
use App\Shared\Infrastructure\Http\InvalidApiPayloadException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpContactMessageSender implements ContactMessageSender
{
    private const int UNPROCESSABLE = 422;

    public function __construct(#[Target('api.client')] private HttpClientInterface $apiClient)
    {
    }

    public function send(ContactMessage $message): void
    {
        try {
            $response = $this->apiClient->request('POST', '/api/contact_messages', ['json' => ContactTranslation::request($message)]);
            if (self::UNPROCESSABLE === $response->getStatusCode()) {
                throw new ContactMessageRejectedException(ContactTranslation::violations($response->toArray(false)));
            }
            $response->getContent();
        } catch (ExceptionInterface|InvalidApiPayloadException $exception) {
            throw new ContactMessageNotSentException($exception);
        }
    }
}
