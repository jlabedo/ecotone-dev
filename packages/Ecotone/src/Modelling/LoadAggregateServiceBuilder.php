<?php

namespace Ecotone\Modelling;

use Ecotone\Messaging\Config\Container\CompilableBuilder;
use Ecotone\Messaging\Config\Container\ContainerMessagingBuilder;
use Ecotone\Messaging\Config\Container\Definition;
use Ecotone\Messaging\Config\Container\FactoryDefinition;
use Ecotone\Messaging\Config\Container\InterfaceToCallReference;
use Ecotone\Messaging\Config\Container\Reference;
use Ecotone\Messaging\Handler\ChannelResolver;
use Ecotone\Messaging\Handler\ClassDefinition;
use Ecotone\Messaging\Handler\Enricher\PropertyEditorAccessor;
use Ecotone\Messaging\Handler\Enricher\PropertyReaderAccessor;
use Ecotone\Messaging\Handler\InputOutputMessageHandlerBuilder;
use Ecotone\Messaging\Handler\InterfaceToCall;
use Ecotone\Messaging\Handler\InterfaceToCallRegistry;
use Ecotone\Messaging\Handler\ReferenceSearchService;
use Ecotone\Messaging\Handler\ServiceActivator\ServiceActivatorBuilder;
use Ecotone\Messaging\Handler\TypeDescriptor;
use Ecotone\Messaging\MessageHandler;
use Ecotone\Modelling\Attribute\AggregateVersion;
use Ecotone\Modelling\Attribute\EventSourcingAggregate;
use Ecotone\Modelling\Attribute\TargetAggregateVersion;

class LoadAggregateServiceBuilder extends InputOutputMessageHandlerBuilder implements CompilableBuilder
{
    private string $aggregateClassName;
    private string $methodName;
    private ?string $messageVersionPropertyName;
    private ?string $aggregateVersionPropertyName = null;
    private array $aggregateRepositoryReferenceNames;
    private ?string $handledMessageClassName;
    private EventSourcingHandlerExecutor $eventSourcingHandlerExecutor;
    private LoadAggregateMode $loadAggregateMode;
    private bool $isEventSourced;
    private bool $isAggregateVersionAutomaticallyIncreased = true;

    private function __construct(ClassDefinition $aggregateClassName, string $methodName, ?ClassDefinition $handledMessageClass, LoadAggregateMode $loadAggregateMode, InterfaceToCallRegistry $interfaceToCallRegistry)
    {
        $this->aggregateClassName      = $aggregateClassName;
        $this->methodName              = $methodName;
        $this->handledMessageClassName = $handledMessageClass;
        $this->loadAggregateMode = $loadAggregateMode;

        $this->initialize($aggregateClassName, $handledMessageClass, $interfaceToCallRegistry);
    }

    public static function create(ClassDefinition $aggregateClassDefinition, string $methodName, ?ClassDefinition $handledMessageClass, LoadAggregateMode $loadAggregateMode, InterfaceToCallRegistry $interfaceToCallRegistry): self
    {
        return new self($aggregateClassDefinition, $methodName, $handledMessageClass, $loadAggregateMode, $interfaceToCallRegistry);
    }

    public function getInterceptedInterface(InterfaceToCallRegistry $interfaceToCallRegistry): InterfaceToCall
    {
        return $interfaceToCallRegistry->getFor($this->aggregateClassName, $this->methodName);
    }

    public function build(ChannelResolver $channelResolver, ReferenceSearchService $referenceSearchService): MessageHandler
    {
        $aggregateRepository = $this->isEventSourced
            ? LazyEventSourcedRepository::create(
                $this->aggregateClassName,
                $this->isEventSourced,
                $channelResolver,
                $referenceSearchService,
                $this->aggregateRepositoryReferenceNames
            ) : LazyStandardRepository::create(
                $this->aggregateClassName,
                $this->isEventSourced,
                $channelResolver,
                $referenceSearchService,
                $this->aggregateRepositoryReferenceNames
            );

        return ServiceActivatorBuilder::createWithDirectReference(
            new LoadAggregateService(
                $aggregateRepository,
                $this->aggregateClassName,
                $this->isEventSourced,
                $this->methodName,
                $this->messageVersionPropertyName,
                $this->aggregateVersionPropertyName,
                $this->isAggregateVersionAutomaticallyIncreased,
                new PropertyReaderAccessor(),
                PropertyEditorAccessor::create($referenceSearchService),
                $this->eventSourcingHandlerExecutor,
                $this->loadAggregateMode
            ),
            'load'
        )
            ->withOutputMessageChannel($this->getOutputMessageChannelName())
            ->build($channelResolver, $referenceSearchService);
    }

    public function compile(ContainerMessagingBuilder $builder): Reference|Definition|null
    {
        $repository = $this->isEventSourced
            ? new FactoryDefinition([LazyEventSourcedRepository::class, 'create'], [
                $this->aggregateClassName,
                $this->isEventSourced,
                new Reference(ChannelResolver::class),
                new Reference(ReferenceSearchService::class),
                $this->aggregateRepositoryReferenceNames
            ])
            : new FactoryDefinition([LazyStandardRepository::class, 'create'], [
                $this->aggregateClassName,
                $this->isEventSourced,
                new Reference(ChannelResolver::class),
                new Reference(ReferenceSearchService::class),
                $this->aggregateRepositoryReferenceNames
            ]);

        if (!$builder->has(PropertyEditorAccessor::class)) {
            $builder->register(PropertyEditorAccessor::class, new FactoryDefinition([PropertyEditorAccessor::class, 'create'], [
                new Reference(ReferenceSearchService::class)
            ]));
        }

        $loadAggregateService = new Definition(LoadAggregateService::class, [
            $repository,
            $this->aggregateClassName,
            $this->isEventSourced,
            $this->methodName,
            $this->messageVersionPropertyName,
            $this->aggregateVersionPropertyName,
            $this->isAggregateVersionAutomaticallyIncreased,
            new Reference(PropertyReaderAccessor::class),
            new Reference(PropertyEditorAccessor::class),
            // TODO: this is a fake implementation, we need to implement it
            new Definition(EventSourcingHandlerExecutor::class, [$this->aggregateClassName,[]]),
            new Definition(LoadAggregateMode::class, [$this->loadAggregateMode->getType()])
        ]);

        $reference = $builder->register(\uniqid(LoadAggregateService::class), $loadAggregateService);

        $interfaceToCall = $builder->getInterfaceToCall(new InterfaceToCallReference(LoadAggregateService::class, 'load'));
        return ServiceActivatorBuilder::create($reference, $interfaceToCall)
            ->withOutputMessageChannel($this->getOutputMessageChannelName())
            ->compile($builder);
    }

    /**
     * @param string[] $aggregateRepositoryReferenceNames
     */
    public function withAggregateRepositoryFactories(array $aggregateRepositoryReferenceNames): self
    {
        $this->aggregateRepositoryReferenceNames = $aggregateRepositoryReferenceNames;

        return $this;
    }

    public function resolveRelatedInterfaces(InterfaceToCallRegistry $interfaceToCallRegistry): iterable
    {
        return [
            $interfaceToCallRegistry->getFor(LoadAggregateService::class, 'load'),
        ];
    }

    public function getRequiredReferenceNames(): array
    {
        return [];
    }

    private function initialize(ClassDefinition $aggregateClassDefinition, ?ClassDefinition $handledMessageClassName, InterfaceToCallRegistry $interfaceToCallRegistry): void
    {
        $this->isEventSourced = $aggregateClassDefinition->hasClassAnnotation(TypeDescriptor::create(EventSourcingAggregate::class));
        $aggregateMessageVersionPropertyName = null;
        if ($handledMessageClassName) {
            $targetAggregateVersion            = TypeDescriptor::create(TargetAggregateVersion::class);
            foreach ($handledMessageClassName->getProperties() as $property) {
                if ($property->hasAnnotation($targetAggregateVersion)) {
                    $aggregateMessageVersionPropertyName = $property->getName();
                }
            }
        }
        $versionAnnotation             = TypeDescriptor::create(AggregateVersion::class);
        foreach ($aggregateClassDefinition->getProperties() as $property) {
            if ($property->hasAnnotation($versionAnnotation)) {
                /** @var AggregateVersion $annotation */
                $annotation = $property->getAnnotation($versionAnnotation);
                $this->aggregateVersionPropertyName = $property->getName();
                $this->isAggregateVersionAutomaticallyIncreased = $annotation->isAutoIncreased();
            }
        }

        $this->messageVersionPropertyName = $aggregateMessageVersionPropertyName;
        $this->eventSourcingHandlerExecutor = EventSourcingHandlerExecutor::createFor($aggregateClassDefinition, $this->isEventSourced, $interfaceToCallRegistry);
    }
}
