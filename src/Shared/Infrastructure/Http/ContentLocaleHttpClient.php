<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\ContentLocale;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Langue de la page passée à l'API sur un site multilingue (section 29 bis de la spec commune) : `locale=<langue>` sur
 * chaque lecture (paramètre plutôt qu'en-tête : clé de cache explicite), `Accept-Language` sur chaque envoi. Une lecture
 * qui passe déjà `locale` (identité dans la langue du préfixe, sitemap) la garde. Site monolingue : aucun changement, la
 * fausse API de la phase @identite de make test-e2e compare l'adresse exacte de certaines lectures.
 */
#[AsDecorator('api.client')]
final class ContentLocaleHttpClient implements HttpClientInterface
{
    use DecoratorTrait;

    public function __construct(HttpClientInterface $client, private readonly ContentLocale $contentLocale)
    {
        $this->client = $client;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $locale = $this->contentLocale->apiLocale();
        if (null === $locale) {
            return $this->client->request($method, $url, $options);
        }

        if ('GET' === $method) {
            $query = \is_array($options['query'] ?? null) ? $options['query'] : [];
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $inUrl);
            if (!isset($query['locale']) && !isset($inUrl['locale'])) {
                $options['query'] = [...$query, 'locale' => $locale];
            }
        } else {
            $headers = \is_array($options['headers'] ?? null) ? $options['headers'] : [];
            $options['headers'] = [...$headers, 'Accept-Language' => $locale];
        }

        return $this->client->request($method, $url, $options);
    }
}
