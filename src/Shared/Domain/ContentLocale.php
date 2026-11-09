<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Langue des contenus à demander à l'API pour la requête en cours (section 29 bis de la spec commune).
 */
interface ContentLocale
{
    /**
     * Langue de la page sur un site multilingue ; null sur un site monolingue, dont les appels restent ceux d'avant les langues.
     */
    public function apiLocale(): ?string;
}
