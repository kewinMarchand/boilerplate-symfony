<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Les douze jetons de couleur de marque, calculés par l'API, en clair et en sombre (`#rrggbb`).
 */
final readonly class SiteTheme
{
    public const array TOKENS = [
        'primary',
        'primary-dark',
        'on-primary',
        'primary-surface-subtle',
        'primary-surface',
        'primary-surface-strong',
        'secondary',
        'secondary-dark',
        'on-secondary',
        'secondary-surface-subtle',
        'secondary-surface',
        'secondary-surface-strong',
    ];

    /**
     * @param array<string, string> $light jeton => couleur, dans l'ordre de TOKENS
     * @param array<string, string> $dark  jeton => couleur, dans l'ordre de TOKENS
     */
    public function __construct(
        public array $light,
        public array $dark,
    ) {
    }
}
