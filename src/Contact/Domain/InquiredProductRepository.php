<?php

declare(strict_types=1);

namespace App\Contact\Domain;

interface InquiredProductRepository
{
    /**
     * null si le produit est inconnu ou si l'API ne répond pas : la page de contact reste alors un formulaire simple.
     */
    public function find(string $slug): ?InquiredProduct;
}
