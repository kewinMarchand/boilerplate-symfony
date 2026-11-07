<?php

declare(strict_types=1);

namespace App\Site\UI;

use App\Blog\Domain\ArticleLoadException;
use App\Blog\Domain\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ArticleController extends AbstractController
{
    /**
     * Fiche d'un article lue dans l'API. Article inconnu de l'API : 404. API en panne : titre de repli et erreur, en 503.
     */
    #[Route('/blog/{slug}', name: 'article', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function show(string $slug, ArticleRepository $articleRepository): Response
    {
        try {
            $detail = $articleRepository->find($slug);
        } catch (ArticleLoadException $exception) {
            return $this->render('blog/article.html.twig', ['detail' => null, 'error' => $exception->getMessage()], new Response(status: Response::HTTP_SERVICE_UNAVAILABLE));
        }

        if (null === $detail) {
            throw $this->createNotFoundException();
        }

        return $this->render('blog/article.html.twig', ['detail' => $detail, 'error' => null]);
    }
}
