<?php

declare(strict_types=1);

namespace App\Shared\UI\Locale;

use App\Shared\Domain\ContentLocale;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Langue de la page en cours et langues du site, posées par LocaleSubscriber au début de la requête. Avant lui (commande,
 * test sans requête), la langue par défaut du framework, seule.
 */
final class PageLocale implements ContentLocale
{
    private string $locale;

    /** @var non-empty-list<string> */
    private array $locales;

    public function __construct(#[Autowire('%kernel.default_locale%')] string $defaultLocale)
    {
        $this->locale = $defaultLocale;
        $this->locales = [$defaultLocale];
    }

    /**
     * @param non-empty-list<string> $locales langues du site, la langue par défaut en tête
     */
    public function set(string $locale, array $locales): void
    {
        $this->locale = $locale;
        $this->locales = $locales;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function defaultLocale(): string
    {
        return $this->locales[0];
    }

    /**
     * @return non-empty-list<string>
     */
    public function locales(): array
    {
        return $this->locales;
    }

    public function isMultilingual(): bool
    {
        return \count($this->locales) > 1;
    }

    public function apiLocale(): ?string
    {
        return $this->isMultilingual() ? $this->locale : null;
    }

    /**
     * Étiquette avec la région la plus probable, « fr-FR », « en-US » (inLanguage des données structurées).
     */
    public function tag(?string $locale = null): string
    {
        return str_replace('_', '-', self::regional($locale ?? $this->locale));
    }

    /**
     * Forme d'Open Graph, « fr_FR », « en_US ».
     */
    public function ogLocale(?string $locale = null): string
    {
        return self::regional($locale ?? $this->locale);
    }

    /**
     * Nom d'une langue dans cette langue, « Français », « English ».
     */
    public function name(string $locale): string
    {
        $name = \Locale::getDisplayLanguage($locale, $locale);

        return mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1);
    }

    private static function regional(string $locale): string
    {
        $likely = \ResourceBundle::create('likelySubtags', 'ICUDATA', false)?->get($locale);
        if (!\is_string($likely)) {
            return $locale;
        }
        $region = \Locale::getRegion($likely);

        return '' === $region ? $locale : $locale.'_'.$region;
    }
}
