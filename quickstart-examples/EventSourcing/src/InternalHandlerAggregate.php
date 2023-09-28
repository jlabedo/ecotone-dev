<?php

namespace App\EventSourcing;

use Ecotone\Modelling\Attribute\AggregateEvents;
use Ecotone\Modelling\Attribute\AggregateIdentifier;
use Ecotone\Modelling\Attribute\EventSourcingAggregate;
use Ecotone\Modelling\Attribute\EventSourcingHandler;

#[EventSourcingAggregate]
class InternalHandlerAggregate
{
    use WithInternalExecutor;

    #[AggregateIdentifier]
    private int $id;

    public static function create(int $id): self
    {
        $self = new self();
        $self->recordThat(new InternalHandlerAggregateWasCreated($id));
        return $self;
    }

    #[EventSourcingHandler]
    private function whenInternalHandlerAggregateWasCreated(InternalHandlerAggregateWasCreated $event): void
    {
        $this->id = $event->id;
    }

    public function getId(): int
    {
        return $this->id;
    }
}

trait WithInternalExecutor {

    #[EventSourcingHandlerMap]
    private static array $eventToHandlerMap = [];
    private ?array $recordedEvents = [];

    public function recordThat(object $event): void
    {
        if($methodName = static::$eventToHandlerMap[get_class($event)]) {
            $this->{$methodName}($event);
        }

        if (! $this->recordedEvents) {
            $this->recordedEvents = [];
        }

        $this->recordedEvents[] = $event;
    }

    #[EventSourcingExecutor]
    public static function fromEventStream(array $events): static
    {
        $self = new self();
        foreach ($events as $event) {
            if($methodName = static::$eventToHandlerMap[get_class($event)]) {
                $self->{$methodName}($event);
            }
        }

        return $self;
    }

    #[AggregateEvents]
    public function getRecordedEvents(): array
    {
        $recordedEvents = $this->recordedEvents;
        $this->recordedEvents = [];

        return $recordedEvents;
    }
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class EventSourcingExecutor {
}

#[\Attribute(\Attribute::TARGET_ALL)]
class EventSourcingHandlerMap {
}

class InternalHandlerAggregateWasCreated
{
    public function __construct(public int $id)
    {
    }
}
