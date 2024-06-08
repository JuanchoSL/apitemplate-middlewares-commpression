<?php

namespace App\Context\Infrastructure\Middlewares;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

class OutputCompressionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $compress = false;
        $encoding = false;
        if ($request->hasHeader('Accept-Encoding')) {
            if (stristr($request->getHeaderLine('Accept-Encoding'), 'deflate') !== false) {
                $compress = ZLIB_ENCODING_DEFLATE;
                $encoding = 'deflate';
            } elseif (stristr($request->getHeaderLine('Accept-Encoding'), 'gzip') !== false) {
                $compress = ZLIB_ENCODING_GZIP;
                $encoding = 'gzip';
            }
        }
        $response = $handler->handle($request);

        if (!$compress || $response->hasHeader('Content-Encoding')) {
            // Browser doesn't accept compression
            return $response;
        }
        // Compress response data
        $deflateContext = deflate_init($compress);
        $compressed = deflate_add($deflateContext, (string) $response->getBody(), \ZLIB_FINISH);

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $compressed);
        rewind($stream);

        return $response
            ->withHeader('Content-Encoding', $encoding)
            ->withHeader('Content-Length', (string) strlen($compressed))
            ->withBody(new \Slim\Psr7\Stream($stream));
    }
}