<?php

declare(strict_types=1);

namespace App\Contact\Domain;

/**
 * Produit visé par une demande de renseignement : de quoi le nommer, le lier à sa fiche et l'envoyer avec le message.
 */
final readonly class InquiredProduct
{
    public function __construct(
        public string $slug,
        public string $name,
    ) {
    }
}
