<?php

declare(strict_types=1);

namespace Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter;

use Ecotone\Messaging\Config\Container\CompilableParameterConverterBuilder;
use Ecotone\Messaging\Config\Container\ContainerMessagingBuilder;
use Ecotone\Messaging\Config\Container\Definition;
use Ecotone\Messaging\Config\Container\Reference;
use Ecotone\Messaging\Handler\InterfaceParameter;
use Ecotone\Messaging\Handler\InterfaceToCall;
use Ecotone\Messaging\Handler\ParameterConverter;
use Ecotone\Messaging\Handler\ParameterConverterBuilder;
use Ecotone\Messaging\Handler\ReferenceSearchService;

/**
 * Class ReferenceBuilder
 * @package Ecotone\Messaging\Handler\Processor\MethodInvoker
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class ReferenceBuilder implements ParameterConverterBuilder
{
    private function __construct(private string $parameterName, private string $referenceServiceName)
    {
    }

    /**
     * @param string $parameterName
     * @param string $referenceServiceName
     * @return ReferenceBuilder
     */
    public static function create(string $parameterName, string $referenceServiceName): self
    {
        return new self($parameterName, $referenceServiceName);
    }

    /**
     * @inheritDoc
     */
    public function isHandling(InterfaceParameter $parameter): bool
    {
        return $parameter->getName() === $this->parameterName;
    }

    public function compile(ContainerMessagingBuilder $builder, InterfaceToCall $interfaceToCall, InterfaceParameter $interfaceParameter): Definition
    {
        return new Definition(ValueConverter::class, [new Reference($this->referenceServiceName)]);
    }
}
