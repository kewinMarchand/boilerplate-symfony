<?php

declare(strict_types=1);

namespace App\Tests\Unit\Contact\Infrastructure\Http;

use App\Contact\Domain\ContactMessage;
use App\Contact\Infrastructure\Http\ContactTranslation;
use PHPUnit\Framework\TestCase;

final class ContactTranslationTest extends TestCase
{
    public function testBuildsTheRequestBody(): void
    {
        self::assertSame(
            ['name' => 'Ada', 'email' => 'ada@exemple.fr', 'message' => 'Bonjour'],
            ContactTranslation::request(new ContactMessage('Ada', 'ada@exemple.fr', 'Bonjour')),
        );
    }

    public function testSendsTheProductOfAnInquiry(): void
    {
        self::assertSame(
            ['name' => 'Ada', 'email' => 'ada@exemple.fr', 'message' => 'Bonjour', 'product' => 'monstera-deliciosa'],
            ContactTranslation::request(new ContactMessage('Ada', 'ada@exemple.fr', 'Bonjour', 'monstera-deliciosa')),
        );
    }

    public function testReadsTheInquiredProduct(): void
    {
        $product = ContactTranslation::product(['id' => 1, 'slug' => 'monstera-deliciosa', 'name' => 'Monstera deliciosa', 'price' => 3490]);

        self::assertSame(['monstera-deliciosa', 'Monstera deliciosa'], [$product->slug, $product->name]);
    }

    public function testReadsTheViolationsOfA422(): void
    {
        $payload = ['status' => 422, 'violations' => [
            ['propertyPath' => 'name', 'message' => 'Indiquez votre nom (2 caractères minimum).', 'code' => 'x'],
        ]];

        self::assertSame(
            [['field' => 'name', 'message' => 'Indiquez votre nom (2 caractères minimum).']],
            ContactTranslation::violations($payload),
        );
    }
}
