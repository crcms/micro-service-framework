<?php

namespace CrCms\Microservice\Server\Middleware;

use CrCms\Foundation\Helpers\InstanceConcern;
use CrCms\Microservice\Server\Contracts\RequestContract;
use CrCms\Microservice\Server\Contracts\ResponseContract;
use Closure;
use CrCms\Microservice\Server\Packer\Packer;
use UnexpectedValueException;

/**
 * Class DataEncryptDecryptMiddleware
 * @package CrCms\Microservice\Server\Middleware
 */
class DataEncryptDecryptMiddleware
{
    /**
     * @var Packer
     */
    protected $packer;

    /**
     * DataEncryptDecrypt constructor.
     * @param Packer $packer
     */
    public function __construct(Packer $packer)
    {
        $this->packer = $packer;
    }

    /**
     * @param RequestContract $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(RequestContract $request, Closure $next)
    {
        $secretStatus = config('app.secret_status');

        /* 前置执行 */
        $data = $this->packer->unpack(
            $this->parseContent($request->rawData())['data'],
            $secretStatus);

        $request->setCurrentCall($data['call']);
        $request->setData($data['data'] ?? []);

        /* @var ResponseContract $response */
        $response = $next($request);

        /* 后置执行 */
        $response->setData(['data' => $this->packer->pack($response->getData(true), $secretStatus)]);

        return $response;
    }

    /**
     * @param $content
     * @return array
     */
    protected function parseContent($content): array
    {
        $data = json_decode($content, true);
        if (json_last_error() !== 0) {
            throw new UnexpectedValueException("Parse data error: " . json_last_error_msg());
        }

        return $data;
    }
}