<?php

namespace Ecotone\Messaging\Handler\Processor\MethodInvoker;

use function array_merge;

use Ecotone\Messaging\Config\Container\AttributeDefinition;
use Ecotone\Messaging\Config\Container\AttributeReference;
use Ecotone\Messaging\Handler\InterfaceParameter;
use Ecotone\Messaging\Handler\InterfaceToCall;
use Ecotone\Messaging\Handler\ParameterConverterBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\AllHeadersBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\MessageConverterBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\PayloadBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\ReferenceBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\ValueBuilder;
use Ecotone\Messaging\Handler\TypeDescriptor;
use Ecotone\Messaging\MessagingException;
use Ecotone\Messaging\Support\InvalidArgumentException;

class MethodArgumentsFactory
{
    /**
     * @param ParameterConverterBuilder[] $passedMethodParameterConverters
     * @param object[] $endpointAnnotations
     * @return ParameterConverterBuilder[]
     */
    public static function createDefaultMethodParameters(InterfaceToCall $interfaceToCall, array $passedMethodParameterConverters, array $endpointAnnotations, ?InterfaceToCall $interceptedInterface, bool $ignorePayload): array
    {
        $passedArgumentsCount = count($passedMethodParameterConverters);
        $requiredArgumentsCount = count($interfaceToCall->getInterfaceParameters());
        $missingParametersAmount = $requiredArgumentsCount - $passedArgumentsCount;

        if ($missingParametersAmount > 0) {
            if ($interceptedInterface) {
                foreach ($interfaceToCall->getInterfaceParameters() as $interfaceParameter) {
                    if (! self::hasParameterConverter($passedMethodParameterConverters, $interfaceParameter) && $interfaceParameter->isAnnotation()) {
                        $passedMethodParameterConverters[] = self::getAnnotationValueConverter($interfaceParameter, $interceptedInterface, $endpointAnnotations) ?? new ValueBuilder($interfaceParameter->getName(), null);
                        $missingParametersAmount--;
                    }
                }
            }

            if ($missingParametersAmount >= 2 && $interfaceToCall->getSecondParameter()->getTypeDescriptor()->isNonCollectionArray()) {
                if (! $ignorePayload && ! self::hasPayloadConverter($passedMethodParameterConverters) && ! $interfaceToCall->getFirstParameter()->isAnnotation()) {
                    $passedMethodParameterConverters[] = self::createPayloadOrMessageParameter($interfaceToCall->getFirstParameter());
                }
                $passedMethodParameterConverters[] = AllHeadersBuilder::createWith($interfaceToCall->getSecondParameter()->getName());
            } elseif (! $ignorePayload && ! self::hasPayloadConverter($passedMethodParameterConverters) && ! $interfaceToCall->getFirstParameter()->isAnnotation()) {
                $passedMethodParameterConverters[] = self::createPayloadOrMessageParameter($interfaceToCall->getFirstParameter());
            }

            foreach ($interfaceToCall->getInterfaceParameters() as $interfaceParameter) {
                if ($interfaceParameter->isAnnotation()) {
                    continue;
                }
                if (! self::hasParameterConverter($passedMethodParameterConverters, $interfaceParameter) && $interfaceParameter->isMessage()) {
                    $passedMethodParameterConverters[] = MessageConverterBuilder::create($interfaceParameter->getName());
                } elseif (! self::hasParameterConverter($passedMethodParameterConverters, $interfaceParameter) && $interfaceParameter->getTypeDescriptor()->isClassOrInterface()) {
                    $passedMethodParameterConverters[] = ReferenceBuilder::create($interfaceParameter->getName(), $interfaceParameter->getTypeHint());
                }
            }
        }

        $parametersForObjectToInvoke = $interfaceToCall->getInterfaceParameters();

        $orderedMethodArguments = [];
        foreach ($parametersForObjectToInvoke as $invokeParameter) {
            $orderedMethodArguments[] = self::getMethodArgumentFor($invokeParameter, $passedMethodParameterConverters, $interfaceToCall);
        }

        return $orderedMethodArguments;
    }

    /**
     * @param AttributeDefinition[] $endpointAnnotations
     */
    public static function getAnnotationValueConverter(InterfaceParameter $interfaceParameter, InterfaceToCall $interceptedInterface, array $endpointAnnotations): ?ValueBuilder
    {
        if ($result = static::getConverterForAttributeDefinition($interfaceParameter, $endpointAnnotations)) {
            return $result;
        }

        $allAnnotations = array_merge(
            $interceptedInterface->getMethodAnnotations(),
            $interceptedInterface->getClassAnnotations()
        );
        foreach ($allAnnotations as $endpointAnnotation) {
            if (TypeDescriptor::createFromVariable($endpointAnnotation)->equals($interfaceParameter->getTypeDescriptor())) {
                return new ValueBuilder($interfaceParameter->getName(), $endpointAnnotation);
            }
        }
        foreach ($allAnnotations as $endpointAnnotation) {
            if (TypeDescriptor::createFromVariable($endpointAnnotation)->isCompatibleWith($interfaceParameter->getTypeDescriptor())) {
                return new ValueBuilder($interfaceParameter->getName(), $endpointAnnotation);
            }
        }
        return null;
    }

    public static function getAnnotationValueDefinitionOrReference(InterfaceParameter $interfaceParameter, InterfaceToCall $interceptedInterface, array $endpointAnnotations): AttributeReference|AttributeDefinition|null
    {
        $interfaceParameterType = $interfaceParameter->getTypeDescriptor();
        // Endpoint Annotations
        foreach ($endpointAnnotations as $endpointAnnotation) {
            if (TypeDescriptor::create($endpointAnnotation->getClassName())->equals($interfaceParameterType)) {
                return $endpointAnnotation;
            }
        }
        foreach ($endpointAnnotations as $endpointAnnotation) {
            if (TypeDescriptor::create($endpointAnnotation->getClassName())->isCompatibleWith($interfaceParameterType)) {
                return $endpointAnnotation;
            }
        }

        // Method
        foreach ($interceptedInterface->getMethodAnnotations() as $endpointAnnotation) {
            if (TypeDescriptor::createFromVariable($endpointAnnotation)->equals($interfaceParameter->getTypeDescriptor())) {
                return new AttributeReference(\get_class($endpointAnnotation), $interceptedInterface->getInterfaceName(), $interceptedInterface->getMethodName());
            }
        }
        foreach ($interceptedInterface->getMethodAnnotations() as $endpointAnnotation) {
            if (TypeDescriptor::createFromVariable($endpointAnnotation)->isCompatibleWith($interfaceParameter->getTypeDescriptor())) {
                return new AttributeReference(\get_class($endpointAnnotation), $interceptedInterface->getInterfaceName(), $interceptedInterface->getMethodName());
            }
        }

        // Class
        foreach ($interceptedInterface->getClassAnnotations() as $endpointAnnotation) {
            if (TypeDescriptor::createFromVariable($endpointAnnotation)->equals($interfaceParameter->getTypeDescriptor())) {
                return new AttributeReference(\get_class($endpointAnnotation), $interceptedInterface->getInterfaceName());
            }
        }
        foreach ($interceptedInterface->getClassAnnotations() as $endpointAnnotation) {
            if (TypeDescriptor::createFromVariable($endpointAnnotation)->isCompatibleWith($interfaceParameter->getTypeDescriptor())) {
                return new AttributeReference(\get_class($endpointAnnotation), $interceptedInterface->getInterfaceName());
            }
        }

        return null;
    }

    /**
     * @param AttributeDefinition[] $endpointAnnotations
     */
    public static function getConverterForAttributeDefinition(InterfaceParameter $interfaceParameter, array $endpointAnnotations)
    {
        $interfaceParameterType = $interfaceParameter->getTypeDescriptor();
        foreach ($endpointAnnotations as $endpointAnnotation) {
            if (TypeDescriptor::create($endpointAnnotation->getClassName())->equals($interfaceParameterType)) {
                return new ValueBuilder($interfaceParameter->getName(), $endpointAnnotation);
            }
        }
        foreach ($endpointAnnotations as $endpointAnnotation) {
            if (TypeDescriptor::create($endpointAnnotation->getClassName())->isCompatibleWith($interfaceParameterType)) {
                return new ValueBuilder($interfaceParameter->getName(), $endpointAnnotation);
            }
        }
        return null;
    }

    /**
     * @param ParameterConverterBuilder[] $passedMethodParameterConverters
     * @return bool
     */
    private static function hasPayloadConverter(array $passedMethodParameterConverters): bool
    {
        foreach ($passedMethodParameterConverters as $parameterConverter) {
            if ($parameterConverter instanceof PayloadBuilder) {
                return true;
            }
        }

        return false;
    }

    private static function createPayloadOrMessageParameter(InterfaceParameter $parameter): ParameterConverterBuilder
    {
        return $parameter->isMessage() ? MessageConverterBuilder::create($parameter->getName()) : PayloadBuilder::create($parameter->getName());
    }

    /**
     * @param ParameterConverterBuilder[] $methodParameterConverters
     * @throws MessagingException
     */
    private static function getMethodArgumentFor(InterfaceParameter $invokeParameter, array $methodParameterConverters, InterfaceToCall $interfaceToCall): ParameterConverterBuilder
    {
        foreach ($methodParameterConverters as $methodParameterConverter) {
            if ($methodParameterConverter->isHandling($invokeParameter)) {
                return $methodParameterConverter;
            }
        }

        throw InvalidArgumentException::create("Invoked object {$interfaceToCall->getInterfaceName()}:{$interfaceToCall->getMethodName()} has no converter for parameter `{$invokeParameter->getName()}`");
    }

    private static function hasParameterConverter(array $passedMethodParameterConverters, InterfaceParameter $interfaceParameter): bool
    {
        foreach ($passedMethodParameterConverters as $passedMethodParameterConverter) {
            if ($passedMethodParameterConverter->isHandling($interfaceParameter)) {
                return true;
            }
        }

        return false;
    }
}
