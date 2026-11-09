<?php

declare(strict_types=1);

namespace App\Shared\UI\Locale;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;

/**
 * Seul point qui préfixe les liens internes : une route générée dans une langue autre que la langue par défaut (celle de
 * la page, ou `_locale` passé en paramètre) devient sa jumelle préfixée `localized_<nom>`. Dans la langue par défaut, la
 * route sans préfixe, sans `_locale` en query string. Branché comme `generator_class` du routeur (App\Kernel) : `path()`,
 * `url()`, `generateUrl()` et `redirectToRoute()` en profitent sans changement.
 */
final class LocalizedUrlGenerator extends CompiledUrlGenerator
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        $defaultLocale = $this->context->getParameter(LocaleSubscriber::DEFAULT_LOCALE_PARAMETER);
        $locale = $parameters['_locale'] ?? $this->context->getParameter('_locale');
        unset($parameters['_locale']);

        if (\is_string($defaultLocale) && \is_string($locale) && $locale !== $defaultLocale && !str_starts_with($name, LocaleSubscriber::LOCALIZED_ROUTE_PREFIX)) {
            try {
                return parent::generate(LocaleSubscriber::LOCALIZED_ROUTE_PREFIX.$name, ['_locale' => $locale, ...$parameters], $referenceType);
            } catch (RouteNotFoundException) {
                // Route sans jumelle préfixée (développement, rapport CSP) : servie sans préfixe
            }
        }

        return parent::generate($name, $parameters, $referenceType);
    }
}
