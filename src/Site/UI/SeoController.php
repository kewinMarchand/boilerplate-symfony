<?php

declare(strict_types=1);

namespace App\Site\UI;

use App\Blog\Domain\ArticleRepository;
use App\Blog\Domain\ArticlesLoadException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SeoController extends AbstractController
{
    #[Route('/robots.txt', name: 'seo_robots', methods: ['GET'], format: 'txt')]
    public function robots(): Response
    {
        return $this->render('seo/robots.txt.twig');
    }

    #[Route('/sitemap.xml', name: 'seo_sitemap', methods: ['GET'], format: 'xml')]
    public function sitemap(ArticleRepository $articleRepository): Response
    {
        try {
            $articles = $articleRepository->findLatest(\PHP_INT_MAX);
        } catch (ArticlesLoadException) {
            // API indisponible : le sitemap reste servi, sans les fiches
            $articles = [];
        }

        return $this->render('seo/sitemap.xml.twig', ['articles' => $articles]);
    }

    #[Route('/manifest.webmanifest', name: 'seo_manifest', methods: ['GET'])]
    public function manifest(): Response
    {
        return $this->render('seo/manifest.webmanifest.twig', [], new Response(headers: ['Content-Type' => 'application/manifest+json']));
    }
}
