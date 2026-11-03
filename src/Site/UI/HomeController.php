<?php

declare(strict_types=1);

namespace App\Site\UI;

use App\Blog\Domain\ArticleRepository;
use App\Blog\Domain\ArticlesLoadException;
use App\Content\Domain\SlideRepository;
use App\Content\Domain\SlidesLoadException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    private const int LATEST_ARTICLES = 3;

    #[Route('/', name: 'home', methods: ['GET'])]
    public function __invoke(ArticleRepository $articleRepository, SlideRepository $slideRepository): Response
    {
        try {
            $slides = $slideRepository->findAll();
            $slidesError = null;
        } catch (SlidesLoadException $exception) {
            $slides = [];
            $slidesError = $exception->getMessage();
        }

        try {
            return $this->render('home/index.html.twig', [
                'slides' => $slides,
                'slides_error' => $slidesError,
                'articles' => $articleRepository->findLatest(self::LATEST_ARTICLES),
                'articles_error' => null,
            ]);
        } catch (ArticlesLoadException $exception) {
            return $this->render('home/index.html.twig', [
                'slides' => $slides,
                'slides_error' => $slidesError,
                'articles' => [],
                'articles_error' => $exception->getMessage(),
            ]);
        }
    }
}
