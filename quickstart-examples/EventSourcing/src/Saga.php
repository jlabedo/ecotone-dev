<?php declare(strict_types=1);

namespace App\EventSourcing;

use App\EventSourcing\Command\ChangePrice;
use App\EventSourcing\Event\ProductWasRegistered;
use Ecotone\EventSourcing\Attribute\Stream;
use Ecotone\Modelling\Attribute\EventHandler;
use Ecotone\Modelling\Attribute\EventSourcingAggregate;
use Ecotone\Modelling\Attribute\EventSourcingHandler;
use Ecotone\Modelling\Attribute\EventSourcingSaga;
use Ecotone\Modelling\Attribute\SagaIdentifier;
use Ecotone\Modelling\CommandBus;
use Ecotone\Modelling\WithAggregateVersioning;

#[EventSourcingAggregate()]
class Saga
{
    use WithAggregateVersioning;
    #[SagaIdentifier]
    private int $productId;

    #[EventHandler()]
    public static function start(ProductWasRegistered $event, CommandBus $commandBus): array
    {
        // This one is Ok
        if ($event->getProductId() === 2) {
            $commandBus->send(new ChangePrice(1, 100));
        }

        // This one is failing
        if ($event->getProductId() === 3) {
            $commandBus->send(new ChangePrice(3, 100));
        }

        return [new SagaStarted($event->getProductId())];
    }

    #[EventSourcingHandler()]
    public function onPriceWasChanged(SagaStarted $event): void
    {
        $this->productId = $event->getProductId();
    }
}