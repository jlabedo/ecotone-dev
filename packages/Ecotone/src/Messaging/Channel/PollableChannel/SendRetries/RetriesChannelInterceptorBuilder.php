<?php

declare(strict_types=1);

namespace Ecotone\Messaging\Channel\PollableChannel\SendRetries;

use Ecotone\Messaging\Channel\ChannelInterceptor;
use Ecotone\Messaging\Channel\ChannelInterceptorBuilder;
use Ecotone\Messaging\Config\ConfiguredMessagingSystem;
use Ecotone\Messaging\Config\Container\CompilableBuilder;
use Ecotone\Messaging\Config\Container\ContainerMessagingBuilder;
use Ecotone\Messaging\Config\Container\Definition;
use Ecotone\Messaging\Config\Container\Reference;
use Ecotone\Messaging\Handler\InterfaceToCallRegistry;
use Ecotone\Messaging\Handler\Logger\LoggingHandlerBuilder;
use Ecotone\Messaging\Handler\Recoverability\RetryTemplate;
use Ecotone\Messaging\Handler\ReferenceSearchService;
use Ecotone\Messaging\PrecedenceChannelInterceptor;
use Psr\Log\LoggerInterface;

final class RetriesChannelInterceptorBuilder implements ChannelInterceptorBuilder
{
    public function __construct(
        private string $relatedChannel,
        private RetryTemplate $retryTemplate,
        private ?string $errorChannel
    ) {
    }

    public function relatedChannelName(): string
    {
        return $this->relatedChannel;
    }

    public function getRequiredReferenceNames(): array
    {
        return [];
    }

    public function resolveRelatedInterfaces(InterfaceToCallRegistry $interfaceToCallRegistry): iterable
    {
        return [];
    }

    public function getPrecedence(): int
    {
        return PrecedenceChannelInterceptor::DEFAULT_PRECEDENCE;
    }

    public function build(ReferenceSearchService $referenceSearchService): ChannelInterceptor
    {
        return new SendRetryChannelInterceptor(
            $this->relatedChannel,
            $this->retryTemplate,
            $this->errorChannel,
            $referenceSearchService->get(ConfiguredMessagingSystem::class),
            $referenceSearchService->get(LoggingHandlerBuilder::LOGGER_REFERENCE)
        );
    }

    public function compile(ContainerMessagingBuilder $builder): Reference|Definition|null
    {
        return new Definition(SendRetryChannelInterceptor::class, [
            $this->relatedChannel,
            $this->retryTemplate,
            $this->errorChannel,
            new Reference(ConfiguredMessagingSystem::class),
            new Reference(LoggerInterface::class),
        ]);
    }
}
