<?php

declare(strict_types=1);

namespace Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter;

use Ecotone\Messaging\Config\Container\AttributeDefinition;
use Ecotone\Messaging\Config\Container\CompilableParameterConverterBuilder;
use Ecotone\Messaging\Config\Container\ContainerMessagingBuilder;
use Ecotone\Messaging\Config\Container\Definition;
use Ecotone\Messaging\Handler\InterfaceParameter;
use Ecotone\Messaging\Handler\InterfaceToCall;
use Ecotone\Messaging\Handler\ParameterConverter;
use Ecotone\Messaging\Handler\ParameterConverterBuilder;
use Ecotone\Messaging\Handler\ReferenceSearchService;

/**
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class ValueBuilder implements ParameterConverterBuilder
{
    public function __construct(private string $parameterName, private mixed $staticValue)
    {
    }

    /**
     * @inheritDoc
     */
    public function isHandling(InterfaceParameter $parameter): bool
    {
        return $parameter->getName() === $this->parameterName;
    }

    public function compile(ContainerMessagingBuilder $builder, InterfaceToCall $interfaceToCall): Definition
    {
        return new Definition(ValueConverter::class, [$this->staticValue]);
    }
}
