<?php

declare(strict_types=1);

namespace App\Site\UI;

use App\Content\Domain\LegalPageLoadException;
use App\Content\Domain\LegalPageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegalController extends AbstractController
{
    #[Route('/mentions-legales', name: 'legal_notice', methods: ['GET'])]
    public function notice(LegalPageRepository $legalPageRepository): Response
    {
        return $this->renderLegalPage($legalPageRepository, 'legal/notice.html.twig', 'mentions-legales');
    }

    #[Route('/donnees-personnelles', name: 'legal_privacy', methods: ['GET'])]
    public function privacy(LegalPageRepository $legalPageRepository): Response
    {
        return $this->renderLegalPage($legalPageRepository, 'legal/privacy.html.twig', 'donnees-personnelles');
    }

    #[Route('/accessibilite', name: 'legal_accessibility', methods: ['GET'])]
    public function accessibility(LegalPageRepository $legalPageRepository): Response
    {
        return $this->renderLegalPage($legalPageRepository, 'legal/accessibility.html.twig', 'accessibilite');
    }

    #[Route('/plan-du-site', name: 'legal_sitemap', methods: ['GET'])]
    public function sitemap(): Response
    {
        return $this->render('legal/sitemap.html.twig');
    }

    /**
     * Page inconnue de l'API : 404. API en panne : la page garde son titre et affiche l'erreur, en 503.
     */
    private function renderLegalPage(LegalPageRepository $legalPageRepository, string $template, string $slug): Response
    {
        try {
            $page = $legalPageRepository->find($slug);
        } catch (LegalPageLoadException $exception) {
            return $this->render($template, ['page' => null, 'error' => $exception->getMessage()], new Response(status: Response::HTTP_SERVICE_UNAVAILABLE));
        }

        if (null === $page) {
            throw $this->createNotFoundException();
        }

        return $this->render($template, ['page' => $page, 'error' => null]);
    }
}
