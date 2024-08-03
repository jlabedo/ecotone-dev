<?php

namespace Test\Ecotone\Messaging\Unit\Handler\Processor;

use Ecotone\Lite\EcotoneLite;
use Ecotone\Messaging\Support\GenericMessage;
use Ecotone\Modelling\CommandBus;
use PHPUnit\Framework\TestCase;
use Test\Ecotone\Messaging\Fixture\InterceptorsOrdering\Gateway;
use Test\Ecotone\Messaging\Fixture\InterceptorsOrdering\GatewayInterceptors;
use Test\Ecotone\Messaging\Fixture\InterceptorsOrdering\InterceptorOrderingCase;
use Test\Ecotone\Messaging\Fixture\InterceptorsOrdering\InterceptorOrderingServiceActivatorCase;
use Test\Ecotone\Messaging\Fixture\InterceptorsOrdering\InterceptorOrderingStack;
use Test\Ecotone\Messaging\Fixture\InterceptorsOrdering\InterceptorOrderingWithoutAfterCase;

class InterceptorsOrderingTestCase extends TestCase
{
    public function testInterceptorsAreCalledInOrder(): void
    {
        $ecotone = EcotoneLite::bootstrapFlowTesting(
            [InterceptorOrderingCase::class],
            [new InterceptorOrderingCase()],
        );
        $callStack = new InterceptorOrderingStack();
        /** @var CommandBus $commandBus */
        $commandBus = $ecotone->getGateway(CommandBus::class);
        $return = $commandBus->sendWithRouting("endpoint", $callStack);

        self::assertSame($callStack, $return);

        self::assertEquals(
            [
                ["beforeChangeHeaders", []],
                ["before", ["beforeChangeHeaders" => "header"]],
                ["around begin", ["beforeChangeHeaders" => "header"]],
                ["endpoint", ["beforeChangeHeaders" => "header"]],
                ["afterChangeHeaders", ["beforeChangeHeaders" => "header"]],
                ["after", ["beforeChangeHeaders" => "header", "afterChangeHeaders" => "header"]],
                ["around end", ["beforeChangeHeaders" => "header"], GenericMessage::class],
            ],
            $callStack->getCalls()
        );
    }

    public function testInterceptorsAreCalledInOrderWithoutAfterInterceptors(): void
    {
        $ecotone = EcotoneLite::bootstrapFlowTesting(
            [InterceptorOrderingWithoutAfterCase::class],
            [new InterceptorOrderingWithoutAfterCase()],
        );
        $callStack = new InterceptorOrderingStack();
        /** @var CommandBus $commandBus */
        $commandBus = $ecotone->getGateway(CommandBus::class);
        $return = $commandBus->sendWithRouting("endpoint", $callStack);

        self::assertSame($callStack, $return);

        self::assertEquals(
            [
                ["beforeChangeHeaders", []],
                ["before", ["beforeChangeHeaders" => "header"]],
                ["around begin", ["beforeChangeHeaders" => "header"]],
                ["endpoint", ["beforeChangeHeaders" => "header"]],
                ["around end", ["beforeChangeHeaders" => "header"], GenericMessage::class],
            ],
            $callStack->getCalls()
        );
    }

    public function testInterceptorsAreCalledInOrderOnServiceActivator(): void
    {
        $ecotone = EcotoneLite::bootstrapFlowTesting(
            [InterceptorOrderingServiceActivatorCase::class],
            [new InterceptorOrderingServiceActivatorCase()],
        );
        $callStack = new InterceptorOrderingStack();
        $ecotone->sendDirectToChannel("runEndpoint", $callStack);

        self::assertEquals(
            [
                ["beforeChangeHeaders", []],
                ["before", ["beforeChangeHeaders" => "header"]],
                ["around begin", ["beforeChangeHeaders" => "header"]],
                ["endpoint", ["beforeChangeHeaders" => "header"]],
                ["around end", ["beforeChangeHeaders" => "header"], GenericMessage::class],
            ],
            $callStack->getCalls()
        );
    }

    public function testInterceptorsAreCalledInOrderWithGateway(): void
    {
        $ecotone = EcotoneLite::bootstrapFlowTesting(
            [InterceptorOrderingServiceActivatorCase::class, Gateway::class, GatewayInterceptors::class],
            [new InterceptorOrderingServiceActivatorCase(), new GatewayInterceptors()],
        );
        $callStack = new InterceptorOrderingStack();
        $return = $ecotone->getGateway(Gateway::class)->runWithReturn($callStack);

        self::assertSame($callStack, $return);

        self::assertEquals(
            [
                ["gateway::around begin", []],
                ["beforeChangeHeaders", []],
                ["before", ["beforeChangeHeaders" => "header"]],
                ["around begin", ["beforeChangeHeaders" => "header"]],
                ["endpoint", ["beforeChangeHeaders" => "header"]],
                ["around end", ["beforeChangeHeaders" => "header"], GenericMessage::class],
                ["gateway::around end", [], GenericMessage::class],
            ],
            $callStack->getCalls()
        );
    }

    public function testInterceptorsAreCalledInOrderWithGatewayAndVoidReturnType(): void
    {
        $ecotone = EcotoneLite::bootstrapFlowTesting(
            [InterceptorOrderingServiceActivatorCase::class, Gateway::class, GatewayInterceptors::class],
            [new InterceptorOrderingServiceActivatorCase(), new GatewayInterceptors()],
        );
        $callStack = new InterceptorOrderingStack();
        $ecotone->getGateway(Gateway::class)->runWithVoid($callStack);

        self::assertEquals(
            [
                ["gateway::around begin", []],
                ["beforeChangeHeaders", []],
                ["before", ["beforeChangeHeaders" => "header"]],
                ["around begin", ["beforeChangeHeaders" => "header"]],
                ["endpoint", ["beforeChangeHeaders" => "header"]],
                ["around end", ["beforeChangeHeaders" => "header"], GenericMessage::class],
                ["gateway::around end", []],
            ],
            $callStack->getCalls()
        );
    }
}