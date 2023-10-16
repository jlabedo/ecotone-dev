<?php

declare(strict_types=1);

namespace Ecotone\Messaging\Config\BeforeSend;

use Ecotone\Messaging\Channel\ChannelInterceptor;
use Ecotone\Messaging\Channel\ChannelInterceptorBuilder;
use Ecotone\Messaging\Channel\DirectChannel;
use Ecotone\Messaging\Config\Container\ChannelReference;
use Ecotone\Messaging\Config\Container\ContainerMessagingBuilder;
use Ecotone\Messaging\Config\Container\Definition;
use Ecotone\Messaging\Config\Container\Reference;
use Ecotone\Messaging\Config\InMemoryChannelResolver;
use Ecotone\Messaging\Handler\Gateway\GatewayProxyBuilder;
use Ecotone\Messaging\Handler\InterfaceToCallRegistry;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\MethodInterceptor;
use Ecotone\Messaging\Handler\ReferenceSearchService;
use LogicException;
use Ramsey\Uuid\Uuid;

class BeforeSendChannelInterceptorBuilder implements ChannelInterceptorBuilder
{
    private string $inputChannelName;
    private MethodInterceptor $methodInterceptor;
    private GatewayProxyBuilder $gateway;
    private string $internalRequestChannelName;

    public function __construct(string $inputChannelName, MethodInterceptor $methodInterceptor)
    {
        $this->inputChannelName  = $inputChannelName;
        $this->methodInterceptor = $methodInterceptor;

        $this->internalRequestChannelName = Uuid::uuid4()->toString();
        $this->gateway                    = GatewayProxyBuilder::create(BeforeSendGateway::class . $this->internalRequestChannelName, BeforeSendGateway::class, 'execute', $this->internalRequestChannelName);
    }

    /**
     * @inheritDoc
     */
    public function relatedChannelName(): string
    {
        return $this->inputChannelName;
    }

    /**
     * @inheritDoc
     */
    public function getPrecedence(): int
    {
        return $this->methodInterceptor->getPrecedence();
    }

    /**
     * @inheritDoc
     */
    public function compile(ContainerMessagingBuilder $builder): Definition
    {
        $messageHandlerReference = $this->methodInterceptor->getInterceptingObject()->compile($builder);
        $builder->register(new ChannelReference($this->internalRequestChannelName), new Definition(DirectChannel::class, [$this->internalRequestChannelName, $messageHandlerReference]));
        $gatewayReference        = $this->gateway->compile($builder);

        return new Definition(BeforeSendChannelInterceptor::class, [$gatewayReference]);
    }

    public function __toString()
    {
        return "{$this->inputChannelName} {$this->methodInterceptor}";
    }
}
