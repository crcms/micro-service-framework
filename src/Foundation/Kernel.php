<?php

namespace CrCms\Microservice\Foundation;

use Exception;
use Throwable;
use ArrayObject;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Facade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Support\Arrayable;
use CrCms\Microservice\Dispatching\Dispatcher;
use CrCms\Microservice\Server\Contracts\KernelContract;
use CrCms\Microservice\Server\Contracts\RequestContract;
use CrCms\Microservice\Server\Contracts\ResponseContract;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;

/**
 * Class Kernel.
 */
class Kernel implements KernelContract
{
    /**
     * @var ApplicationContract
     */
    protected $app;

    /**
     * @var Dispatcher
     */
    protected $caller;

    /**
     * @var array
     */
    protected $bootstrappers = [
        \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
        \CrCms\Microservice\Bootstrap\LoadConfiguration::class,
        \CrCms\Microservice\Bootstrap\HandleExceptions::class,
        \CrCms\Microservice\Bootstrap\RegisterFacades::class,
        \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
        \Illuminate\Foundation\Bootstrap\BootProviders::class,
    ];

    /**
     * The application request middleware.
     *
     * @var array
     */
    protected $middleware = [
        \CrCms\Microservice\Foundation\Middleware\CheckForMaintenanceModeMiddleware::class,
    ];

    /**
     * @param ApplicationContract $app
     * @param Dispatcher $caller
     */
    public function __construct(ApplicationContract $app, Dispatcher $caller)
    {
        $this->app = $app;
        $this->caller = $caller;
    }

    /**
     * handle.
     *
     * @param RequestContract $request
     * @return ResponseContract
     */
    public function handle(RequestContract $request): ResponseContract
    {
        try {
            $this->app['events']->dispatch('request.handle', [$request]);

            $response = $this->sendRequest($request);
        } catch (Exception $e) {
            $this->reportException($e);
            $response = $this->toResponse($this->renderException($request, $e));
        } catch (Throwable $e) {
            $this->reportException($e = new FatalThrowableError($e));
            $response = $this->toResponse($this->renderException($request, $e));
        }

        $this->app['events']->dispatch('request.handled', [$request, $response]);

        return $response;
    }

    /**
     * sendRequest.
     *
     * @param RequestContract $request
     * @return ResponseContract
     */
    protected function sendRequest(RequestContract $request): ResponseContract
    {
        $this->app->instance('request', $this->bindRequestMatcher($request));

        Facade::clearResolvedInstance('request');

        return (new Pipeline($this->app))
            ->send($request)
            ->through(array_merge($this->middleware, $request->matcher()->getCallerMiddleware()))
            ->then(function (RequestContract $request) {
                return $this->toResponse(
                    $this->app->call($request->matcher()->getCallerUses())
                );
            });
    }

    /**
     * toResponse.
     *
     * @param $response
     * @param int $statusCode
     * @return ResponseContract
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function toResponse($response, int $statusCode = 200): ResponseContract
    {
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            $statusCode = $response->getStatusCode();
        } elseif ($response instanceof Arrayable || $response instanceof Model) {
            $data = $response->toArray();
        } elseif (is_array($response)) {
            $data = $response;
        } elseif ($response instanceof ArrayObject) {
            // @todo 待验证
            $data = (array) $response;
        } else {
            throw new DomainException('The response type error');
        }

        /* @var ResponseContract $response */
        $response = $this->app->make('response');
        $response->setStatusCode($statusCode);

        return $data ? $response->setData($data)->setPackData(
            $this->app->make('bridging.packer')->pack($data)
        ) : $response;
    }

    /**
     * bindRequestMatcher.
     *
     * @param RequestContract $request
     * @return RequestContract
     */
    protected function bindRequestMatcher(RequestContract $request): RequestContract
    {
        $data = $this->app->make('bridging.packer')->unpack($request->rawData());

        return $request->setData($data['data'] ?? [])
            ->setMatcher(
                $this->caller->getCaller($data['call'])
            );
    }

    /**
     * bootstrap.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        if (! $this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers());
        }
    }

    /**
     * @return ApplicationContract
     */
    public function getApplication(): ApplicationContract
    {
        return $this->app;
    }

    /**
     * terminate.
     *
     * @param RequestContract $request
     * @param ResponseContract $response
     * @return mixed|void
     */
    public function terminate(RequestContract $request, ResponseContract $response)
    {
        $this->terminateMiddleware($request, $response);

        $this->app->terminate();
    }

    /**
     * terminateMiddleware.
     *
     * @param RequestContract $request
     * @param ResponseContract $response
     * @return void
     */
    protected function terminateMiddleware(RequestContract $request, ResponseContract $response)
    {
        $middlewares = array_merge(
            $this->middleware,
            $request->matcher()->getCallerMiddleware()
        );

        foreach ($middlewares as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            list($name) = $this->parseMiddleware($middleware);

            $instance = $this->app->make($name);

            if (method_exists($instance, 'terminate')) {
                $instance->terminate($request, $response);
            }
        }
    }

    /**
     * @param $middleware
     *
     * @return array
     */
    protected function parseMiddleware($middleware)
    {
        list($name, $parameters) = array_pad(explode(':', $middleware, 2), 2, []);

        if (is_string($parameters)) {
            $parameters = explode(',', $parameters);
        }

        return [$name, $parameters];
    }

    /**
     * @return array
     */
    protected function bootstrappers()
    {
        return $this->bootstrappers;
    }

    /**
     * @param Exception $e
     */
    protected function reportException(Exception $e)
    {
        $this->app[ExceptionHandlerContract::class]->report($e);
    }

    /**
     * @param RequestContract $request
     * @param Exception $e
     *
     * @return mixed
     */
    protected function renderException(RequestContract $request, Exception $e)
    {
        return $this->app[ExceptionHandlerContract::class]->render($request, $e);
    }
}
