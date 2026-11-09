<?php

declare(strict_types=1);

namespace App\Catalog\UI;

use App\Catalog\Domain\CatalogLoadException;
use App\Catalog\Domain\CatalogRepository;
use App\Catalog\Domain\Category;
use App\Catalog\Domain\CategoryPath;
use App\Catalog\Domain\CategoryTree;
use App\Catalog\Domain\Price;
use App\Shared\UI\Locale\PageLocale;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * Fonctions Twig du catalogue. L'en-tête de chaque page, pages d'erreur comprises, lit l'arbre : si
 * l'API est indisponible, le menu se replie sur la seule entrée « Catalogue » au lieu de casser la page.
 */
final readonly class CatalogExtension
{
    public function __construct(private CatalogRepository $catalogRepository, private PageLocale $pageLocale)
    {
    }

    #[AsTwigFunction('catalog_tree')]
    public function tree(): CategoryTree
    {
        try {
            return $this->catalogRepository->categoryTree();
        } catch (CatalogLoadException) {
            return new CategoryTree(new Category('', 'Catalogue'));
        }
    }

    /**
     * @return list<CategoryPath>
     */
    #[AsTwigFunction('catalog_paths')]
    public function paths(): array
    {
        return CategoryPath::all($this->tree());
    }

    /**
     * @return list<int|null>
     */
    #[AsTwigFunction('pagination_window')]
    public function paginationWindow(int $current, int $pageCount): array
    {
        return PaginationWindow::pages($current, $pageCount);
    }

    #[AsTwigFilter('price')]
    public function price(Price|int $price): string
    {
        return ($price instanceof Price ? $price : Price::fromCents($price))->format($this->pageLocale->ogLocale());
    }
}
