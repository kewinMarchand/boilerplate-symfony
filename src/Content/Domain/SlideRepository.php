<?php

declare(strict_types=1);

namespace App\Content\Domain;

interface SlideRepository
{
    /**
     * @return list<Slide> dans l'ordre du carrousel
     *
     * @throws SlidesLoadException
     */
    public function findAll(): array;
}
