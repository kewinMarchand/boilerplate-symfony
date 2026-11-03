<?php

declare(strict_types=1);

namespace App\Task\Infrastructure\Http;

use App\Shared\Infrastructure\Http\InvalidApiPayloadException;
use App\Task\Domain\TaskRepository;
use App\Task\Domain\TasksLoadException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpTaskRepository implements TaskRepository
{
    public function __construct(#[Target('api.client')] private HttpClientInterface $apiClient)
    {
    }

    public function findAll(): array
    {
        try {
            return TaskTranslation::tasks($this->apiClient->request('GET', '/api/tasks')->toArray());
        } catch (ExceptionInterface|InvalidApiPayloadException|\InvalidArgumentException $exception) {
            throw new TasksLoadException($exception);
        }
    }
}
