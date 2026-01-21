<?php

declare(strict_types=1);

namespace Ecotone\OpenTelemetry;

use Ecotone\Messaging\Channel\ChannelInterceptor;
use Ecotone\Messaging\Message;
use Ecotone\Messaging\MessageChannel;
use Ecotone\Messaging\MessageHeaders;
use Ecotone\Messaging\Support\MessageBuilder;
use Ecotone\OpenTelemetry\Support\MessagingAttributes;

use function json_decode;
use function json_encode;

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\Span as APISpan;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Trace\Span;
use Throwable;

/**
 * licence Apache-2.0
 */
final class TracingChannelInterceptor implements ChannelInterceptor
{
    public const TRACING_CARRIER_HEADER = 'ecotoneTracingCarrier';
    private const MESSAGING_SYSTEM = MessagingAttributes::SYSTEM_ECOTONE;

    public function __construct(private string $channelName, private TracerProviderInterface $tracerProvider)
    {
    }

    public function preSend(Message $message, MessageChannel $messageChannel): ?Message
    {
        $spanName = MessagingAttributes::buildSpanName(MessagingAttributes::OPERATION_SEND, $this->channelName);
        
        $span = EcotoneSpanBuilder::createWithMessagingAttributes(
            $message,
            $spanName,
            $this->tracerProvider,
            self::MESSAGING_SYSTEM,
            $this->channelName,
            MessagingAttributes::OPERATION_SEND,
            SpanKind::KIND_PRODUCER
        )->startSpan();

        $scope = $span->activate();
        $ctx = $span->storeInContext(Context::getCurrent());
        $carrier = [];
        TraceContextPropagator::getInstance()->inject($carrier, null, $ctx);

        return MessageBuilder::fromMessage($message)
                ->setHeader(self::TRACING_CARRIER_HEADER, json_encode($carrier))
                ->setHeader(MessageHeaders::TEMPORARY_SPAN_CONTEXT_HEADER, $scope)
                ->build();
    }

    public function postSend(Message $message, MessageChannel $messageChannel): void
    {
        // @TODO Remove header from message after notice are stopped from OpenTelemetry (https://github.com/open-telemetry/opentelemetry-php/issues/1138)
        $currentContext = $message->getHeaders()->get(MessageHeaders::TEMPORARY_SPAN_CONTEXT_HEADER);
        //        $currentContext = Context::storage()->scope();
        $currentRelatedSpan = Span::getCurrent();
        $currentContext->detach();
        $currentRelatedSpan->end();
    }

    public function afterSendCompletion(Message $message, MessageChannel $messageChannel, ?Throwable $exception): bool
    {
        return false;
    }

    public function preReceive(MessageChannel $messageChannel): bool
    {
        return true;
    }

    public function afterReceiveCompletion(?Message $message, MessageChannel $messageChannel, ?Throwable $exception): void
    {
        if ($exception !== null && $message !== null) {
            $carrier = $message->getHeaders()->containsKey(self::TRACING_CARRIER_HEADER) ? json_decode($message->getHeaders()->get(self::TRACING_CARRIER_HEADER), true) : [];
            $context = TraceContextPropagator::getInstance()->extract($carrier);

            $spanName = MessagingAttributes::buildSpanName(MessagingAttributes::OPERATION_RECEIVE, $this->channelName);
            
            $producerSpan = APISpan::fromContext($context);
            $producerSpanContext = $producerSpan->getContext();
            
            $spanBuilder = EcotoneSpanBuilder::createWithMessagingAttributes(
                $message,
                $spanName,
                $this->tracerProvider,
                self::MESSAGING_SYSTEM,
                $this->channelName,
                MessagingAttributes::OPERATION_RECEIVE,
                SpanKind::KIND_CONSUMER
            )
                ->setParent($context);
            
            if ($producerSpanContext->isValid()) {
                $spanBuilder = $spanBuilder->addLink(
                    $producerSpanContext,
                    [MessagingAttributes::MESSAGING_MESSAGE_ID => $message->getHeaders()->getMessageId()]
                );
            }
            
            $span = $spanBuilder->startSpan();

            $span->setStatus(StatusCode::STATUS_ERROR);
            $span->end();
        }
    }

    public function postReceive(Message $message, MessageChannel $messageChannel): ?Message
    {
        return $message;
    }
}
