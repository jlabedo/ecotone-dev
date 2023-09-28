<?php

use App\EventSourcing\EventSourcingHandlerMap;
use App\EventSourcing\InternalHandlerAggregate;
use Ecotone\Messaging\Support\Assert;
use Ecotone\Modelling\Attribute\EventSourcingHandler;

require __DIR__ . "/vendor/autoload.php";

$reflectionClass = new ReflectionClass(InternalHandlerAggregate::class);

$handlersMap = [];
foreach ($reflectionClass->getMethods() as $reflectionMethod) {
    if ($reflectionMethod->getAttributes(EventSourcingHandler::class)) {
        $eventType = $reflectionMethod->getParameters()[0]->getType()->getName();
        $handlersMap[$eventType] = $reflectionMethod->getName();
    }
}

foreach ($reflectionClass->getProperties() as $property) {
    if ($property->getAttributes(EventSourcingHandlerMap::class)) {
        if (!$property->isStatic()) {
            throw new InvalidArgumentException("Property must be static");
        }
        $property->setAccessible(true);
        $property->setValue($handlersMap);
        break;
    }
}

$aggregate = InternalHandlerAggregate::create(1);

Assert::isTrue($aggregate->getId() === 1, "Aggregate was not created");

echo "Aggregate id is {$aggregate->getId()}\n";
