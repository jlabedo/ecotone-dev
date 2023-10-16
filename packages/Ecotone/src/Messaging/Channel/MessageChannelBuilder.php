<?php

namespace Ecotone\Messaging\Channel;

use Ecotone\Messaging\Config\Container\CompilableBuilder;
use Ecotone\Messaging\Handler\InterfaceToCall;
use Ecotone\Messaging\Handler\InterfaceToCallRegistry;
use Ecotone\Messaging\Handler\ReferenceSearchService;
use Ecotone\Messaging\MessageChannel;

/**
 * Interface MessageChannelBuilder
 * @package Ecotone\Messaging\Channel
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
interface MessageChannelBuilder extends CompilableBuilder
{
    /**
     * @return string
     */
    public function getMessageChannelName(): string;

    /**
     * @return bool
     */
    public function isPollable(): bool;
}
