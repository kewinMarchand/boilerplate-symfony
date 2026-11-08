<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Security;

use App\Shared\Infrastructure\Security\SiteCsp;
use Kewinmarchand\Csp\Symfony\CspResponseSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SiteCspTest extends TestCase
{
    public function testAllowsTheApiAndPointsToTheReportRoute(): void
    {
        $csp = $this->policy(SiteCsp::subscriber('http://localhost:8091'));

        self::assertStringContainsString("connect-src 'self' http://localhost:8091", $csp);
        self::assertStringContainsString("img-src 'self' data: https: http://localhost:8091", $csp);
        self::assertStringContainsString("media-src 'self' http://localhost:8091", $csp);
        self::assertStringContainsString('report-uri /api/csp-report', $csp);
        self::assertStringNotContainsString('matomo', $csp);
    }

    public function testAddsMatomoAndGlitchtipOnlyWhenConfigured(): void
    {
        $csp = $this->policy(SiteCsp::subscriber(
            'http://localhost:8091',
            'https://matomo.exemple.com/',
            'https://cle@erreurs.exemple.com/2',
        ));

        self::assertStringContainsString("script-src 'self' 'unsafe-inline' https://matomo.exemple.com", $csp);
        self::assertStringContainsString(
            "connect-src 'self' http://localhost:8091 https://matomo.exemple.com https://erreurs.exemple.com",
            $csp,
        );
        self::assertStringContainsString("media-src 'self' http://localhost:8091", $csp);
    }

    public function testStartupLine(): void
    {
        self::assertSame('[boilerplate-symfony] serveur démarré (prod)', SiteCsp::startupLine('prod'));
    }

    private function policy(CspResponseSubscriber $subscriber): string
    {
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );
        $subscriber->onResponse($event);

        return (string) $event->getResponse()->headers->get('Content-Security-Policy-Report-Only');
    }
}
