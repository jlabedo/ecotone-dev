<?php
/*
 * licence Enterprise
 */
declare(strict_types=1);

namespace Ecotone\OpenTelemetry;

use Ecotone\Messaging\Channel\MessageChannelInterceptorAdapter;
use Ecotone\Messaging\MessageChannel;
use OpenTelemetry\SemConv\Incubating\Attributes\MessagingIncubatingAttributes;

/**
 * Helper class for OpenTelemetry messaging semantic convention attributes.
 *
 * @see https://opentelemetry.io/docs/specs/semconv/messaging/messaging-spans/
 *
 * licence Apache-2.0
 */
final class MessagingAttributes
{
    public const MESSAGING_SYSTEM = MessagingIncubatingAttributes::MESSAGING_SYSTEM;
    public const MESSAGING_DESTINATION_NAME = MessagingIncubatingAttributes::MESSAGING_DESTINATION_NAME;
    public const MESSAGING_OPERATION_TYPE = MessagingIncubatingAttributes::MESSAGING_OPERATION_TYPE;
    public const MESSAGING_MESSAGE_ID = MessagingIncubatingAttributes::MESSAGING_MESSAGE_ID;
    public const MESSAGING_BATCH_MESSAGE_COUNT = MessagingIncubatingAttributes::MESSAGING_BATCH_MESSAGE_COUNT;

    public const OPERATION_SEND = MessagingIncubatingAttributes::MESSAGING_OPERATION_TYPE_VALUE_SEND;
    public const OPERATION_RECEIVE = MessagingIncubatingAttributes::MESSAGING_OPERATION_TYPE_VALUE_RECEIVE;
    public const OPERATION_PROCESS = MessagingIncubatingAttributes::MESSAGING_OPERATION_TYPE_VALUE_PROCESS;
    public const OPERATION_CREATE = MessagingIncubatingAttributes::MESSAGING_OPERATION_TYPE_VALUE_CREATE;

    public const SYSTEM_ECOTONE = 'ecotone';
    public const SYSTEM_RABBITMQ = 'rabbitmq';
    public const SYSTEM_KAFKA = 'kafka';
    public const SYSTEM_SQS = 'sqs';
    public const SYSTEM_REDIS = 'redis';
    public const SYSTEM_DBAL = 'dbal';

    /**
     * Build messaging attributes for a span.
     *
     * @param string $system Messaging system identifier
     * @param string $destinationName Destination/channel name
     * @param string $operation Operation type (send, receive, process, create)
     * @param string|null $messageId Message ID
     * @param int|null $batchMessageCount Number of messages in batch (if applicable)
     * @return array<string, mixed>
     */
    public static function build(
        string $system,
        string $destinationName,
        string $operation,
        ?string $messageId = null,
        ?int $batchMessageCount = null
    ): array {
        $attributes = [
            self::MESSAGING_SYSTEM => $system,
            self::MESSAGING_DESTINATION_NAME => $destinationName,
            self::MESSAGING_OPERATION_TYPE => $operation,
        ];

        if ($messageId !== null) {
            $attributes[self::MESSAGING_MESSAGE_ID] = $messageId;
        }

        if ($batchMessageCount !== null) {
            $attributes[self::MESSAGING_BATCH_MESSAGE_COUNT] = $batchMessageCount;
        }

        return $attributes;
    }

    /**
     * Build span name following semantic convention: "{operation} {destination}".
     */
    public static function buildSpanName(string $operation, string $destination): string
    {
        return $operation . ' ' . $destination;
    }

    /**
     * Detect messaging system from MessageChannel instance class name.
     * Returns the appropriate system identifier based on the channel implementation.
     */
    public static function detectSystemFromChannel(MessageChannel $channel): string
    {
        if ($channel instanceof MessageChannelInterceptorAdapter) {
            $channel = $channel->getInternalMessageChannel();
        }
        $className = get_class($channel);

        return match (true) {
            str_contains($className, 'Kafka\\') => self::SYSTEM_KAFKA,
            str_contains($className, 'Amqp\\') => self::SYSTEM_SQS,
            str_contains($className, 'Redis\\') => self::SYSTEM_REDIS,
            str_contains($className, 'Dbal\\') => self::SYSTEM_DBAL,
            default => self::SYSTEM_ECOTONE,
        };
    }
}
