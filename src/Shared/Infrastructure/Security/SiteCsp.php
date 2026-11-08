<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use Kewinmarchand\Csp\Csp;
use Kewinmarchand\Csp\Symfony\CspResponseSubscriber;

/**
 * CSP Report-Only du site, par le paquet kewinmarchand/csp : seules les origines que le site charge réellement, selon ses
 * variables d'environnement. Seule classe du projet qui importe le paquet (make arch-wrappers).
 */
final class SiteCsp
{
    private const string APP = 'boilerplate-symfony';

    /**
     * Fabrique de l'abonné qui pose l'en-tête (config/services.yaml) : l'API, qui sert aussi les images et l'audio de contenu, plus Matomo (script, appels, pixel) et GlitchTip
     * (envoi des erreurs du navigateur) quand ils sont configurés.
     */
    public static function subscriber(string $apiUrl, string $matomoUrl = '', string $glitchtipDsn = ''): CspResponseSubscriber
    {
        return CspResponseSubscriber::create($apiUrl, self::extra($apiUrl, $matomoUrl, $glitchtipDsn));
    }

    /** Ligne écrite au démarrage du conteneur (docker/startup.php), pour retrouver le bon fichier de log chez l'hébergeur. */
    public static function startupLine(string $environment): string
    {
        return Csp::startupLine(self::APP, $environment);
    }

    /** @return array<string, list<string>> */
    private static function extra(string $apiUrl, string $matomoUrl, string $glitchtipDsn): array
    {
        $api = self::originOf($apiUrl);
        $extra = ['img-src' => [$api], 'media-src' => [$api]];
        if ('' !== $matomoUrl) {
            $matomo = self::originOf($matomoUrl);
            $extra = [...$extra, 'script-src' => [$matomo], 'connect-src' => [$matomo], 'img-src' => [$api, $matomo]];
        }
        if ('' !== $glitchtipDsn) {
            $extra['connect-src'] = [...$extra['connect-src'] ?? [], self::originOf($glitchtipDsn)];
        }

        return $extra;
    }

    private static function originOf(string $address): string
    {
        $parts = parse_url($address);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').$port;
    }
}
