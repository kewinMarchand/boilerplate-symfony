<?php

declare(strict_types=1);

namespace App\Shared\UI\Seo;

final readonly class SeoPage
{
    /**
     * @param list<string>                                                           $jsonLd             scripts JSON-LD déjà encodés
     * @param list<array{hreflang: string, url: string}>                             $alternates         `<link rel="alternate" hreflang>`, x-default compris, vide sur un site monolingue
     * @param list<string>                                                           $ogLocaleAlternates `og:locale:alternate`
     * @param list<array{locale: string, name: string, href: string, current: bool}> $languages          liens du sélecteur de langue, vide sur un site monolingue
     */
    public function __construct(
        public string $title,
        public string $description,
        public ?string $canonical,
        public string $robots,
        public string $image,
        public string $imageAlt,
        public Breadcrumb $breadcrumb,
        public array $jsonLd,
        public ?string $prev = null,
        public ?string $next = null,
        public int $imageWidth = 1200,
        public int $imageHeight = 630,
        public ?string $publishedTime = null,
        public string $ogLocale = 'fr_FR',
        public array $alternates = [],
        public array $ogLocaleAlternates = [],
        public array $languages = [],
    ) {
    }
}
