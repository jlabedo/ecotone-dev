<?php

namespace Ecotone\Messaging\Config\Container;

use Ecotone\Messaging\Handler\InterfaceToCall;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\AroundInterceptorBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\NewMethodInterceptorBuilder;
use Ecotone\Messaging\Support\Assert;

class MethodInterceptorsConfiguration
{
    /**
     * @param array<NewMethodInterceptorBuilder> $beforeInterceptors
     * @param array<AroundInterceptorBuilder> $aroundInterceptors
     * @param array<NewMethodInterceptorBuilder> $afterInterceptors
     */
    public function __construct(
        private array $beforeInterceptors,
        private array $aroundInterceptors,
        private array $afterInterceptors,
    ) {
    }

    public static function createEmpty()
    {
        return new self([], [], []);
    }

    public function getRelatedInterceptors(InterfaceToCall $interfaceToCall, array $endpointAnnotations, array $requiredInterceptorNames): self
    {
        return new self(
            $this->getRelatedInterceptorsFor($this->beforeInterceptors, $interfaceToCall, $endpointAnnotations, $requiredInterceptorNames),
            $this->getRelatedInterceptorsFor($this->aroundInterceptors, $interfaceToCall, $endpointAnnotations, $requiredInterceptorNames),
            $this->getRelatedInterceptorsFor($this->afterInterceptors, $interfaceToCall, $endpointAnnotations, $requiredInterceptorNames),
        );
    }

    public static function hookIntoMethodCall(
        self $configuration,
        Definition $methodCallProviderDefinition
    ): Definition
    {
        $afterInterceptors = [];
        foreach ($configuration->getAfterInterceptors() as $afterInterceptor) {
            $afterInterceptors[] = $afterInterceptor->compileForInterceptedInterface($builder, $configuration->getInterceptedInterface(), $configuration->getEndpointAnnotations());
        }
        $aroundInterceptors = [];
        foreach ($configuration->getAroundInterceptors() as $aroundInterceptor) {
            $aroundInterceptors[] = $aroundInterceptor->compileForInterceptedInterface($builder, $configuration->interfaceToCall, $configuration->getEndpointAnnotations());
        }
    }

    /**
     * @template T
     * @param array<T> $interceptors
     * @param InterfaceToCall $interceptedInterface
     * @param array<AttributeDefinition> $endpointAnnotations
     * @param array<string> $requiredInterceptorNames
     * @return array<T>
     * @throws \Ecotone\Messaging\MessagingException
     */
    private function getRelatedInterceptorsFor(array $interceptors, InterfaceToCall $interceptedInterface, array $endpointAnnotations, array $requiredInterceptorNames): iterable
    {
        Assert::allInstanceOfType($endpointAnnotations, AttributeDefinition::class);

        $relatedInterceptors = [];

        $endpointAnnotationsInstances = array_map(
            fn (AttributeDefinition $attributeDefinition) => $attributeDefinition->instance(),
            $endpointAnnotations
        );
        foreach ($interceptors as $interceptor) {
            foreach ($requiredInterceptorNames as $requiredInterceptorName) {
                if ($interceptor->hasName($requiredInterceptorName)) {
                    $relatedInterceptors[] = $interceptor;
                    break;
                }
            }

            if ($interceptor->doesItCutWith($interceptedInterface, $endpointAnnotationsInstances)) {
                $relatedInterceptors[] = $interceptor->addInterceptedInterfaceToCall($interceptedInterface, $endpointAnnotations);
            }
        }

        return $relatedInterceptors;
    }

    /**
     * @return NewMethodInterceptorBuilder[]
     */
    public function getBeforeInterceptors(): array
    {
        return $this->beforeInterceptors;
    }

    /**
     * @return AroundInterceptorBuilder[]
     */
    public function getAroundInterceptors(): array
    {
        return $this->aroundInterceptors;
    }

    /**
     * @return NewMethodInterceptorBuilder[]
     */
    public function getAfterInterceptors(): array
    {
        return $this->afterInterceptors;
    }
}