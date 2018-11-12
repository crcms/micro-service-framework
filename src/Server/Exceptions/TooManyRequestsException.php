<?php

namespace CrCms\Microservice\Server\Exceptions;

use Throwable;

/**
 * Class TooManyRequestsException
 * @package CrCms\Microservice\Server\Exceptions
 */
class TooManyRequestsException extends ServiceException
{
    /**
     * TooManyRequestsException constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, 429, $previous);
    }
}