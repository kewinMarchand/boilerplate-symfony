<?php

declare(strict_types=1);

namespace App\Shared\Domain;

interface SiteIdentityRepository
{
    /**
     * @throws SiteIdentityLoadException
     */
    public function current(): SiteIdentity;
}
