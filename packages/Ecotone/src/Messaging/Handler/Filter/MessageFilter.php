<?php

declare(strict_types=1);

namespace Ecotone\Messaging\Handler\Filter;

use Ecotone\Messaging\Handler\MessageProcessor;
use Ecotone\Messaging\Handler\RealMessageProcessor;
use Ecotone\Messaging\Message;
use Ecotone\Messaging\MessageChannel;
use Ecotone\Messaging\MessageHeaders;

/**
 * Class MessageFilter
 * @package Ecotone\Messaging\Handler\Filter
 * @author  Dariusz Gafka <support@simplycodedsoftware.com>
 * @internal
 */
/**
 * licence Apache-2.0
 */
class MessageFilter
{
    public function __construct(
        private RealMessageProcessor $messageSelector,
        private ?MessageChannel $discardChannel,
        private bool $throwExceptionOnDiscard)
    {
    }

    /**
     * @inheritDoc
     */
    public function handle(Message $message): ?Message
    {
        if (! $this->messageSelector->process($message)) {
            return $message;
        }

        if ($this->discardChannel) {
            $this->discardChannel->send($message);
        }

        if ($this->throwExceptionOnDiscard) {
            throw MessageFilterDiscardException::create("Message with id {$message->getHeaders()->get(MessageHeaders::MESSAGE_ID)} was discarded");
        }

        return null;
    }
}
