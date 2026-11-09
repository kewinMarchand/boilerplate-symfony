<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

enum Exposure: string
{
    case Sun = 'soleil';
    case PartialShade = 'mi-ombre';
    case Shade = 'ombre';
}
