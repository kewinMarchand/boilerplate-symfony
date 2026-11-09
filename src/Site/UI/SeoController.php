<?php

declare(strict_types=1);

namespace App\Site\UI;

use App\Blog\Domain\ArticleRepository;
use App\Blog\Domain\ArticlesLoadException;
use App\Catalog\Domain\CatalogLoadException;
use App\Catalog\Domain\CatalogRepository;
use App\Catalog\Domain\CategoryPath;
use App\Shared\UI\Locale\PageLocale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SeoController extends AbstractController
{
    #[Route('/robots.txt', name: 'seo_robots', methods: ['GET'], format: 'txt')]
    public function robots(): Response
    {
        return $this->render('seo/robots.txt.twig');
    }

    /**
     * Site monolingue : une entrée par adresse. Multilingue (section 29 bis de la spec commune) : une entrée par adresse et par
     * langue, avec ses équivalents en `xhtml:link` (`groups`) ; produits et articles associés d'une langue à l'autre par
     * identifiant, catégories par leurs `alternates`.
     *
     * @param array{navigation: array<string, list<array{route: string, label: string}>>} $site
     */
    #[Route('/sitemap.xml', name: 'seo_sitemap', methods: ['GET'], format: 'xml')]
    public function sitemap(ArticleRepository $articleRepository, CatalogRepository $catalogRepository, PageLocale $pageLocale, #[Autowire('%site%')] array $site): Response
    {
        if ($pageLocale->isMultilingual()) {
            return $this->render('seo/sitemap.xml.twig', [
                'groups' => $this->localizedGroups($articleRepository, $catalogRepository, $pageLocale->locales(), $site),
                'default_locale' => $pageLocale->defaultLocale(),
            ]);
        }

        try {
            $productSlugs = $catalogRepository->productSlugs();
        } catch (CatalogLoadException) {
            // API indisponible : le sitemap reste servi, sans les fiches produit
            $productSlugs = [];
        }

        try {
            $articles = $articleRepository->findLatest(\PHP_INT_MAX);
        } catch (ArticlesLoadException) {
            // API indisponible : le sitemap reste servi, sans les fiches
            $articles = [];
        }

        return $this->render('seo/sitemap.xml.twig', ['articles' => $articles, 'product_slugs' => $productSlugs, 'groups' => []]);
    }

    #[Route('/manifest.webmanifest', name: 'seo_manifest', methods: ['GET'])]
    public function manifest(): Response
    {
        return $this->render('seo/manifest.webmanifest.twig', [], new Response(headers: ['Content-Type' => 'application/manifest+json']));
    }

    /**
     * Chemins de chaque adresse dans les langues où elle existe, par langue.
     *
     * @param non-empty-list<string>                                                      $locales
     * @param array{navigation: array<string, list<array{route: string, label: string}>>} $site
     *
     * @return list<array<string, string>>
     */
    private function localizedGroups(ArticleRepository $articleRepository, CatalogRepository $catalogRepository, array $locales, array $site): array
    {
        $groups = [];
        foreach (array_merge(...array_values($site['navigation'])) as $item) {
            $groups[] = array_combine($locales, array_map(fn (string $locale): string => $this->generateUrl($item['route'], ['_locale' => $locale]), $locales));
        }

        $articles = [];
        $products = [];
        foreach ($locales as $locale) {
            try {
                foreach ($articleRepository->findLatest(\PHP_INT_MAX, null, $locale) as $article) {
                    $articles[$article->id][$locale] = $this->generateUrl('article', ['slug' => $article->slug, '_locale' => $locale]);
                }
            } catch (ArticlesLoadException) {
                // API indisponible : le sitemap reste servi, sans les fiches de cette langue
            }
            try {
                foreach ($catalogRepository->productSlugs($locale) as $id => $slug) {
                    $products[$id][$locale] = $this->generateUrl('product', ['slug' => $slug, '_locale' => $locale]);
                }
            } catch (CatalogLoadException) {
                // API indisponible : le sitemap reste servi, sans les fiches de cette langue
            }
        }

        try {
            $categories = array_map(
                function (CategoryPath $path): array {
                    $paths = [];
                    foreach ($path->alternates() as $locale => $value) {
                        $paths[$locale] = $this->generateUrl('catalog', ['path' => $value, '_locale' => $locale]);
                    }

                    return $paths;
                },
                CategoryPath::all($catalogRepository->categoryTree()),
            );
        } catch (CatalogLoadException) {
            $categories = [];
        }

        return [...$groups, ...array_values($articles), ...array_values($products), ...$categories];
    }
}
