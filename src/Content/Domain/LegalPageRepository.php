<?php

declare(strict_types=1);

namespace App\Content\Domain;

interface LegalPageRepository
{
    /**
     * @return LegalPage|null null si l'API ne connaît pas la page
     *
     * @throws LegalPageLoadException
     */
    public function find(string $slug): ?LegalPage;
}
