<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Identité du site administrée dans l'API (section 22 de la spec commune) : nom, accroche, logo, thème servi par défaut
 * et couleurs de marque, null tant qu'aucune n'est saisie, plus l'image de partage et l'icône (section 26 et 22), et la vente
 * en ligne (section 27) : coupée, ni prix ni panier, une demande de renseignement à la place. Langues du site (section 29),
 * la première étant la langue par défaut.
 */
final readonly class SiteIdentity
{
    /**
     * @param 'auto'|'light'|'dark'  $defaultColorScheme
     * @param non-empty-list<string> $locales            codes BCP 47 courts, la langue par défaut en tête
     */
    public function __construct(
        public string $name,
        public string $tagline,
        public ?Image $logo,
        public string $defaultColorScheme,
        public ?SiteTheme $theme,
        public ?Image $shareImage,
        public ?SiteIcon $icon,
        public bool $salesEnabled,
        public array $locales,
    ) {
    }
}
