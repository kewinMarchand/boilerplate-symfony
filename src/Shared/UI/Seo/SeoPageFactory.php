<?php

declare(strict_types=1);

namespace App\Shared\UI\Seo;

use App\Shared\UI\SiteIdentityProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Seul point de construction des métadonnées SEO d'une page, à partir de l'identité et de la config du site
 * et des données fournies par la vue.
 */
final readonly class SeoPageFactory
{
    private const int JSON_FLAGS = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_THROW_ON_ERROR;

    /**
     * @param array{name: string, description: string, url: string, navigation: array<string, list<array{route: string, label: string}>>} $site
     */
    public function __construct(
        #[Autowire('%site%')]
        private array $site,
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
        private SiteIdentityProvider $siteIdentity,
    ) {
    }

    /**
     * @param array{
     *     title?: string,
     *     description?: string,
     *     breadcrumb?: list<array{label: string, path?: string}>,
     *     canonical?: string|false,
     *     robots?: string,
     *     json_ld?: list<array<string, mixed>>,
     *     prev?: string|null,
     *     next?: string|null,
     *     image?: array{url: string, width: int, height: int, alt: string},
     *     published_time?: string,
     * } $options chemins relatifs à l'URL du site ; image : adresse absolue (image servie par l'API) ; published_time : page d'article, AAAA-MM-JJ
     */
    public function create(array $options = []): SeoPage
    {
        $siteUrl = $this->site['url'];
        $request = $this->requestStack->getMainRequest();
        $route = $request?->attributes->get('_route');
        $route = \is_string($route) ? $route : null;
        $title = $options['title'] ?? null;
        $identity = $this->siteIdentity->current();

        $canonicalPath = $options['canonical'] ?? (null !== $route ? $this->currentPath($route) : false);
        $canonical = false === $canonicalPath ? null : $siteUrl.$canonicalPath;
        $breadcrumb = Breadcrumb::fromTrail($options['breadcrumb'] ?? $this->defaultTrail($route, $title));

        $jsonLd = $options['json_ld'] ?? [];
        if ('home' === $route) {
            $jsonLd = [...$this->organizationJsonLd($siteUrl, $identity->name), ...$jsonLd];
        }
        $breadcrumbJsonLd = $breadcrumb->jsonLd($siteUrl, $canonical ?? $siteUrl.($request?->getPathInfo() ?? '/'));
        if (null !== $breadcrumbJsonLd) {
            $jsonLd[] = $breadcrumbJsonLd;
        }

        return new SeoPage(
            null === $title ? $identity->name : $title.' · '.$identity->name,
            $options['description'] ?? ('' !== $identity->tagline ? $identity->tagline : $this->site['description']),
            $canonical,
            $options['robots'] ?? 'index, follow',
            $options['image']['url'] ?? $siteUrl.'/og-image.jpg',
            $options['image']['alt'] ?? $identity->name.' : jardin tropical luxuriant',
            $breadcrumb,
            array_map(static fn (array $data): string => json_encode($data, self::JSON_FLAGS), $jsonLd),
            isset($options['prev']) ? $siteUrl.$options['prev'] : null,
            isset($options['next']) ? $siteUrl.$options['next'] : null,
            $options['image']['width'] ?? 1200,
            $options['image']['height'] ?? 630,
            $options['published_time'] ?? null,
        );
    }

    private function currentPath(string $route): string
    {
        $parameters = $this->requestStack->getMainRequest()?->attributes->get('_route_params');

        return $this->urlGenerator->generate($route, \is_array($parameters) ? $parameters : []);
    }

    /**
     * @return list<array{label: string, path?: string}>
     */
    private function defaultTrail(?string $route, ?string $title): array
    {
        if ('home' === $route) {
            return [];
        }

        foreach ($this->site['navigation'] as $items) {
            foreach ($items as $item) {
                if ($item['route'] === $route) {
                    return [['label' => $item['label']]];
                }
            }
        }

        return null === $title ? [] : [['label' => $title]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function organizationJsonLd(string $siteUrl, string $name): array
    {
        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $name,
                'url' => $siteUrl.'/',
                'logo' => $siteUrl.'/icon-512.png',
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $name,
                'url' => $siteUrl.'/',
                'inLanguage' => 'fr',
            ],
        ];
    }
}
