<?php

declare(strict_types=1);

namespace App\Shared\UI;

use App\Shared\Domain\SiteTheme;

/**
 * Feuille qui redéfinit les jetons de couleur de marque de boilerplates-ui, sous ses propres sélecteurs du clair et du
 * sombre (section 20 de la spec commune), jamais en mode renforcé.
 */
final class ThemeStyle
{
    private const string NOT_ENHANCED = ':not([data-a11y-mode="enhanced"])';

    public static function css(SiteTheme $theme): string
    {
        $light = self::declarations($theme->light);
        $dark = self::declarations($theme->dark);

        return ':root'.self::NOT_ENHANCED.'{'.$light.'}'
            .'[data-color-scheme="dark"]'.self::NOT_ENHANCED.'{'.$dark.'}'
            .'@media (prefers-color-scheme: dark){[data-color-scheme="auto"]'.self::NOT_ENHANCED.'{'.$dark.'}}';
    }

    /**
     * @param array<string, string> $tokens
     */
    private static function declarations(array $tokens): string
    {
        $declarations = '';
        foreach ($tokens as $token => $color) {
            $declarations .= '--bui-color-'.$token.':'.$color.';';
        }

        return $declarations;
    }
}
