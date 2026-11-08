<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Contact\Domain\ContactMessage;
use App\Contact\Domain\ContactMessageNotSentException;
use App\Contact\Domain\ContactMessageSender;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContactControllerTest extends WebTestCase
{
    public function testAnInquiryNamesTheProductAndSendsItWithTheMessage(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $sender = new class implements ContactMessageSender {
            public ?ContactMessage $sent = null;

            public function send(ContactMessage $message): void
            {
                $this->sent = $message;
            }
        };
        self::getContainer()->set(ContactMessageSender::class, $sender);
        $crawler = $client->request('GET', '/contact?produit=monstera-deliciosa');

        self::assertSelectorTextSame('[data-testid="contact-product"]', 'Votre demande concerne : Monstera deliciosa');
        self::assertSelectorExists('[data-testid="contact-product"] a[href="/produit/monstera-deliciosa"]');
        self::assertSame('http://localhost/contact', $crawler->filter('link[rel="canonical"]')->attr('href'));

        $client->submit($crawler->filter('[data-testid="contact-submit"]')->form(), [
            'contact[name]' => 'Ada',
            'contact[email]' => 'ada@exemple.fr',
            'contact[message]' => 'Bonjour, ceci est un message.',
        ], ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertSame('monstera-deliciosa', $sender->sent?->product);
        self::assertResponseRedirects('/contact?produit=monstera-deliciosa', 303);
        $client->followRedirect();
        self::assertSelectorTextContains('[data-testid="contact-success"]', 'Votre demande de renseignement a bien été envoyée.');
    }

    public function testAnUnknownProductLeavesASimpleContactForm(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/contact?produit=produit-inconnu');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-testid="contact-product"]');
        self::assertSame('', $crawler->filter('#contact_product')->attr('value') ?? '');
    }

    public function testInvalidSubmissionLinksEachErrorToItsField(): void
    {
        $client = $this->submit(['name' => '', 'email' => 'ada', 'message' => 'court']);

        self::assertResponseStatusCodeSame(422);
        $email = $client->getCrawler()->filter('[data-testid="contact-email"]');
        self::assertSame('true', $email->attr('aria-invalid'));
        $describedBy = (string) $email->attr('aria-describedby');
        self::assertStringContainsString('adresse e-mail valide', $client->getCrawler()->filter('#'.$describedBy)->text());
        self::assertNotNull($client->getCrawler()->filter('[data-testid="contact-name"]')->attr('autofocus'));
    }

    public function testValidSubmissionWithoutJavaScriptRedirectsAndConfirms(): void
    {
        $client = $this->submit(['name' => 'Ada', 'email' => 'ada@exemple.fr', 'message' => 'Bonjour, ceci est un message.']);

        self::assertResponseRedirects('/contact', 303);
        $client->followRedirect();
        self::assertSelectorTextContains('[data-testid="contact-success"]', 'bien été envoyé');
    }

    public function testValidSubmissionWithTurboAnswersWithAStream(): void
    {
        $client = $this->submit(['name' => 'Ada', 'email' => 'ada@exemple.fr', 'message' => 'Bonjour, ceci est un message.'], ['HTTP_ACCEPT' => 'text/vnd.turbo-stream.html, text/html']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/vnd.turbo-stream.html; charset=UTF-8');
        self::assertStringContainsString('target="contact-status"', (string) $client->getResponse()->getContent());
    }

    public function testApiViolationsAreShownUnderTheirField(): void
    {
        $client = $this->submit(['name' => 'Refus', 'email' => 'refus@exemple.fr', 'message' => 'Bonjour, ceci est un message.']);

        self::assertResponseStatusCodeSame(422);
        $email = $client->getCrawler()->filter('[data-testid="contact-email"]');
        self::assertSame('true', $email->attr('aria-invalid'));
        self::assertStringContainsString('refusée par le destinataire', $client->getCrawler()->filter('#'.$email->attr('aria-describedby'))->text());
    }

    public function testSendingFailureShowsABusinessMessage(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->set(ContactMessageSender::class, new class implements ContactMessageSender {
            public function send(ContactMessage $message): void
            {
                throw new ContactMessageNotSentException();
            }
        });
        $this->submitWith($client, ['name' => 'Ada', 'email' => 'ada@exemple.fr', 'message' => 'Bonjour, ceci est un message.']);

        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('[data-testid="contact-error"]', 'L’envoi a échoué');
    }

    /**
     * @param array<string, string> $values
     * @param array<string, string> $server
     */
    private function submit(array $values, array $server = []): KernelBrowser
    {
        $client = self::createClient();
        $this->submitWith($client, $values, $server);

        return $client;
    }

    /**
     * @param array<string, string> $values
     * @param array<string, string> $server
     */
    private function submitWith(KernelBrowser $client, array $values, array $server = []): void
    {
        $crawler = $client->request('GET', '/contact');
        $form = $crawler->filter('[data-testid="contact-submit"]')->form();
        $client->submit($form, [
            'contact[name]' => $values['name'],
            'contact[email]' => $values['email'],
            'contact[message]' => $values['message'],
        ], ['HTTP_ORIGIN' => 'http://localhost', ...$server]);
    }
}
