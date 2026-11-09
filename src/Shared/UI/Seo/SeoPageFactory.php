<?php

declare(strict_types=1);

namespace App\Shared\UI\Seo;

use App\Shared\Domain\PageMeta;
use App\Shared\Domain\PageMetaRepository;
use App\Shared\Domain\PageMetasLoadException;
use App\Shared\UI\Locale\PageLocale;
use App\Shared\UI\SiteIdentityProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        private PageMetaRepository $pageMetas,
        private PageLocale $pageLocale,
        private TranslatorInterface $translator,
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
     *     page?: string,
     *     meta_title?: string,
     *     alternates?: array<string, array<string, string>>|null,
     * } $options chemins relatifs à l'URL du site ; image : adresse absolue (image servie par l'API) ; published_time : page d'article, AAAA-MM-JJ ;
     *   page : clé de la page fixe dont le titre et la description saisis dans l'admin remplacent ceux de la vue ;
     *   meta_title : titre saisi dans l'admin (produit, article). Un titre saisi est servi tel quel, sans le nom du site ;
     *   alternates : paramètres de la route de la page dans chaque langue où elle a un équivalent (page détaillée, depuis les
     *   `alternates` de l'API) ; absent ou null, les mêmes paramètres dans toutes les langues (page fixe)
     */
    public function create(array $options = []): SeoPage
    {
        $siteUrl = $this->site['url'];
        $request = $this->requestStack->getMainRequest();
        $route = $request?->attributes->get('_route');
        $route = \is_string($route) ? $route : null;
        $title = $options['title'] ?? null;
        $identity = $this->siteIdentity->current();
        $meta = isset($options['page']) ? $this->pageMeta($options['page']) : null;
        $pageTitle = $meta->title ?? '';
        $enteredTitle = '' !== $pageTitle ? $pageTitle : ($options['meta_title'] ?? '');
        $metaDescription = $meta->description ?? '';
        $shareImage = $identity->shareImage;
        $image = $options['image'] ?? (null === $shareImage ? null : [
            'url' => $shareImage->src,
            'width' => $shareImage->width,
            'height' => $shareImage->height,
            'alt' => '' !== $shareImage->alt ? $shareImage->alt : null,
        ]);

        $canonicalPath = $options['canonical'] ?? (null !== $route ? $this->currentPath($route) : false);
        $canonical = false === $canonicalPath ? null : $siteUrl.$canonicalPath;
        $breadcrumb = Breadcrumb::fromTrail($options['breadcrumb'] ?? $this->defaultTrail($route, $title), $this->translator->trans('nav.home'), $this->urlGenerator->generate('home'));

        $jsonLd = $options['json_ld'] ?? [];
        if ('home' === $route) {
            $jsonLd = [...$this->organizationJsonLd($siteUrl, $identity->name, $identity->icon->png512 ?? $siteUrl.'/icon-512.png', $this->pageLocale->locale()), ...$jsonLd];
        }
        $breadcrumbJsonLd = $breadcrumb->jsonLd($siteUrl, $canonical ?? $siteUrl.($request?->getPathInfo() ?? '/'));
        if (null !== $breadcrumbJsonLd) {
            $jsonLd[] = $breadcrumbJsonLd;
        }

        return new SeoPage(
            match (true) {
                '' !== $enteredTitle => $enteredTitle,
                null === $title => $identity->name,
                default => $title.' · '.$identity->name,
            },
            '' !== $metaDescription ? $metaDescription : ($options['description'] ?? ('' !== $identity->tagline ? $identity->tagline : $this->site['description'])),
            $canonical,
            $options['robots'] ?? 'index, follow',
            $image['url'] ?? $siteUrl.'/og-image.jpg',
            $image['alt'] ?? $this->translator->trans('seo.default_image_alt', ['%site%' => $identity->name]),
            $breadcrumb,
            array_map(static fn (array $data): string => json_encode($data, self::JSON_FLAGS), $jsonLd),
            isset($options['prev']) ? $siteUrl.$options['prev'] : null,
            isset($options['next']) ? $siteUrl.$options['next'] : null,
            $image['width'] ?? 1200,
            $image['height'] ?? 630,
            $options['published_time'] ?? null,
            $this->pageLocale->ogLocale(),
            ...$this->languageVersions($siteUrl, false === $canonicalPath ? null : $route, $options['alternates'] ?? null),
        );
    }

    /**
     * Versions de la page dans chaque langue d'un site multilingue : `hreflang` (plus x-default vers la langue par défaut) et
     * `og:locale:alternate` des langues où elle existe, et liens du sélecteur, vers l'accueil d'une langue sans équivalent. Page
     * sans canonique (erreur) : aucun équivalent.
     *
     * @param array<string, array<string, string>>|null $equivalents
     *
     * @return array{alternates: list<array{hreflang: string, url: string}>, ogLocaleAlternates: list<string>, languages: list<array{locale: string, name: string, href: string, current: bool}>}
     */
    private function languageVersions(string $siteUrl, ?string $route, ?array $equivalents): array
    {
        if (!$this->pageLocale->isMultilingual()) {
            return ['alternates' => [], 'ogLocaleAlternates' => [], 'languages' => []];
        }

        $parameters = $this->requestStack->getMainRequest()?->attributes->get('_route_params');
        $parameters = \is_array($parameters) ? $parameters : [];
        $alternates = [];
        $ogLocaleAlternates = [];
        $languages = [];
        foreach ($this->pageLocale->locales() as $locale) {
            $equivalent = null === $route ? null : (null === $equivalents ? $parameters : ($equivalents[$locale] ?? null));
            $path = null === $equivalent ? null : $this->urlGenerator->generate($route, [...$equivalent, '_locale' => $locale]);
            if (null !== $path) {
                $alternates[] = ['hreflang' => $locale, 'url' => $siteUrl.$path];
                if ($locale !== $this->pageLocale->locale()) {
                    $ogLocaleAlternates[] = $this->pageLocale->ogLocale($locale);
                }
            }
            $languages[] = [
                'locale' => $locale,
                'name' => $this->pageLocale->name($locale),
                'href' => $path ?? $this->urlGenerator->generate('home', ['_locale' => $locale]),
                'current' => $locale === $this->pageLocale->locale(),
            ];
        }

        $default = array_find($alternates, fn (array $alternate): bool => $alternate['hreflang'] === $this->pageLocale->defaultLocale());
        if (null !== $default) {
            $alternates[] = ['hreflang' => 'x-default', 'url' => $default['url']];
        }

        return ['alternates' => $alternates, 'ogLocaleAlternates' => $ogLocaleAlternates, 'languages' => $languages];
    }

    private function pageMeta(string $page): ?PageMeta
    {
        try {
            return $this->pageMetas->all()[$page] ?? null;
        } catch (PageMetasLoadException) {
            // API indisponible : la page garde le titre et la description de la vue
            return null;
        }
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
                    return [['label' => $this->translator->trans($item['label'])]];
                }
            }
        }

        return null === $title ? [] : [['label' => $title]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function organizationJsonLd(string $siteUrl, string $name, string $logo, string $locale): array
    {
        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $name,
                'url' => $siteUrl.'/',
                'logo' => $logo,
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $name,
                'url' => $siteUrl.'/',
                'inLanguage' => $locale,
            ],
        ];
    }
}
