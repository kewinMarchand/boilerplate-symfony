<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use Kewinmarchand\Csp\Csp;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ajoute à style-src le nonce des blocs <style> rendus par la page (attribut de requête `csp_style_nonce`, posé par
 * site_theme_style()), après l'abonné du paquet qui pose l'en-tête.
 */
final readonly class StyleNonceCspSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', -10]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $nonce = $event->getRequest()->attributes->get('csp_style_nonce');
        $headers = $event->getResponse()->headers;
        $policy = $headers->get(Csp::HEADER);
        if (!$event->isMainRequest() || !\is_string($nonce) || null === $policy) {
            return;
        }

        $headers->set(Csp::HEADER, (string) preg_replace('/(^|; )(style-src [^;]*)/', "\$1\$2 'nonce-".$nonce."'", $policy));
    }
}
