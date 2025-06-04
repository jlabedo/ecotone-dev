<?php
/*
 * licence Apache-2.0
 */
declare(strict_types=1);

namespace Test\Ecotone\EventSourcing\Integration;

use Ecotone\Lite\EcotoneLite;
use Ecotone\Messaging\Config\ModulePackageList;
use Ecotone\Messaging\Config\ServiceConfiguration;
use Enqueue\Dbal\DbalConnectionFactory;
use Test\Ecotone\EventSourcing\EventSourcingMessagingTestCase;
use Test\Ecotone\EventSourcing\Fixture\ProjectionHandlersExecutionRoutingTest\{AnAggregate,
    AnEvent,
    ProjectionWithMulitpleHandlersForSameEvent,
    ProjectionWithObjectRouting,
    ProjectionWithRegexRouting};

class ProjectionHandlersExecutionRoutingTest extends EventSourcingMessagingTestCase
{
    public function test_projection_with_object_routing(): void
    {
        $ecotone = EcotoneLite::bootstrapFlowTestingWithEventStore(
            classesToResolve: [ProjectionWithObjectRouting::class, AnEvent::class, AnAggregate::class],
            containerOrAvailableServices: [$projection = new ProjectionWithObjectRouting(), DbalConnectionFactory::class => $this->getConnectionFactory()],
            configuration: ServiceConfiguration::createWithDefaults()
                ->withSkippedModulePackageNames(ModulePackageList::allPackagesExcept([ModulePackageList::DBAL_PACKAGE, ModulePackageList::EVENT_SOURCING_PACKAGE, ModulePackageList::ASYNCHRONOUS_PACKAGE, ModulePackageList::JMS_CONVERTER_PACKAGE])),
            runForProductionEventStore: true
        );

        $ecotone->sendCommandWithRoutingKey('create', '123');

        self::assertEquals(
            [new AnEvent('123')],
            $projection->events,
            'Projection should receive named event even if object routing is used'
        );
    }

    public function test_projection_with_regex_routing(): void
    {
        $ecotone = EcotoneLite::bootstrapFlowTestingWithEventStore(
            classesToResolve: [ProjectionWithRegexRouting::class, AnEvent::class, AnAggregate::class],
            containerOrAvailableServices: [$projection = new ProjectionWithRegexRouting(), DbalConnectionFactory::class => $this->getConnectionFactory()],
            configuration: ServiceConfiguration::createWithDefaults()
                ->withSkippedModulePackageNames(ModulePackageList::allPackagesExcept([ModulePackageList::DBAL_PACKAGE, ModulePackageList::EVENT_SOURCING_PACKAGE, ModulePackageList::ASYNCHRONOUS_PACKAGE, ModulePackageList::JMS_CONVERTER_PACKAGE])),
            runForProductionEventStore: true
        );

        $ecotone->sendCommandWithRoutingKey('create', '123');

        self::assertEquals(
            [
                ['id' => '123'],
            ],
            $projection->events,
            'Projection should receive named event whit regex routing'
        );
    }

    public function test_projection_with_multiple_handlers_for_same_event(): void
    {
        $ecotone = EcotoneLite::bootstrapFlowTestingWithEventStore(
            classesToResolve: [ProjectionWithMulitpleHandlersForSameEvent::class, AnEvent::class, AnAggregate::class],
            containerOrAvailableServices: [$projection = new ProjectionWithMulitpleHandlersForSameEvent(), DbalConnectionFactory::class => $this->getConnectionFactory()],
            configuration: ServiceConfiguration::createWithDefaults()
                ->withSkippedModulePackageNames(ModulePackageList::allPackagesExcept([ModulePackageList::DBAL_PACKAGE, ModulePackageList::EVENT_SOURCING_PACKAGE, ModulePackageList::ASYNCHRONOUS_PACKAGE, ModulePackageList::JMS_CONVERTER_PACKAGE])),
            runForProductionEventStore: true
        );

        $ecotone->sendCommandWithRoutingKey('create', '123');

        self::assertEqualsCanonicalizing(
            [
                new AnEvent('123'),
                ['id' => '123'],
                ['id' => '123'],
            ],
            $projection->events,
            'Projection should receive named event whit regex routing'
        );
    }
}

