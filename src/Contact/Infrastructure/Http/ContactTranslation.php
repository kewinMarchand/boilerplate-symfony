<?php

declare(strict_types=1);

namespace App\Contact\Infrastructure\Http;

use App\Contact\Domain\ContactMessage;
use App\Contact\Domain\InquiredProduct;
use App\Shared\Infrastructure\Http\ApiPayload;

final class ContactTranslation
{
    /**
     * `product` (slug) seulement pour une demande de renseignement.
     *
     * @return array{name: string, email: string, message: string, product?: string}
     */
    public static function request(ContactMessage $message): array
    {
        $body = ['name' => $message->name, 'email' => $message->email, 'message' => $message->message];

        return null === $message->product ? $body : [...$body, 'product' => $message->product];
    }

    /**
     * Fiche produit de l'API (`GET /api/products/{slug}`), réduite à ce que la demande de renseignement affiche.
     */
    public static function product(mixed $payload): InquiredProduct
    {
        $product = ApiPayload::object($payload, 'product');

        return new InquiredProduct(ApiPayload::string($product, 'slug'), ApiPayload::string($product, 'name'));
    }

    /**
     * Violations d'un 422 de l'API (`violations[].propertyPath`, `message`).
     *
     * @return list<array{field: string, message: string}>
     */
    public static function violations(mixed $payload): array
    {
        return array_map(
            static fn (array $violation): array => ['field' => ApiPayload::string($violation, 'propertyPath'), 'message' => ApiPayload::string($violation, 'message')],
            ApiPayload::objects(ApiPayload::object($payload, 'contact_message')['violations'] ?? null, 'violations'),
        );
    }
}
