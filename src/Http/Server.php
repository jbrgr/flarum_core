<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Flarum\Http;

use Flarum\Foundation\SiteInterface;
use Throwable;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\ServerRequestFactory;
use Zend\HttpHandlerRunner\Emitter\SapiEmitter;
use Zend\HttpHandlerRunner\RequestHandlerRunner;
use Zend\Stratigility\Middleware\ErrorResponseGenerator;

class Server
{
    private $site;

    public function __construct(SiteInterface $site)
    {
        $this->site = $site;
    }

    public function listen()
    {
        $runner = new RequestHandlerRunner(
            $this->safelyBootAndGetHandler(),
            new SapiEmitter,
            [ServerRequestFactory::class, 'fromGlobals'],
            function (Throwable $e) {
                $generator = new ErrorResponseGenerator;

                return $generator($e, new ServerRequest, new Response);
            }
        );
        $runner->run();
    }

    /**
     * Try to boot Flarum, and retrieve the app's HTTP request handler.
     *
     * We catch all exceptions happening during this process and format them to
     * prevent exposure of sensitive information.
     *
     * @return \Psr\Http\Server\RequestHandlerInterface
     */
    private function safelyBootAndGetHandler()
    {
        try {
            return $this->site->bootApp()->getRequestHandler();
        } catch (Throwable $e) {
            exit($this->formatBootException($e));
        }
    }

    /**
     * Display the most relevant information about an early exception.
     */
    private function formatBootException(Throwable $error): string
    {
        $message = $error->getMessage();
        $file = $error->getFile();
        $line = $error->getLine();
        $type = get_class($error);

        return <<<ERROR
            Flarum encountered a boot error ($type)<br />
            <b>$message</b><br />
            thrown in <b>$file</b> on line <b>$line</b>
ERROR;
    }
}
