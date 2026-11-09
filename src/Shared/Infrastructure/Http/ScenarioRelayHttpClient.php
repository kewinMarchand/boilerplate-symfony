<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Relaie l'en-tête X-Scenario de la requête en cours vers l'API, pour provoquer en test les états
 * d'erreur et de liste vide. À défaut, `API_SCENARIO` fixe le scénario de tous les appels du site (`bilingual` : site en
 * français et en anglais, tests/e2e/bilingue.e2e.ts). L'API ne l'honore qu'en dev et en test.
 */
#[AsDecorator('api.client')]
final class ScenarioRelayHttpClient implements HttpClientInterface
{
    use DecoratorTrait;

    public function __construct(
        HttpClientInterface $client,
        private readonly RequestStack $requestStack,
        #[Autowire('%env(API_SCENARIO)%')]
        private readonly string $defaultScenario = '',
    ) {
        $this->client = $client;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $scenario = $this->requestStack->getMainRequest()?->headers->get('X-Scenario') ?: $this->defaultScenario;
        if ('' !== $scenario) {
            $headers = \is_array($options['headers'] ?? null) ? $options['headers'] : [];
            $options['headers'] = [...$headers, 'X-Scenario' => $scenario];
        }

        return $this->client->request($method, $url, $options);
    }
}
