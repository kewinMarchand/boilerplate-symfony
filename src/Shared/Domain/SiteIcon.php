<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Icône du site (favicons) tirée par l'API de l'image saisie dans l'admin : adresses absolues des PNG de 32, 180, 192
 * et 512 px.
 */
final readonly class SiteIcon
{
    public function __construct(
        public string $png32,
        public string $png180,
        public string $png192,
        public string $png512,
    ) {
    }
}
