<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Domain\ContentLocale;
use App\Shared\Infrastructure\Http\ContentLocaleHttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ContentLocaleHttpClientTest extends TestCase
{
    public function testMonolingualSiteKeepsItsCalls(): void
    {
        self::assertSame('http://api.test/api/products?page=1', self::send(null, 'GET', 'http://api.test/api/products?page=1')['url']);
    }

    public function testReadsPassThePageLocale(): void
    {
        self::assertSame('http://api.test/api/products?page=1&locale=en', self::send('en', 'GET', 'http://api.test/api/products?page=1')['url']);
    }

    public function testAnExplicitLocaleWins(): void
    {
        self::assertSame('http://api.test/api/site-identity?locale=de', self::send('en', 'GET', 'http://api.test/api/site-identity', ['query' => ['locale' => 'de']])['url']);
    }

    public function testSendsPassAcceptLanguage(): void
    {
        self::assertContains('Accept-Language: en', self::send('en', 'POST', 'http://api.test/api/contact_messages', ['json' => []])['headers']);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{url: string, headers: list<string>}
     */
    private static function send(?string $locale, string $method, string $url, array $options = []): array
    {
        $sent = ['url' => '', 'headers' => []];
        $mock = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $headers = $options['headers'] ?? [];
            $sent = ['url' => $url, 'headers' => \is_array($headers) ? array_values(array_filter($headers, \is_string(...))) : []];

            return new MockResponse('{}');
        });
        $contentLocale = new class($locale) implements ContentLocale {
            public function __construct(private readonly ?string $locale)
            {
            }

            public function apiLocale(): ?string
            {
                return $this->locale;
            }
        };
        (new ContentLocaleHttpClient($mock, $contentLocale))->request($method, $url, $options);

        return $sent;
    }
}
