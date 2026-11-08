<?php

declare(strict_types=1);

namespace App\Shared\UI\Twig;

use App\Shared\Domain\SiteIdentity;
use App\Shared\UI\SiteIdentityProvider;
use App\Shared\UI\ThemeStyle;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

final readonly class SiteIdentityExtension
{
    public function __construct(
        private SiteIdentityProvider $provider,
        private RequestStack $requestStack,
    ) {
    }

    #[AsTwigFunction('site_identity')]
    public function identity(): SiteIdentity
    {
        return $this->provider->current();
    }

    /**
     * Feuille des couleurs de marque et son nonce, null sans couleurs saisies. Le nonce est laissé dans la requête
     * (`csp_style_nonce`), que la CSP ajoute à style-src (App\Shared\Infrastructure\Security\StyleNonceCspSubscriber) ;
     * Turbo le lit aussi dans `<meta name="csp-nonce">` pour la feuille de sa barre de progression.
     *
     * @return array{css: string, nonce: string}|null
     */
    #[AsTwigFunction('site_theme_style')]
    public function themeStyle(): ?array
    {
        $theme = $this->provider->current()->theme;
        if (null === $theme) {
            return null;
        }
        $nonce = base64_encode(random_bytes(16));
        $this->requestStack->getMainRequest()?->attributes->set('csp_style_nonce', $nonce);

        return ['css' => ThemeStyle::css($theme), 'nonce' => $nonce];
    }
}
