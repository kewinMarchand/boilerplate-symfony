<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Remplace l'API en test par des réponses réelles capturées sur l'API (tests/Fixtures/api/),
 * avec X-Scenario comme l'API : `error` répond 500, `empty` répond une liste vide. `identite-logo`, `identite-couleurs` et
 * `identite-icone` servent une identité du site avec logo, avec couleurs ou avec icône et image de partage, `sans-vente` une
 * identité à vente en ligne coupée, et `metas` une
 * fiche produit ou article avec métadonnées saisies et un titre saisi pour le blog, que l'API locale n'a pas par défaut.
 */
final class ApiMockResponseFactory
{
    private const string FIXTURES = __DIR__.'/../Fixtures/api/';

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): MockResponse
    {
        $headers = \is_array($options['normalized_headers'] ?? null) ? $options['normalized_headers'] : [];
        $header = \is_array($headers['x-scenario'] ?? null) ? ($headers['x-scenario'][0] ?? null) : null;
        $scenario = \is_string($header) ? trim(substr($header, (int) strpos($header, ':') + 1)) : '';
        if ('error' === $scenario) {
            return new MockResponse('{}', ['http_code' => 500]);
        }

        $path = (string) parse_url($url, \PHP_URL_PATH);
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);

        return match ($path) {
            '/api/tasks' => self::fixture('empty' === $scenario ? null : 'tasks'),
            '/api/articles' => self::fixture(match (true) {
                'empty' === $scenario => null,
                !isset($query['section']) => 'articles',
                'conseils' === $query['section'] => 'articles-conseils',
                default => null,
            }),
            '/api/article-sections' => self::fixture('empty' === $scenario ? null : 'article-sections'),
            '/api/page-metas' => self::fixture(match ($scenario) {
                'empty' => null,
                'metas' => 'page-metas-metas',
                default => 'page-metas',
            }),
            '/api/articles/un-carrousel-accessible-sans-dependance' => self::fixture('article-'.basename($path), 'metas' === $scenario),
            '/api/categories' => self::fixture('categories'),
            '/api/products' => self::fixture(match (true) {
                'empty' === $scenario, isset($query['priceMax']) => 'products-empty',
                \in_array($query['category'] ?? null, ['feuillages', 'monstera', 'plantes-aquatiques'], true) => 'products-'.self::scalar($query['category']),
                default => 'products',
            }),
            '/api/product-facets' => self::fixture(match (true) {
                'empty' === $scenario, isset($query['priceMax']) => 'product-facets-empty',
                \in_array($query['category'] ?? null, ['feuillages', 'plantes-aquatiques'], true) => 'product-facets-'.self::scalar($query['category']),
                default => 'product-facets',
            }),
            '/api/contact_messages' => self::contact($options),
            '/api/featured-products' => self::fixture('empty' === $scenario ? null : 'featured-products'),
            '/api/products/monstera-deliciosa' => self::fixture('product-'.basename($path), 'metas' === $scenario),
            '/api/products/monstera-adansonii' => self::fixture('product-'.basename($path)),
            '/api/site-identity' => self::fixture(match ($scenario) {
                'identite-logo' => 'site-identity-logo',
                'identite-couleurs' => 'site-identity-couleurs',
                'identite-icone' => 'site-identity-icone',
                'sans-vente' => 'site-identity-sans-vente',
                default => 'site-identity',
            }),
            '/api/legal_pages/mentions-legales', '/api/legal_pages/donnees-personnelles', '/api/legal_pages/accessibilite' => self::fixture('legal-page-'.basename($path)),
            default => new MockResponse('{}', ['http_code' => 404]),
        };
    }

    /**
     * @param bool $withMetas fiche avec les métadonnées de metas.json à la place des chaînes vides capturées
     */
    private static function fixture(?string $name, bool $withMetas = false): MockResponse
    {
        $body = null === $name ? '[]' : (string) file_get_contents(self::FIXTURES.$name.'.json');
        if ($withMetas) {
            $body = (string) json_encode([...(array) json_decode($body, true), ...(array) json_decode((string) file_get_contents(self::FIXTURES.'metas.json'), true)]);
        }

        return new MockResponse($body, [
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }

    /**
     * Le nom « Refus » simule un refus de l'API, comme son 422 de validation.
     *
     * @param array<string, mixed> $options
     */
    private static function contact(array $options): MockResponse
    {
        $body = \is_string($options['body'] ?? null) ? $options['body'] : '';
        if (!str_contains($body, '"name":"Refus"')) {
            return new MockResponse('{}', ['http_code' => 201, 'response_headers' => ['content-type' => 'application/json']]);
        }

        return new MockResponse((string) json_encode(['status' => 422, 'violations' => [
            ['propertyPath' => 'email', 'message' => 'Cette adresse e-mail est refusée par le destinataire.'],
        ]]), ['http_code' => 422, 'response_headers' => ['content-type' => 'application/json']]);
    }

    private static function scalar(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
