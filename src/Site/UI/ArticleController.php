<?php

declare(strict_types=1);

namespace App\Site\UI;

use App\Blog\Domain\ArticleLoadException;
use App\Blog\Domain\ArticleRepository;
use App\Blog\Domain\ArticlesLoadException;
use App\Blog\Domain\ArticleTerm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ArticleController extends AbstractController
{
    /**
     * Liste de tous les articles lue dans l'API, du plus récent au plus ancien, filtrée par le paramètre `rubrique`
     * (slug traduit en `section`). API en panne : erreur avec relance, en 503. Rubriques indisponibles : pas de filtre.
     */
    #[Route('/blog', name: 'blog', methods: ['GET'])]
    public function list(Request $request, ArticleRepository $articleRepository): Response
    {
        $section = $request->query->getString('rubrique');
        $section = '' === $section ? null : $section;

        try {
            $articles = $articleRepository->findLatest(\PHP_INT_MAX, $section);
        } catch (ArticlesLoadException $exception) {
            return $this->render('blog/index.html.twig', ['articles' => [], 'sections' => [], 'section' => $section, 'error' => $exception->getMessage()], new Response(status: Response::HTTP_SERVICE_UNAVAILABLE));
        }

        return $this->render('blog/index.html.twig', ['articles' => $articles, 'sections' => $this->sections($articleRepository), 'section' => $section, 'error' => null]);
    }

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

    /**
     * @return list<ArticleTerm>
     */
    private function sections(ArticleRepository $articleRepository): array
    {
        try {
            return $articleRepository->sections();
        } catch (ArticlesLoadException) {
            // La liste reste l'essentiel : sans les rubriques, le filtre disparaît
            return [];
        }
    }
}
