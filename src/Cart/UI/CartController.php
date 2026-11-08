<?php

declare(strict_types=1);

namespace App\Cart\UI;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CartController extends AbstractController
{
    /**
     * Le panier n'existe que dans le navigateur : le serveur rend le titre et le fil d'Ariane, le contrôleur Stimulus `cart` le contenu.
     */
    #[Route('/panier', name: 'cart', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('cart/index.html.twig');
    }
}
