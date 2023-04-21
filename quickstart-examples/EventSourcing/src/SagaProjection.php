<?php

namespace App\EventSourcing;

use App\EventSourcing\Event\PriceWasChanged;
use App\EventSourcing\Event\StockWasRegistered;
use Doctrine\DBAL\Connection;
use Ecotone\EventSourcing\Attribute\Projection;
use Ecotone\EventSourcing\Attribute\ProjectionInitialization;
use Ecotone\Messaging\Support\Assert;
use Ecotone\Modelling\Attribute\EventHandler;

#[Projection("saga_projection", Saga::class)]
class SagaProjection
{
    private array $priceChangeOverTime = [];

    private bool $isInitialized = false;
    private bool $sagaStarted = false;

    #[EventHandler]
    public function when(SagaStarted $event): void
    {
        Assert::isTrue($this->isInitialized, "Saga Projection is not initialized");
        $this->sagaStarted = true;
    }

    #[ProjectionInitialization]
    public function init(): void
    {
        $this->isInitialized = true;
    }
}