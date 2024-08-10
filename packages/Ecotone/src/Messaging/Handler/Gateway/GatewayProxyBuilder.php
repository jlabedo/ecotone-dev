<?php

declare(strict_types=1);

namespace Ecotone\Messaging\Handler\Gateway;

use function array_map;

use Ecotone\Messaging\Config\Container\AttributeDefinition;

use Ecotone\Messaging\Config\Container\ChannelReference;
use Ecotone\Messaging\Config\Container\CompilableBuilder;
use Ecotone\Messaging\Config\Container\Definition;
use Ecotone\Messaging\Config\Container\GatewayProxyMethodReference;
use Ecotone\Messaging\Config\Container\GatewayProxyReference;
use Ecotone\Messaging\Config\Container\InterfaceToCallReference;
use Ecotone\Messaging\Config\Container\MessagingContainerBuilder;
use Ecotone\Messaging\Config\Container\ProxyBuilder;
use Ecotone\Messaging\Config\Container\Reference;
use Ecotone\Messaging\Conversion\ConversionService;
use Ecotone\Messaging\Conversion\MediaType;
use Ecotone\Messaging\Handler\Gateway\ParameterToMessageConverter\GatewayHeadersBuilder;
use Ecotone\Messaging\Handler\Gateway\ParameterToMessageConverter\GatewayHeaderValueBuilder;
use Ecotone\Messaging\Handler\Gateway\ParameterToMessageConverter\GatewayPayloadBuilder;
use Ecotone\Messaging\Handler\Gateway\ParameterToMessageConverter\GatewayPayloadConverter;
use Ecotone\Messaging\Handler\Gateway\ParameterToMessageConverter\GatewayPayloadExpressionBuilder;
use Ecotone\Messaging\Handler\InterceptedEndpoint;
use Ecotone\Messaging\Handler\InterfaceToCall;
use Ecotone\Messaging\Handler\InterfaceToCallRegistry;
use Ecotone\Messaging\Handler\Processor\ChainedMessageProcessor;
use Ecotone\Messaging\Handler\Processor\MethodInvocationProcessor;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\AroundInterceptorBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\AroundMethodInvocationProvider;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\InterceptorWithPointCut;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\MessageProcessorInvocationProvider;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\MethodInterceptorBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\PayloadResultMessageConverter;
use Ecotone\Messaging\MessageHeaders;
use Ecotone\Messaging\MessagingException;
use Ecotone\Messaging\PollableChannel;
use Ecotone\Messaging\Precedence;
use Ecotone\Messaging\SubscribableChannel;
use Ecotone\Messaging\Support\Assert;

use Ecotone\Messaging\Support\InvalidArgumentException;

use function is_a;

use Ramsey\Uuid\Uuid;

/**
 * Class GatewayProxySpec
 * @package Ecotone\Messaging\Config
 * @author Dariusz Gafka <support@simplycodedsoftware.com>
 */
/**
 * licence Apache-2.0
 */
class GatewayProxyBuilder implements InterceptedEndpoint, CompilableBuilder, ProxyBuilder
{
    public const DEFAULT_REPLY_MILLISECONDS_TIMEOUT = -1;

    private string $referenceName;
    private string $interfaceName;
    private string $methodName;
    private string $requestChannelName;
    private int $replyMilliSecondsTimeout = self::DEFAULT_REPLY_MILLISECONDS_TIMEOUT;
    private ?string $replyChannelName = null;
    private ?string $replyContentType = null;
    /**
     * @var GatewayParameterConverterBuilder[]
     */
    private array $methodArgumentConverters = [];
    private ?string $errorChannelName = null;
    /**
     * @var string[]
     */
    private array $messageConverterReferenceNames = [];
    /**
     * @var AroundInterceptorBuilder[]
     */
    private array $aroundInterceptors = [];
    /**
     * @var MethodInterceptorBuilder[]
     */
    private array $beforeInterceptors = [];
    /**
     * @var MethodInterceptorBuilder[]
     */
    private array $afterInterceptors = [];
    /**
     * @var AttributeDefinition[]
     */
    private iterable $endpointAnnotations = [];
    /**
     * @var string[]
     */
    private array $requiredInterceptorNames = [];
    private ?InterfaceToCall $annotatedInterfaceToCall = null;

    /**
     * GatewayProxyBuilder constructor.
     * @param string $referenceName
     * @param string $interfaceName
     * @param string $methodName
     * @param string $requestChannelName
     */
    private function __construct(string $referenceName, string $interfaceName, string $methodName, string $requestChannelName)
    {
        Assert::notNullAndEmpty($requestChannelName, "Request channel for {$interfaceName}:{$methodName} can not be empty.");

        $this->referenceName = $referenceName;
        $this->interfaceName = $interfaceName;
        $this->methodName = $methodName;
        $this->requestChannelName = $requestChannelName;
    }

    /**
     * @param string $referenceName
     * @param string $interfaceName
     * @param string $methodName
     * @param string $requestChannelName
     * @return GatewayProxyBuilder
     */
    public static function create(string $referenceName, string $interfaceName, string $methodName, string $requestChannelName): self
    {
        return new self($referenceName, $interfaceName, $methodName, $requestChannelName);
    }

    /**
     * @param string $replyChannelName where to expect reply
     * @return GatewayProxyBuilder
     */
    public function withReplyChannel(string $replyChannelName): self
    {
        $this->replyChannelName = $replyChannelName;

        return $this;
    }

    public function withReplyContentType(string $contentType): self
    {
        $this->replyContentType = MediaType::parseMediaType($contentType)->toString();

        return $this;
    }

    /**
     * @param string $errorChannelName
     * @return GatewayProxyBuilder
     */
    public function withErrorChannel(string $errorChannelName): self
    {
        $this->errorChannelName = $errorChannelName;

        return $this;
    }

    /**
     * @param int $replyMillisecondsTimeout
     * @return GatewayProxyBuilder
     */
    public function withReplyMillisecondTimeout(int $replyMillisecondsTimeout): self
    {
        $this->replyMilliSecondsTimeout = $replyMillisecondsTimeout;

        return $this;
    }

    public function withAnnotatedInterface(InterfaceToCall $interfaceToCall): self
    {
        $this->annotatedInterfaceToCall = $interfaceToCall;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getReferenceName(): string
    {
        return $this->referenceName;
    }

    /**
     * @inheritDoc
     */
    public function getInterfaceName(): string
    {
        return $this->interfaceName;
    }

    /**
     * @inheritDoc
     */
    public function getRelatedMethodName(): string
    {
        return $this->methodName;
    }

    /**
     * @param GatewayParameterConverterBuilder[] $methodArgumentConverters
     * @return $this
     * @throws MessagingException
     */
    public function withParameterConverters(array $methodArgumentConverters): self
    {
        Assert::allInstanceOfType($methodArgumentConverters, GatewayParameterConverterBuilder::class);
        $amount = 0;
        foreach ($methodArgumentConverters as $methodArgumentConverter) {
            $amount += $methodArgumentConverter instanceof GatewayPayloadBuilder || $methodArgumentConverter instanceof GatewayPayloadExpressionBuilder;
        }
        Assert::isTrue($amount <= 1, "Can't create gateway {$this} with two Payload converters");

        $this->methodArgumentConverters = $methodArgumentConverters;

        return $this;
    }

    /**
     * @param string[] $messageConverterReferenceNames
     * @return $this
     */
    public function withMessageConverters(array $messageConverterReferenceNames): self
    {
        $this->messageConverterReferenceNames = array_unique(array_merge($this->messageConverterReferenceNames, $messageConverterReferenceNames));

        return $this;
    }

    /**
     * @return $this
     */
    public function addAroundInterceptor(AroundInterceptorBuilder $aroundInterceptorReference): self
    {
        $this->aroundInterceptors[] = $aroundInterceptorReference;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getInterceptedInterface(InterfaceToCallRegistry $interfaceToCallRegistry): InterfaceToCall
    {
        return $interfaceToCallRegistry->getFor($this->interfaceName, $this->methodName);
    }

    public function addBeforeInterceptor(MethodInterceptorBuilder $methodInterceptor): self
    {
        $this->beforeInterceptors[] = $methodInterceptor;

        return $this;
    }

    public function addAfterInterceptor(MethodInterceptorBuilder $methodInterceptor): self
    {
        $this->afterInterceptors[] = $methodInterceptor;

        return $this;
    }

    /**
     * @param AttributeDefinition[] $endpointAnnotations
     * @return static
     */
    public function withEndpointAnnotations(iterable $endpointAnnotations): self
    {
        Assert::allInstanceOfType($endpointAnnotations, AttributeDefinition::class);
        $this->endpointAnnotations = $endpointAnnotations;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getRequiredInterceptorNames(): iterable
    {
        return $this->requiredInterceptorNames;
    }

    /**
     * @inheritDoc
     */
    public function withRequiredInterceptorNames(iterable $interceptorNames): self
    {
        foreach ($interceptorNames as $interceptorName) {
            $this->requiredInterceptorNames[] = $interceptorName;
        }

        return $this;
    }

    /**
     * @return AttributeDefinition[]
     */
    public function getEndpointAnnotations(): array
    {
        return $this->endpointAnnotations;
    }

    public function registerProxy(MessagingContainerBuilder $builder): Reference
    {
        $gateway = $this->compile($builder);
        $builder->register($this->getProxyMethodReference(), $gateway);
        if (! $builder->has($this->getReferenceName())) {
            $builder->register(
                $this->getReferenceName(),
                ProxyFactory::getGatewayProxyDefinitionFor(new GatewayProxyReference($this->referenceName, $this->interfaceName))
            );
        }

        return new Reference($this->getReferenceName());
    }

    public function compile(MessagingContainerBuilder $builder): Definition
    {
        $interfaceToCallReference = new InterfaceToCallReference($this->interfaceName, $this->methodName);
        $interfaceToCall = $builder->getInterfaceToCall($interfaceToCallReference);

        if (! $interfaceToCall->canReturnValue() && $this->replyChannelName) {
            throw InvalidArgumentException::create("Can't set reply channel for {$interfaceToCall}");
        }

        if (! ($interfaceToCall->canItReturnNull() || $interfaceToCall->hasReturnTypeVoid())) {
            $requestChannelDefinition = $builder->getDefinition(ChannelReference::toChannel($this->requestChannelName));
            Assert::isTrue(is_a($requestChannelDefinition->getClassName(), SubscribableChannel::class, true), 'Gateway request channel should not be pollable if expected return type is not nullable');
        }

        if (! $interfaceToCall->canItReturnNull() && $this->errorChannelName && ! $interfaceToCall->hasReturnTypeVoid()) {
            throw InvalidArgumentException::create("Gateway {$interfaceToCall} with error channel must allow nullable return type");
        }

        if ($this->replyChannelName) {
            $replyChannelDefinition = $builder->getDefinition(ChannelReference::toChannel($this->replyChannelName));
            Assert::isTrue(is_a($replyChannelDefinition->getClassName(), PollableChannel::class, true), 'Reply channel must be pollable');
        }

        $methodArgumentConverters = [];
        if ($this->replyContentType) {
            $methodArgumentConverters[] = GatewayHeaderValueBuilder::create(MessageHeaders::REPLY_CONTENT_TYPE, $this->replyContentType)->compile($builder, $interfaceToCall);
        }
        if ($interfaceToCall->hasFirstParameter() && ! $this->hasConverterFor($interfaceToCall->getFirstParameter())) {
            $methodArgumentConverters[] = GatewayPayloadBuilder::create($interfaceToCall->getFirstParameter()->getName())->compile($builder, $interfaceToCall);
        }
        if ($interfaceToCall->hasSecondParameter() && ! $this->hasConverterFor($interfaceToCall->getSecondParameter())) {
            if ($interfaceToCall->getSecondParameter()->getTypeDescriptor()->isArrayButNotClassBasedCollection()) {
                $methodArgumentConverters[] = GatewayHeadersBuilder::create($interfaceToCall->getSecondParameter()->getName())->compile($builder, $interfaceToCall);
            }
        }

        foreach ($this->methodArgumentConverters as $messageConverterBuilder) {
            $methodArgumentConverters[] = $messageConverterBuilder->compile($builder, $interfaceToCall);
        }

        $messageConverters = [];
        foreach ($this->messageConverterReferenceNames as $messageConverterReferenceName) {
            $messageConverters[] = new Reference($messageConverterReferenceName);
        }

        if (empty($methodArgumentConverters) && $interfaceToCall->hasMoreThanOneParameter()) {
            throw InvalidArgumentException::create("You need to pass method argument converts for {$interfaceToCall}");
        }

        if (empty($methodArgumentConverters) && $interfaceToCall->hasSingleParameter()) {
            $methodArgumentConverters = [GatewayPayloadConverter::create($interfaceToCall->getFirstParameter())];
        }

        $internalHandlerReference = $this->compileGatewayInternalHandler($builder);

        return new Definition(Gateway::class, [
            new Definition(MethodCallToMessageConverter::class, [
                $methodArgumentConverters,
                $interfaceToCall->getInterfaceParametersNames(),
            ]),
            $interfaceToCall->getReturnType(),
            $messageConverters,
            new Definition(GatewayReplyConverter::class, [
                new Reference(ConversionService::REFERENCE_NAME),
                $interfaceToCall->toString(),
                $interfaceToCall->getReturnType(),
                $messageConverters,
            ]),
            $internalHandlerReference,
        ]);
    }

    private function compileGatewayInternalHandler(MessagingContainerBuilder $builder): Definition
    {
        $interfaceToCallReference = new InterfaceToCallReference($this->interfaceName, $this->methodName);
        $interfaceToCall = $builder->getInterfaceToCall($interfaceToCallReference);
        $gatewayInternalProcessor = new Definition(GatewayInternalProcessor::class, [
            $interfaceToCall->toString(),
            $interfaceToCall->getReturnType(),
            $interfaceToCall->canItReturnNull(),
            new ChannelReference($this->requestChannelName),
            $this->replyChannelName ? new ChannelReference($this->replyChannelName) : null,
            $this->replyMilliSecondsTimeout,
        ]);
        $interceptedInterface = $this->annotatedInterfaceToCall ?? $interfaceToCall;
        $interceptedInterfaceReference = InterfaceToCallReference::fromInstance($interceptedInterface);
        // register interceptors
        $interceptorsConfig = $builder->getRelatedInterceptors($interceptedInterfaceReference, $this->endpointAnnotations, $this->requiredInterceptorNames);
        $beforeInterceptors = \array_merge($this->beforeInterceptors, $interceptorsConfig->getBeforeInterceptors());
        $aroundInterceptors = \array_merge($this->aroundInterceptors, $interceptorsConfig->getAroundInterceptors());
        $afterInterceptors = \array_merge($this->afterInterceptors, $interceptorsConfig->getAfterInterceptors());

        if ($this->errorChannelName) {
            $interceptorReference = $builder->register(
                Uuid::uuid4()->toString(),
                new Definition(ErrorChannelInterceptor::class, [
                    new ChannelReference($this->errorChannelName),
                ])
            );
            $channelInterceptorInterface = $builder->getInterfaceToCall(new InterfaceToCallReference(ErrorChannelInterceptor::class, 'handle'));
            $aroundInterceptors[] = AroundInterceptorBuilder::create(
                $interceptorReference->getId(),
                $channelInterceptorInterface,
                Precedence::ERROR_CHANNEL_PRECEDENCE,
            );
        }

        $messageProcessors = [];
        $interceptedInterfaceReference = InterfaceToCallReference::fromInstance($interceptedInterface);
        // register before interceptors
        foreach ($this->getSortedInterceptors($beforeInterceptors) as $beforeInterceptor) {
            $messageProcessors[] = $beforeInterceptor->compileForInterceptedInterface($builder, $interceptedInterfaceReference, $this->endpointAnnotations);
        }

        if ($aroundInterceptors) {
            $aroundInterceptors = $this->getSortedInterceptors($aroundInterceptors);
            $compiledAroundInterceptors = array_map(
                fn (AroundInterceptorBuilder $aroundInterceptor) => $aroundInterceptor->compileForInterceptedInterface($builder, $interceptedInterfaceReference, $this->endpointAnnotations),
                $aroundInterceptors
            );
            $messageProcessorInvocation = new Definition(MessageProcessorInvocationProvider::class, [
                $gatewayInternalProcessor,
            ]);
            $resultConverter = new Definition(PayloadResultMessageConverter::class, [$interceptedInterface->getReturnType()]);
            $messageProcessorInvocation = new Definition(AroundMethodInvocationProvider::class, [
                $messageProcessorInvocation,
                $compiledAroundInterceptors,
            ]);
            $messageProcessors[] = new Definition(MethodInvocationProcessor::class, [
                $messageProcessorInvocation,
                $resultConverter,
            ]);
        } else {
            $messageProcessors[] = $gatewayInternalProcessor;
        }
        foreach ($this->getSortedInterceptors($afterInterceptors) as $afterInterceptor) {
            $messageProcessors[] = $afterInterceptor->compileForInterceptedInterface($builder, $interceptedInterfaceReference, $this->endpointAnnotations);
        }

        return new Definition(ChainedMessageProcessor::class, [
            $messageProcessors,
        ]);
    }

    /**
     * @template T of InterceptorWithPointCut
     * @param T[] $interceptors
     * @return T[]
     */
    private function getSortedInterceptors(array $interceptors): array
    {
        usort(
            $interceptors,
            function (InterceptorWithPointCut $a, InterceptorWithPointCut $b) {
                return $a->getPrecedence() <=> $b->getPrecedence();
            }
        );

        return $interceptors;
    }

    public function getProxyMethodReference(): GatewayProxyMethodReference
    {
        return new GatewayProxyMethodReference(
            new GatewayProxyReference($this->referenceName, $this->interfaceName),
            $this->methodName
        );
    }

    public function __toString()
    {
        return sprintf('Gateway - %s:%s with reference name `%s` for request channel `%s`', $this->interfaceName, $this->methodName, $this->referenceName, $this->requestChannelName);
    }

    private function hasConverterFor(\Ecotone\Messaging\Handler\InterfaceParameter $parameter): bool
    {
        foreach ($this->methodArgumentConverters as $parameterConverter) {
            if ($parameterConverter->isHandling($parameter)) {
                return true;
            }
        }

        return false;
    }
}
