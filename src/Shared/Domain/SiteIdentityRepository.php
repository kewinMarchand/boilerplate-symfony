<?php

declare(strict_types=1);

namespace App\Shared\Domain;

interface SiteIdentityRepository
{
    /**
     * @param string|null $locale langue demandée à l'API (préfixe de l'adresse), qu'elle ignore si elle n'est pas activée ;
     *                            null : langue par défaut
     *
     * @throws SiteIdentityLoadException
     */
    public function current(?string $locale = null): SiteIdentity;
}
