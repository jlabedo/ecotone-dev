<?php

namespace Test\Ecotone\Modelling\Fixture\InterceptedCommandAggregate\AddExecutorId;

use Ecotone\Messaging\Attribute\Interceptor\Before;
use Ecotone\Modelling\Attribute\CommandHandler;
use Test\Ecotone\Modelling\Fixture\InterceptedCommandAggregate\Logger;

/**
 * licence Apache-2.0
 */
class AddExecutorId
{
    private string $executorId = '';
    private int $calledCount = 0;

    #[CommandHandler('changeExecutorId')]
    public function addExecutorId(string $executorId): void
    {
        $this->executorId = $executorId;
    }

    #[Before(pointcut: Logger::class)]
    public function add(array $payload): array
    {
        $this->calledCount += 1;
        if (isset($payload['executorId'])) {
            return $payload;
        }

        return array_merge(
            $payload,
            ['executorId' => $this->executorId]
        );
    }

    public function getCalledCount(): int
    {
        return $this->calledCount;
    }
}
