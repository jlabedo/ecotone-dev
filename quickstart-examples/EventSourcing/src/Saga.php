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
        $commandBus->send(new ChangePrice($event->getProductId(), 100));

        return [new SagaStarted($event->getProductId())];
    }

    #[EventSourcingHandler()]
    public function onPriceWasChanged(SagaStarted $event): void
    {
        $this->productId = $event->getProductId();
    }
}