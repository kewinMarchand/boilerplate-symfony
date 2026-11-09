<?php

declare(strict_types=1);

namespace App\Shared\UI\Twig;

use App\Shared\UI\Locale\PageLocale;
use Twig\Attribute\AsTwigFunction;

final readonly class LocaleExtension
{
    public function __construct(private PageLocale $pageLocale)
    {
    }

    /**
     * Langue de la page et langues du site : `<html lang>`, `inLanguage` des données structurées.
     */
    #[AsTwigFunction('page_locale')]
    public function pageLocale(): PageLocale
    {
        return $this->pageLocale;
    }
}
