<?php

namespace Ecotone\Modelling\Config;

use Ecotone\Messaging\Config\Container\CompilableBuilder;
use Ecotone\Messaging\Config\Container\ContainerMessagingBuilder;
use Ecotone\Messaging\Config\Container\Definition;
use Ecotone\Messaging\Config\Container\InterfaceToCallReference;
use Ecotone\Messaging\Config\Container\Reference;
use Ecotone\Messaging\Handler\ChannelResolver;
use Ecotone\Messaging\Handler\InterfaceToCallRegistry;
use Ecotone\Messaging\Handler\MessageHandlerBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\HeaderBuilder;
use Ecotone\Messaging\Handler\ReferenceSearchService;
use Ecotone\Messaging\Handler\Router\RouterBuilder;
use Ecotone\Messaging\MessageHandler;
use Ecotone\Messaging\Support\InvalidArgumentException;
use Ecotone\Modelling\MessageHandling\MetadataPropagator\MessageHeadersPropagatorInterceptor;
use Exception;

/**
 * Class BusRouterBuilder
 * @package Ecotone\Modelling\Config
 * @author  Dariusz Gafka <dgafka.mail@gmail.com>
 */
class BusRouterBuilder implements MessageHandlerBuilder, CompilableBuilder
{
    private ?string $endpointId;

    private array $channelNamesRouting;
    /**
     * @var string[]
     */
    private string $inputChannelName;
    private string $type;

    /**
     * @param string[]  $channelNamesRouting
     *
     * @throws Exception
     */
    private function __construct(string $endpointId, string $inputChannelName, array $channelNamesRouting, string $type)
    {
        $this->channelNamesRouting = $channelNamesRouting;
        $this->inputChannelName = $inputChannelName;
        $this->type = $type;
        $this->endpointId = $endpointId;
    }

    /**
     * @param string[] $channelNamesRouting
     *
     * @return BusRouterBuilder
     * @throws Exception
     */
    public static function createEventBusByObject(array $channelNamesRouting): self
    {
        return new self(
            BusModule::EVENT_CHANNEL_NAME_BY_OBJECT . '.endpoint',
            BusModule::EVENT_CHANNEL_NAME_BY_OBJECT,
            $channelNamesRouting,
            'eventByObject'
        );
    }

    /**
     * @param string[] $channelNamesRouting
     *
     * @return BusRouterBuilder
     * @throws Exception
     */
    public static function createEventBusByName(array $channelNamesRouting): self
    {
        return new self(
            BusModule::EVENT_CHANNEL_NAME_BY_NAME . '.endpoint',
            BusModule::EVENT_CHANNEL_NAME_BY_NAME,
            $channelNamesRouting,
            'eventByName'
        );
    }

    /**
     * @param string[] $channelNamesRouting
     *
     * @return BusRouterBuilder
     * @throws Exception
     */
    public static function createCommandBusByObject(array $channelNamesRouting): self
    {
        return new self(
            BusModule::COMMAND_CHANNEL_NAME_BY_OBJECT . '.endpoint',
            BusModule::COMMAND_CHANNEL_NAME_BY_OBJECT,
            $channelNamesRouting,
            'commandByObject'
        );
    }

    /**
     * @param string[] $channelNamesRouting
     *
     * @return BusRouterBuilder
     * @throws Exception
     */
    public static function createCommandBusByName(array $channelNamesRouting): self
    {
        return new self(
            BusModule::COMMAND_CHANNEL_NAME_BY_NAME . '.endpoint',
            BusModule::COMMAND_CHANNEL_NAME_BY_NAME,
            $channelNamesRouting,
            'commandByName'
        );
    }

    /**
     * @param string[] $channelNamesRouting
     *
     * @return BusRouterBuilder
     * @throws Exception
     */
    public static function createQueryBusByObject(array $channelNamesRouting): self
    {
        return new self(
            BusModule::QUERY_CHANNEL_NAME_BY_OBJECT . '.endpoint',
            BusModule::QUERY_CHANNEL_NAME_BY_OBJECT,
            $channelNamesRouting,
            'queryByObject'
        );
    }

    /**
     * @param string[] $channelNamesRouting
     *
     * @return BusRouterBuilder
     * @throws Exception
     */
    public static function createQueryBusByName(array $channelNamesRouting): self
    {
        return new self(
            BusModule::QUERY_CHANNEL_NAME_BY_NAME . '.endpoint',
            BusModule::QUERY_CHANNEL_NAME_BY_NAME,
            $channelNamesRouting,
            'queryByName'
        );
    }

    /**
     * @inheritDoc
     */
    public function build(ChannelResolver $channelResolver, ReferenceSearchService $referenceSearchService): MessageHandler
    {
        switch ($this->type) {
            case 'eventByObject': {
                return RouterBuilder::createRouterFromObject(
                    new EventBusRouter($this->channelNamesRouting),
                    'routeByObject'
                )   ->setResolutionRequired(false)
                    ->build($channelResolver, $referenceSearchService);
            }
            case 'eventByName': {
                return RouterBuilder::createRouterFromObject(
                    new EventBusRouter($this->channelNamesRouting),
                    'routeByName'
                )
                    ->setResolutionRequired(false)
                    ->withMethodParameterConverters([
                        HeaderBuilder::createOptional('routedName', BusModule::EVENT_CHANNEL_NAME_BY_NAME),
                    ])
                    ->build($channelResolver, $referenceSearchService);
            }
            case 'commandByObject': {
                return RouterBuilder::createRouterFromObject(
                    new CommandBusRouter($this->channelNamesRouting),
                    'routeByObject'
                )->build($channelResolver, $referenceSearchService);
            }
            case 'commandByName': {
                return RouterBuilder::createRouterFromObject(
                    new CommandBusRouter($this->channelNamesRouting),
                    'routeByName'
                )
                    ->withMethodParameterConverters([
                        HeaderBuilder::createOptional('name', BusModule::COMMAND_CHANNEL_NAME_BY_NAME),
                    ])
                    ->build($channelResolver, $referenceSearchService);
            }
            case 'queryByObject': {
                return RouterBuilder::createRouterFromObject(
                    new QueryBusRouter($this->channelNamesRouting),
                    'routeByObject'
                )->build($channelResolver, $referenceSearchService);
            }
            case 'queryByName': {
                return RouterBuilder::createRouterFromObject(
                    new QueryBusRouter($this->channelNamesRouting),
                    'routeByName'
                )
                    ->withMethodParameterConverters([
                        HeaderBuilder::createOptional('name', BusModule::QUERY_CHANNEL_NAME_BY_NAME),
                    ])
                    ->build($channelResolver, $referenceSearchService);
            }
        }

        throw InvalidArgumentException::create("Incorrect type {$this->type}");
    }

    public function compile(ContainerMessagingBuilder $builder): Reference|Definition|null
    {
        $configs = [
            "eventByObject" => [
                "class" => EventBusRouter::class,
                "method" => "routeByObject",
                "config" => fn (RouterBuilder $router) => $router->setResolutionRequired(false)
            ],
            "eventByName" => [
                "class" => EventBusRouter::class,
                "method" => "routeByName",
                "config" => fn (RouterBuilder $router) => $router
                    ->setResolutionRequired(false)
                    ->withMethodParameterConverters([
                        HeaderBuilder::createOptional('routedName', BusModule::EVENT_CHANNEL_NAME_BY_NAME),
                    ])
            ],
            "commandByObject" => [
                "class" => CommandBusRouter::class,
                "method" => "routeByObject",
                "config" => fn (RouterBuilder $router) => $router
            ],
            "commandByName" => [
                "class" => CommandBusRouter::class,
                "method" => "routeByName",
                "config" => fn (RouterBuilder $router) => $router
                    ->withMethodParameterConverters([
                        HeaderBuilder::createOptional('name', BusModule::COMMAND_CHANNEL_NAME_BY_NAME),
                    ])
            ],
            "queryByObject" => [
                "class" => QueryBusRouter::class,
                "method" => "routeByObject",
                "config" => fn (RouterBuilder $router) => $router
            ],
            "queryByName" => [
                "class" => QueryBusRouter::class,
                "method" => "routeByName",
                "config" => fn (RouterBuilder $router) => $router
                    ->withMethodParameterConverters([
                        HeaderBuilder::createOptional('name', BusModule::QUERY_CHANNEL_NAME_BY_NAME),
                    ])
            ],
        ];
        $config = $configs[$this->type] ?? throw InvalidArgumentException::create("Incorrect type {$this->type}");
        $routerReference = $builder->register($config["class"].'.'.$this->type, new Definition($config["class"], [
            $this->channelNamesRouting
        ]));
        $interfaceToCall = $builder->getInterfaceToCall(new InterfaceToCallReference($config["class"], $config["method"]));
        $router = RouterBuilder::create($routerReference->getId(), $interfaceToCall);
        $router = $config["config"]($router);
        return $router->compile($builder);
    }

    /**
     * @inheritDoc
     */
    public function resolveRelatedInterfaces(InterfaceToCallRegistry $interfaceToCallRegistry): iterable
    {
        return [
            $interfaceToCallRegistry->getFor(QueryBusRouter::class, 'routeByName'),
            $interfaceToCallRegistry->getFor(QueryBusRouter::class, 'routeByObject'),
            $interfaceToCallRegistry->getFor(CommandBusRouter::class, 'routeByName'),
            $interfaceToCallRegistry->getFor(CommandBusRouter::class, 'routeByObject'),
            $interfaceToCallRegistry->getFor(EventBusRouter::class, 'routeByName'),
            $interfaceToCallRegistry->getFor(EventBusRouter::class, 'routeByObject'),
        ];
    }

    /**
     * @inheritDoc
     */
    public function withInputChannelName(string $inputChannelName): self
    {
        $self = clone $this;
        $self->inputChannelName = $inputChannelName;

        return $self;
    }

    /**
     * @inheritDoc
     */
    public function getEndpointId(): ?string
    {
        return $this->endpointId;
    }

    /**
     * @inheritDoc
     */
    public function withEndpointId(string $endpointId): void
    {
        $this->endpointId = $endpointId;
    }

    /**
     * @inheritDoc
     */
    public function getInputMessageChannelName(): string
    {
        return $this->inputChannelName;
    }

    /**
     * @inheritDoc
     */
    public function getRequiredReferenceNames(): array
    {
        return [];
    }

    public function __toString()
    {
        return BusRouterBuilder::class;
    }
}
