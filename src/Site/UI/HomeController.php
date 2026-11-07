<?php

declare(strict_types=1);

namespace App\Site\UI;

use App\Blog\Domain\ArticleRepository;
use App\Blog\Domain\ArticlesLoadException;
use App\Catalog\Domain\CatalogRepository;
use App\Catalog\Domain\FeaturedProductsLoadException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    private const int LATEST_ARTICLES = 3;

    #[Route('/', name: 'home', methods: ['GET'])]
    public function __invoke(ArticleRepository $articleRepository, CatalogRepository $catalogRepository): Response
    {
        try {
            $featured = $catalogRepository->featured();
            $featuredError = null;
        } catch (FeaturedProductsLoadException $exception) {
            $featured = [];
            $featuredError = $exception->getMessage();
        }

        try {
            return $this->render('home/index.html.twig', [
                'featured' => $featured,
                'featured_error' => $featuredError,
                'articles' => $articleRepository->findLatest(self::LATEST_ARTICLES),
                'articles_error' => null,
            ]);
        } catch (ArticlesLoadException $exception) {
            return $this->render('home/index.html.twig', [
                'featured' => $featured,
                'featured_error' => $featuredError,
                'articles' => [],
                'articles_error' => $exception->getMessage(),
            ]);
        }
    }
}
