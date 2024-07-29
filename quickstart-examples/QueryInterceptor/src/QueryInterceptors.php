<?php

namespace App\QueryInterceptor;

use Ecotone\Messaging\Attribute\Interceptor\After;
use Ecotone\Messaging\Attribute\Interceptor\Around;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\MethodInvocation;

class QueryInterceptors
{
    #[After(pointcut: QueryService::class)]
    public function packWithApiFormat(QueryResult $payload): array
    {
        return [
            "status" => "success",
            "executionTime" => $payload->getExecutionTime(),
            "result" => $payload->getValue(),
        ];
    }

    #[Around(pointcut: QueryService::class)]
    public function addMetadata(MethodInvocation $methodInvocation): mixed
    {
        $begin = microtime(true);
        $result = $methodInvocation->proceed();
        $end = microtime(true);

        $executionTime = $end - $begin;
        if (! $result instanceof QueryResult) {
            echo "The result is not a QueryResult !\n";
        } else {
            $result->setExecutionTime($executionTime);
        }
        return $result;
    }
}