<?php

declare(strict_types=1);

namespace Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter;

use Ecotone\Messaging\Config\Container\CompilableParameterConverterBuilder;
use Ecotone\Messaging\Config\Container\ContainerMessagingBuilder;
use Ecotone\Messaging\Config\Container\Definition;
use Ecotone\Messaging\Config\Container\Reference;
use Ecotone\Messaging\Conversion\ConversionService;
use Ecotone\Messaging\Handler\InterfaceParameter;
use Ecotone\Messaging\Handler\InterfaceToCall;
use Ecotone\Messaging\Handler\ParameterConverter;
use Ecotone\Messaging\Handler\ParameterConverterBuilder;
use Ecotone\Messaging\Handler\ReferenceSearchService;

/**
 * Class PayloadParameterConverterBuilder
 * @package Ecotone\Messaging\Handler\Processor\MethodInvoker
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class PayloadBuilder implements ParameterConverterBuilder
{
    private function __construct(private string $parameterName)
    {
    }

    /**
     * @param string $parameterName
     * @return PayloadBuilder
     */
    public static function create(string $parameterName): self
    {
        return new self($parameterName);
    }

    public function compile(ContainerMessagingBuilder $builder, InterfaceToCall $interfaceToCall): Definition
    {
        $interfaceParameter = $interfaceToCall->getParameterWithName($this->parameterName);
        return new Definition(PayloadConverter::class, [
            new Reference(ConversionService::REFERENCE_NAME),
            $interfaceToCall->getInterfaceName(),
            $this->parameterName,
            $interfaceParameter->getTypeDescriptor(),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function isHandling(InterfaceParameter $parameter): bool
    {
        return $parameter->getName() === $this->parameterName;
    }
}
