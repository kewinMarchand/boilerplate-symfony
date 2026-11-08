<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Security;

use App\Shared\Infrastructure\Security\SiteCsp;
use App\Shared\Infrastructure\Security\StyleNonceCspSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class StyleNonceCspSubscriberTest extends TestCase
{
    public function testAddsTheNonceOfTheRenderedStylesToStyleSrc(): void
    {
        $request = new Request();
        $request->attributes->set('csp_style_nonce', 'abc+/=');

        $csp = $this->policy($request);

        self::assertStringContainsString("; style-src 'self' 'unsafe-inline' 'nonce-abc+/='; ", $csp);
        self::assertStringContainsString("script-src 'self' 'unsafe-inline'; ", $csp);
    }

    public function testLeavesThePolicyUnchangedWithoutInlineStyle(): void
    {
        self::assertStringContainsString("; style-src 'self' 'unsafe-inline'; ", $this->policy(new Request()));
    }

    private function policy(Request $request): string
    {
        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new Response());
        SiteCsp::subscriber('http://localhost:8090')->onResponse($event);
        new StyleNonceCspSubscriber()->onResponse($event);

        return (string) $event->getResponse()->headers->get('Content-Security-Policy-Report-Only');
    }
}
