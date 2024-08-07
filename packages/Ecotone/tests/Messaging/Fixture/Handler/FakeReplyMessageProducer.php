<?php

namespace Test\Ecotone\Messaging\Fixture\Handler;

use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\MessageConverter;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\MethodCall;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\MethodCallProvider;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\StaticMethodCallProvider;
use Ecotone\Messaging\Handler\RealMessageProcessor;
use Ecotone\Messaging\Message;
use Ecotone\Messaging\Support\MessageBuilder;

/**
 * Class ReplyMessageProducer
 * @package Test\Ecotone\Messaging\Fixture\Handler
 * @author Dariusz Gafka <support@simplycodedsoftware.com>
 */
/**
 * licence Apache-2.0
 */
class FakeReplyMessageProducer implements RealMessageProcessor
{
    private $replyData;

    /**
     * ReplyMessageProducer constructor.
     * @param $replyData
     */
    private function __construct($replyData)
    {
        $this->replyData = $replyData;
    }

    public static function create($replyData): self
    {
        return new self($replyData);
    }

    /**
     * @inheritDoc
     */
    public function process(Message $message): ?Message
    {
        return MessageBuilder::fromMessage($message)
            ->setPayload($this->replyData)
            ->build();
    }
}
