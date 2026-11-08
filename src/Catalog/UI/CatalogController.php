<?php

declare(strict_types=1);

namespace App\Catalog\UI;

use App\Catalog\Application\ShowCatalogPage;
use App\Shared\UI\SiteIdentityProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CatalogController extends AbstractController
{
    #[Route('/catalogue/{path}', name: 'catalog', requirements: ['path' => '[a-z0-9-]+(?:/[a-z0-9-]+){0,2}'], defaults: ['path' => ''], methods: ['GET'])]
    public function __invoke(Request $request, ShowCatalogPage $showCatalogPage, SiteIdentityProvider $siteIdentity, string $path): Response
    {
        $rawQuery = $request->server->getString('QUERY_STRING');
        $state = CatalogUrlState::fromQueryString($rawQuery, $siteIdentity->salesEnabled());
        $canonicalQuery = $state->queryString();
        if ('' !== $rawQuery && '?'.$rawQuery !== $canonicalQuery) {
            return $this->redirect($this->generateUrl('catalog', ['path' => $path]).$canonicalQuery, Response::HTTP_MOVED_PERMANENTLY);
        }

        $page = $showCatalogPage($path, $state->query);
        if (null === $page) {
            throw $this->createNotFoundException();
        }

        return $this->render('catalog/index.html.twig', [
            'categoryPath' => $page->categoryPath,
            'state' => $state,
            'result' => $page->result,
            'error' => $page->errorMessage,
        ], new Response(status: null === $page->errorMessage ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE));
    }
}
