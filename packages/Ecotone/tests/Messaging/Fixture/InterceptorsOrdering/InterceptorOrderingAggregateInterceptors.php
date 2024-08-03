<?php

namespace Test\Ecotone\Messaging\Fixture\InterceptorsOrdering;

use Ecotone\Messaging\Attribute\Interceptor\After;
use Ecotone\Messaging\Attribute\Interceptor\Around;
use Ecotone\Messaging\Attribute\Interceptor\Before;
use Ecotone\Messaging\Attribute\Parameter\Headers;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\MethodInvocation;

class InterceptorOrderingAggregateInterceptors
{
    #[After(precedence: -1, pointcut: InterceptorOrderingAggregate::class, changeHeaders: true)]
    public function afterChangeHeaders(#[Headers] array $metadata): array
    {
        $stack = $metadata["stack"];
        $stack->add("afterChangeHeaders", $metadata);
        return [...$metadata, "afterChangeHeaders" => "header"];
    }

    #[After(pointcut: InterceptorOrderingAggregate::class)]
    public function after(#[Headers] array $metadata): void
    {
        $stack = $metadata["stack"];
        $stack->add("after", $metadata);
    }

    #[Before(precedence: -1, pointcut: InterceptorOrderingAggregate::class, changeHeaders: true)]
    public function beforeChangeHeaders(#[Headers] array $metadata): array
    {
        $stack = $metadata["stack"];
        $stack->add("beforeChangeHeaders", $metadata);
        return [...$metadata, "beforeChangeHeaders" => "header"];
    }

    #[Before(pointcut: InterceptorOrderingAggregate::class)]
    public function before(#[Headers] array $metadata): void
    {
        $stack = $metadata["stack"];
        $stack->add("before", $metadata);
    }

    #[Around(pointcut: InterceptorOrderingAggregate::class)]
    public function around(MethodInvocation $methodInvocation, #[Headers] array $metadata): mixed
    {
        $stack = $metadata["stack"];
        $stack->add("around begin", $metadata);
        $result = $methodInvocation->proceed();
        $stack->add("around end", $metadata, $result);
        return $result;
    }
}