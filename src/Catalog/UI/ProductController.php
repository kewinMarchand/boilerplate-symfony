<?php

declare(strict_types=1);

namespace App\Catalog\UI;

use App\Catalog\Domain\CatalogLoadException;
use App\Catalog\Domain\CatalogRepository;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductLoadException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    private const int SIMILAR_LIMIT = 8;

    /**
     * Fiche d'un produit lue dans l'API. Produit inconnu de l'API : 404. API en panne : titre de repli et erreur, en 503.
     * Le bouton d'achat vient du module panier : la fiche reçoit son gabarit sans le connaître.
     */
    #[Route('/produit/{slug}', name: 'product', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function show(string $slug, CatalogRepository $catalogRepository): Response
    {
        try {
            $detail = $catalogRepository->findProduct($slug);
        } catch (ProductLoadException $exception) {
            return $this->render('catalog/product.html.twig', ['detail' => null, 'error' => $exception->getMessage()], new Response(status: Response::HTTP_SERVICE_UNAVAILABLE));
        }

        if (null === $detail) {
            throw $this->createNotFoundException();
        }

        return $this->render('catalog/product.html.twig', [
            'detail' => $detail,
            'similar' => $this->similar($catalogRepository, $detail->product),
            'purchase' => 'cart/_add_to_cart.html.twig',
            'error' => null,
        ]);
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
