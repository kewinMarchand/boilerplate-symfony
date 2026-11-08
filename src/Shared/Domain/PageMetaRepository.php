<?php

declare(strict_types=1);

namespace App\Shared\Domain;

interface PageMetaRepository
{
    /**
     * Métadonnées des pages fixes, par clé de page (`home`, `catalog`, `blog`…).
     *
     * @return array<string, PageMeta>
     *
     * @throws PageMetasLoadException
     */
    public function all(): array;
}
