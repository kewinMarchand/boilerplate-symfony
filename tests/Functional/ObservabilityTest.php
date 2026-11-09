<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Sentry\State\HubInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ObservabilityTest extends WebTestCase
{
    private const string FAKE_DSN = 'http://clefactice@localhost:4067/1';

    protected function tearDown(): void
    {
        foreach (['GLITCHTIP_DSN', 'MATOMO_URL', 'MATOMO_SITE_ID'] as $name) {
            $_ENV[$name] = $_SERVER[$name] = '';
        }
        parent::tearDown();
    }

    public function testWithoutDsnNothingIsInjected(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        $body = $crawler->filter('body');
        self::assertNull($body->attr('data-controller'));
        self::assertNull($body->attr('data-glitchtip-dsn-value'));
        self::assertNull($body->attr('data-matomo-tracker-url-value'));
        $html = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('sentry-browser', $html);
        self::assertStringNotContainsString('matomo.js', $html);
    }

    public function testWithoutMatomoThePrivacyPageHasNoAudienceSection(): void
    {
        $client = self::createClient();
        $client->request('GET', '/donnees-personnelles');

        self::assertSelectorNotExists('[data-testid="legal-audience-measurement"]');
    }

    public function testWithMatomoTheTrackerAndTheAudienceSectionAreInjected(): void
    {
        $_ENV['MATOMO_URL'] = $_SERVER['MATOMO_URL'] = 'http://matomo.test';
        $_ENV['MATOMO_SITE_ID'] = $_SERVER['MATOMO_SITE_ID'] = '1';
        $client = self::createClient();
        $crawler = $client->request('GET', '/donnees-personnelles');

        $body = $crawler->filter('body');
        self::assertSame('matomo', $body->attr('data-controller'));
        self::assertSame('http://matomo.test/matomo.js', $body->attr('data-matomo-script-url-value'));
        self::assertSame('http://matomo.test/matomo.php', $body->attr('data-matomo-tracker-url-value'));
        self::assertSame('1', $body->attr('data-matomo-site-id-value'));
        self::assertSelectorTextContains('[data-testid="legal-audience-measurement"]', 'sans cookie');
    }

    public function testMatomoNeedsBothUrlAndSiteId(): void
    {
        $_ENV['MATOMO_URL'] = $_SERVER['MATOMO_URL'] = 'http://matomo.test/';
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        self::assertNull($crawler->filter('body')->attr('data-controller'));
        self::assertStringNotContainsString('matomo.js', (string) $client->getResponse()->getContent());
    }

    public function testWithDsnTheBrowserControllerIsInjected(): void
    {
        $_ENV['GLITCHTIP_DSN'] = $_SERVER['GLITCHTIP_DSN'] = self::FAKE_DSN;
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        $body = $crawler->filter('body');
        self::assertSame('glitchtip', $body->attr('data-controller'));
        self::assertSame(self::FAKE_DSN, $body->attr('data-glitchtip-dsn-value'));
        self::assertSame('test', $body->attr('data-glitchtip-environment-value'));
        self::assertStringContainsString('sentry-browser', (string) $body->attr('data-glitchtip-bundle-value'));
    }

    public function testServerSdkSendsNoPersonalDataAndIgnoresExpectedErrors(): void
    {
        self::bootKernel();
        $options = self::getContainer()->get(HubInterface::class)->getClient()?->getOptions();

        self::assertNotNull($options);
        self::assertFalse($options->shouldSendDefaultPii());
        self::assertSame('none', $options->getMaxRequestBodySize());
        self::assertNull($options->getTracesSampleRate());
        foreach ([NotFoundHttpException::class, UnprocessableEntityHttpException::class, ServiceUnavailableHttpException::class] as $expected) {
            self::assertContains($expected, $options->getIgnoreExceptions());
        }
    }
}
