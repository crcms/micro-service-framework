<?php

namespace CrCms\Microservice\Server\Http;

use CrCms\Microservice\Server\Contracts\RequestContract;
use Illuminate\Http\Request as BaseRequest;

/**
 * Class Request
 * @package CrCms\Foundation\MicroService\Http
 */
class Request extends BaseRequest implements RequestContract
{
    public static function createRequest(): RequestContract
    {
        return new static();
    }

}