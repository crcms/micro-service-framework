<?php

namespace CrCms\Microservice\Server\Exceptions;

use Throwable;

/**
 * Class UnprocessableEntityException
 * @package CrCms\Microservice\Server\Exceptions
 */
class UnprocessableEntityException extends ServiceException
{
    /**
     * UnprocessableEntityException constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, 422, $previous);
    }
}