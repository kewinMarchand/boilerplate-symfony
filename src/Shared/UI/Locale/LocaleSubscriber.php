<?php

declare(strict_types=1);

namespace App\Shared\UI\Locale;

use App\Shared\UI\SiteIdentityProvider;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RequestContextAwareInterface;

/**
 * Langue de la page (section 29 bis de la spec commune). La langue par défaut n'a pas de préfixe, les autres langues
 * activées sont servies sous `/<langue>/…` par les mêmes routes, importées une seconde fois sous le préfixe `/{_locale}`
 * (config/routes.yaml, routes nommées `localized_<nom>`).
 *
 * Avant le routage, la langue vient du préfixe de l'adresse s'il désigne une langue activée autre que la langue par défaut,
 * sinon c'est la langue par défaut : une page introuvable sous `/en/` est rendue en anglais. Après le routage, un préfixe
 * qui n'est pas une telle langue (`/fr/…`, `/de/…`, `/en/…` sur un site monolingue) répond 404, et la route reprend son nom
 * canonique : menus, canonique et liens ne voient qu'un nom, le générateur d'URL (LocalizedUrlGenerator) y remet le préfixe.
 */
final readonly class LocaleSubscriber implements EventSubscriberInterface
{
    public const string LOCALIZED_ROUTE_PREFIX = 'localized_';

    /** Le paramètre de contexte du routeur qui porte la langue par défaut, lu par LocalizedUrlGenerator */
    public const string DEFAULT_LOCALE_PARAMETER = '_default_locale';

    /** Routes servies seulement sans préfixe : un seul robots.txt, un seul sitemap (toutes les langues), un seul manifeste */
    private const array UNPREFIXED_ROUTES = ['seo_robots', 'seo_sitemap', 'seo_manifest'];

    public function __construct(
        private SiteIdentityProvider $siteIdentity,
        private PageLocale $pageLocale,
        private RequestContextAwareInterface $router,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Le routeur écoute en 32, LocaleListener (langue de la requête et du contexte du routeur) en 16
        return [KernelEvents::REQUEST => [['resolveLocale', 40], ['checkPrefix', 20]]];
    }

    public function resolveLocale(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $prefix = 1 === preg_match('#^/([a-z]{2})(?:/|$)#', $request->getPathInfo(), $matches) ? $matches[1] : null;
        $locales = $this->siteIdentity->currentIn($prefix)->locales;
        $locale = null !== $prefix && $prefix !== $locales[0] && \in_array($prefix, $locales, true) ? $prefix : $locales[0];

        $this->pageLocale->set($locale, $locales);
        $request->setLocale($locale);
        $context = $this->router->getContext();
        $context->setParameter('_locale', $locale);
        $context->setParameter(self::DEFAULT_LOCALE_PARAMETER, $locales[0]);
    }

    public function checkPrefix(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $prefix = $request->attributes->get('_locale');
        $route = $request->attributes->get('_route');
        if (null === $prefix || !\is_string($route) || !str_starts_with($route, self::LOCALIZED_ROUTE_PREFIX)) {
            return;
        }

        $route = substr($route, \strlen(self::LOCALIZED_ROUTE_PREFIX));
        $isPrefixedLocale = $prefix === $this->pageLocale->locale() && $prefix !== $this->pageLocale->defaultLocale();
        if (!$isPrefixedLocale || \in_array($route, self::UNPREFIXED_ROUTES, true)) {
            throw new NotFoundHttpException(\sprintf('Préfixe de langue « %s » non servi pour la route « %s ».', \is_string($prefix) ? $prefix : '', $route));
        }

        $request->attributes->set('_route', $route);
    }
}
