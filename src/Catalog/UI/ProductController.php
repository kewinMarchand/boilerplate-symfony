<?php

declare(strict_types=1);

namespace App\Catalog\UI;

use App\Catalog\Domain\CatalogLoadException;
use App\Catalog\Domain\CatalogRepository;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductDetail;
use App\Catalog\Domain\ProductLoadException;
use App\Shared\UI\SiteIdentityProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    private const int SIMILAR_LIMIT = 8;

    /**
     * Fiche d'un produit lue dans l'API. Produit inconnu de l'API : 404. API en panne : titre de repli et erreur, en 503.
     * Le bouton d'achat vient du module panier, ou la demande de renseignement du module contact quand la vente en ligne est
     * coupée : la fiche reçoit son gabarit sans le connaître.
     */
    #[Route('/produit/{slug}', name: 'product', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function show(string $slug, Request $request, CatalogRepository $catalogRepository, SiteIdentityProvider $siteIdentity): Response
    {
        try {
            $detail = $catalogRepository->findProduct($slug);
        } catch (ProductLoadException $exception) {
            return $this->render('catalog/product.html.twig', ['detail' => null, 'error' => $exception->getMessage()], new Response(status: Response::HTTP_SERVICE_UNAVAILABLE));
        }

        if (null === $detail) {
            throw $this->createNotFoundException();
        }

        $salesEnabled = $siteIdentity->salesEnabled();

        return $this->render('catalog/product.html.twig', [
            'detail' => $detail,
            'similar' => $this->similar($catalogRepository, $detail->product),
            'purchase' => $salesEnabled ? 'cart/_add_to_cart.html.twig' : 'contact/_inquiry_link.html.twig',
            'translations' => $salesEnabled ? $this->translations($catalogRepository, $detail, $request->getLocale()) : [],
            'error' => null,
        ]);
    }

    /**
     * Slug et nom du produit dans chaque langue du site, gardés par la ligne du panier pour s'afficher dans la langue de la page
     * du panier ; aucun sur un site monolingue. Une langue que l'API ne sert pas est omise.
     *
     * @return list<array{locale: string, slug: string, name: string}>
     */
    private function translations(CatalogRepository $catalogRepository, ProductDetail $detail, string $locale): array
    {
        $translations = [];
        foreach ($detail->alternates as $alternateLocale => $slug) {
            try {
                $product = $alternateLocale === $locale ? $detail->product : $catalogRepository->findProduct($slug, $alternateLocale)?->product;
            } catch (ProductLoadException) {
                $product = null;
            }
            if (null !== $product) {
                $translations[] = ['locale' => $alternateLocale, 'slug' => $product->slug, 'name' => $product->name];
            }
        }

        return $translations;
    }

    /**
     * @return list<Product>
     */
    private function similar(CatalogRepository $catalogRepository, Product $product): array
    {
        try {
            return $catalogRepository->similar($product, self::SIMILAR_LIMIT);
        } catch (CatalogLoadException) {
            // La fiche reste l'essentiel : sans la liste, la section disparaît
            return [];
        }
    }
}
