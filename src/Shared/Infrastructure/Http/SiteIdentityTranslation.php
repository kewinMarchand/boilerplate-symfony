<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\SiteIdentity;
use App\Shared\Domain\SiteTheme;

final class SiteIdentityTranslation
{
    private const array COLOR_SCHEMES = ['auto', 'light', 'dark'];

    public static function identity(mixed $payload): SiteIdentity
    {
        $identity = ApiPayload::object($payload, 'siteIdentity');
        $colorScheme = ApiPayload::string($identity, 'defaultColorScheme');
        if (!\in_array($colorScheme, self::COLOR_SCHEMES, true)) {
            throw new InvalidApiPayloadException('defaultColorScheme', 'auto, light ou dark');
        }

        return new SiteIdentity(
            ApiPayload::string($identity, 'name'),
            ApiPayload::string($identity, 'tagline'),
            ApiPayload::image($identity, 'logo'),
            $colorScheme,
            null === ($identity['theme'] ?? null) ? null : self::theme(ApiPayload::object($identity['theme'], 'theme')),
        );
    }

    /**
     * @param array<string, mixed> $theme
     */
    private static function theme(array $theme): SiteTheme
    {
        return new SiteTheme(
            self::tokens(ApiPayload::object($theme['light'] ?? null, 'theme.light'), 'theme.light'),
            self::tokens(ApiPayload::object($theme['dark'] ?? null, 'theme.dark'), 'theme.dark'),
        );
    }

    /**
     * Les couleurs finissent dans un bloc <style> : seule la forme `#rrggbb` passe.
     *
     * @param array<string, mixed> $tokens
     *
     * @return array<string, string>
     */
    private static function tokens(array $tokens, string $path): array
    {
        $colors = [];
        foreach (SiteTheme::TOKENS as $token) {
            $color = ApiPayload::string($tokens, $token);
            if (1 !== preg_match('/^#[0-9a-f]{6}$/i', $color)) {
                throw new InvalidApiPayloadException($path.'.'.$token, 'une couleur #rrggbb');
            }
            $colors[$token] = $color;
        }

        return $colors;
    }
}
