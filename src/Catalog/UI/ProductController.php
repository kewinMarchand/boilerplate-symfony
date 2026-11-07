<?php

declare(strict_types=1);

namespace App\Catalog\UI;

use App\Catalog\Domain\CatalogRepository;
use App\Catalog\Domain\ProductLoadException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{
    /**
     * Fiche d'un produit lue dans l'API. Produit inconnu de l'API : 404. API en panne : titre de repli et erreur, en 503.
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

        return $this->render('catalog/product.html.twig', ['detail' => $detail, 'error' => null]);
    }
}
