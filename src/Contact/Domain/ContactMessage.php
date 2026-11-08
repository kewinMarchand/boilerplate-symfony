<?php

declare(strict_types=1);

namespace App\Contact\Domain;

final readonly class ContactMessage
{
    public function __construct(
        public string $name,
        public string $email,
        public string $message,
        public ?string $product = null,
    ) {
    }
}
