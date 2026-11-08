<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Identité du site administrée dans l'API (section 22 de la spec commune) : nom, accroche, logo, thème servi par défaut
 * et couleurs de marque, null tant qu'aucune n'est saisie.
 */
final readonly class SiteIdentity
{
    /**
     * @param 'auto'|'light'|'dark' $defaultColorScheme
     */
    public function __construct(
        public string $name,
        public string $tagline,
        public ?Image $logo,
        public string $defaultColorScheme,
        public ?SiteTheme $theme,
    ) {
    }
}
