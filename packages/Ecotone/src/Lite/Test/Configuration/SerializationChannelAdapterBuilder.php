<?php

declare(strict_types=1);

namespace Ecotone\Lite\Test\Configuration;

use Ecotone\Messaging\Channel\ChannelInterceptor;
use Ecotone\Messaging\Channel\ChannelInterceptorBuilder;
use Ecotone\Messaging\Config\Container\ContainerMessagingBuilder;
use Ecotone\Messaging\Config\Container\Definition;
use Ecotone\Messaging\Config\Container\Reference;
use Ecotone\Messaging\Conversion\ConversionService;
use Ecotone\Messaging\Conversion\MediaType;
use Ecotone\Messaging\Handler\InterfaceToCallRegistry;
use Ecotone\Messaging\Handler\ReferenceSearchService;
use Ecotone\Messaging\Precedence;

final class SerializationChannelAdapterBuilder implements ChannelInterceptorBuilder
{
    public function __construct(private string $relatedChannel, private MediaType $targetMediaType)
    {
    }

    public function relatedChannelName(): string
    {
        return $this->relatedChannel;
    }

    public function getPrecedence(): int
    {
        return Precedence::DEFAULT_PRECEDENCE;
    }

    public function compile(ContainerMessagingBuilder $builder): Definition
    {
        return new Definition(SerializationChannelAdapter::class, [
            $this->targetMediaType,
            Reference::to(ConversionService::REFERENCE_NAME)
        ]);
    }
}
