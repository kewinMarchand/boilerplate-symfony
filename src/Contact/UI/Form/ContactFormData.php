<?php

declare(strict_types=1);

namespace App\Contact\UI\Form;

use App\Contact\Domain\ContactMessage;
use Symfony\Component\Validator\Constraints as Assert;

final class ContactFormData
{
    #[Assert\Length(min: 2, max: 100, minMessage: 'contact.name.too_short', maxMessage: 'contact.name.too_long', normalizer: 'trim')]
    public string $name = '';

    #[Assert\NotBlank(message: 'contact.email.invalid')]
    #[Assert\Email(message: 'contact.email.invalid')]
    public string $email = '';

    #[Assert\Length(min: 10, max: 5000, minMessage: 'contact.message.too_short', maxMessage: 'contact.message.too_long', normalizer: 'trim')]
    public string $message = '';

    /** Slug du produit d'une demande de renseignement, vérifié par l'API */
    public ?string $product = null;

    public function toMessage(): ContactMessage
    {
        return new ContactMessage($this->name, $this->email, $this->message, $this->product);
    }
}
