<?php

namespace App\QueryInterceptor;

use Ecotone\Modelling\Attribute\QueryHandler;

class QueryService
{
    #[QueryHandler("getProductName")]
    public function getProductName(): ?QueryResult
    {
        return new QueryResult("Milk");
    }
}