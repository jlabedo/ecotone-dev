<?php

declare(strict_types=1);

namespace Ecotone\OpenTelemetry;

use Ecotone\Messaging\Handler\Processor\MethodInvoker\MethodInvocation;
use Ecotone\Messaging\Message;
use Ecotone\Messaging\MessageHeaders;
use Ecotone\Messaging\Support\MessageBuilder;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\Span as APISpan;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\Span;
use Throwable;
use function json_decode;

/**
 * licence Apache-2.0
 */
final class TracerInterceptor
{
    public function __construct(private TracerProviderInterface $tracerProvider)
    {
    }

    public function traceAsynchronousEndpoint(MethodInvocation $methodInvocation, Message $message)
    {
        $carrier = $message->getHeaders()->containsKey(TracingChannelInterceptor::TRACING_CARRIER_HEADER) ? json_decode($message->getHeaders()->get(TracingChannelInterceptor::TRACING_CARRIER_HEADER), true) : [];
        $parentContext = TraceContextPropagator::getInstance()->extract($carrier);

        $scope = $parentContext->activate();
        try {
            $destinationName = $this->getDestinationName($methodInvocation, $message);

            $spanName = MessagingAttributes::buildSpanName(MessagingAttributes::OPERATION_PROCESS, $destinationName);

            $producerSpan = APISpan::fromContext($parentContext);
            $producerSpanContext = $producerSpan->getContext();
            
            $spanBuilder = EcotoneSpanBuilder::createWithMessagingAttributes(
                $message,
                $spanName,
                $this->tracerProvider,
                MessagingAttributes::SYSTEM_ECOTONE,
                $destinationName,
                MessagingAttributes::OPERATION_PROCESS,
                SpanKind::KIND_CONSUMER
            )
                ->setParent($parentContext);
            
            if ($producerSpanContext->isValid()) {
                $spanBuilder = $spanBuilder->addLink(
                    $producerSpanContext,
                    [MessagingAttributes::MESSAGING_MESSAGE_ID => $message->getHeaders()->getMessageId()]
                );
            }
            
            $span = $spanBuilder->startSpan();

            $spanScope = $span->activate();

            try {
                $result = $methodInvocation->proceed();
            } catch (Throwable $exception) {
                $span->recordException($exception);
                $this->closeSpan($span, $spanScope, StatusCode::STATUS_ERROR, $exception->getMessage());
                $scope->detach();

                throw $exception;
            }

            $this->closeSpan($span, $spanScope, StatusCode::STATUS_OK, null);
            $scope->detach();

            return $result;
        } catch (Throwable $exception) {
            $scope->detach();

            throw $exception;
        }
    }

    public function provideContextForDistributedBus(Message $message): Message
    {
        $ctx = Span::getCurrent()->storeInContext(Context::getCurrent());
        $carrier = [];
        TraceContextPropagator::getInstance()->inject($carrier, null, $ctx);

        return MessageBuilder::fromMessage($message)
            ->setHeader(TracingChannelInterceptor::TRACING_CARRIER_HEADER, json_encode($carrier))
            ->build();
    }

    public function traceDistributedBus(MethodInvocation $methodInvocation, Message $message): mixed
    {
        $span = EcotoneSpanBuilder::create($message, 'Distributed Bus', $this->tracerProvider, SpanKind::KIND_PRODUCER)
            ->startSpan();
        $spanScope = $span->activate();

        $result = $methodInvocation->proceed();

        $this->closeSpan($span, $spanScope, StatusCode::STATUS_OK, null);

        return $result;
    }

    public function traceCommandHandler(MethodInvocation $methodInvocation, Message $message)
    {
        return $this->trace(
            'Command Handler: ' . $methodInvocation->getName(),
            $methodInvocation,
            $message,
        );
    }

    public function traceQueryHandler(MethodInvocation $methodInvocation, Message $message)
    {
        return $this->trace(
            'Query Handler: ' . $methodInvocation->getName(),
            $methodInvocation,
            $message,
        );
    }

    public function traceEventHandler(MethodInvocation $methodInvocation, Message $message)
    {
        return $this->trace(
            'Event Handler: ' . $methodInvocation->getName(),
            $methodInvocation,
            $message,
        );
    }

    public function traceMessageHandler(MethodInvocation $methodInvocation, Message $message)
    {
        return $this->trace(
            'Message Handler: ' . $methodInvocation->getName(),
            $methodInvocation,
            $message,
        );
    }

    public function traceCommandBus(MethodInvocation $methodInvocation, Message $message)
    {
        return $this->trace(
            'Command Bus',
            $methodInvocation,
            $message,
        );
    }

    public function traceEventBus(MethodInvocation $methodInvocation, Message $message)
    {
        return $this->trace(
            'Event Bus',
            $methodInvocation,
            $message,
        );
    }

    public function traceQueryBus(MethodInvocation $methodInvocation, Message $message)
    {
        return $this->trace(
            'Query Bus',
            $methodInvocation,
            $message,
        );
    }

    public function trace(
        string $type,
        MethodInvocation $methodInvocation,
        Message $message,
        array $attributes = [],
        int $spanKind = SpanKind::KIND_SERVER,
    ) {
        $span = EcotoneSpanBuilder::create(
            $message,
            $type,
            $this->tracerProvider,
            $spanKind
        )
            ->startSpan();
        $spanScope = $span->activate();

        try {
            $result = $methodInvocation->proceed();
        } catch (Throwable $exception) {
            $span->recordException($exception);
            $this->closeSpan($span, $spanScope, StatusCode::STATUS_ERROR, $exception->getMessage());

            throw $exception;
        }

        $this->closeSpan($span, $spanScope, StatusCode::STATUS_OK, null);

        return $result;
    }

    private function closeSpan(SpanInterface $span, ScopeInterface $spanScope, string $statusCode, ?string $descriptionStatusCode): void
    {
        $currentSpan = Span::getCurrent();
        $currentContext = Context::getCurrent();

        $span->setStatus($statusCode, $descriptionStatusCode);
        $spanScope->detach();
        $span->end();
    }

    private function getDestinationName(MethodInvocation $methodInvocation, Message $message): string
    {
        if ($message->getHeaders()->containsKey(MessageHeaders::ROUTING_SLIP)) {
            try {
                $routingSlip = $message->getHeaders()->resolveRoutingSlip();
                if (!empty($routingSlip)) {
                    $destination = $routingSlip[0];
                    $destination = $this->normalizeEndpointId($destination);
                    return $destination;
                }
            } catch (\Ecotone\Messaging\MessagingException $e) {
            }
        }
        
        if ($message->getHeaders()->containsKey(MessageHeaders::CONSUMER_POLLING_METADATA)) {
            return $message->getHeaders()->get(MessageHeaders::CONSUMER_POLLING_METADATA)->getEndpointId();
        }
        
        if ($message->getHeaders()->containsKey(MessageHeaders::POLLED_CHANNEL_NAME)) {
            return $message->getHeaders()->get(MessageHeaders::POLLED_CHANNEL_NAME);
        }
        
        return 'unknown';
    }

    private function normalizeEndpointId(string $destination): string
    {
        $suffixes = [
            '.target-connection.execute',
            '.target-connection',
            '.target.execute',
            '-connection.execute',
            '.target',
            '-connection',
            '.execute',
        ];

        foreach ($suffixes as $suffix) {
            if (str_ends_with($destination, $suffix)) {
                return substr($destination, 0, -strlen($suffix));
            }
        }

        return $destination;
    }
}
