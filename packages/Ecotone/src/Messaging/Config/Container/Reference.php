<?php

namespace Ecotone\Messaging\Config\Container;

use Ecotone\Messaging\Config\Container\Compiler\ContainerImplementation;

class Reference
{
    public function __construct(private string $id, private int $invalidBehavior = ContainerImplementation::EXCEPTION_ON_INVALID_REFERENCE)
    {
    }

    public static function to(string $id): self
    {
        return new self($id);
    }

    public static function toChannel(string $id): ChannelReference
    {
        return new ChannelReference($id);
    }

    public static function toInterface(string $className, string $methodName): InterfaceToCallReference
    {
        return new InterfaceToCallReference($className, $methodName);
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Returns the behavior to be used when the service does not exist.
     */
    public function getInvalidBehavior(): int
    {
        return $this->invalidBehavior;
    }

    public function __toString(): string
    {
        return $this->id;
    }

}
