<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

enum Size: string
{
    case Small = 'S';
    case Medium = 'M';
    case Large = 'L';
}
