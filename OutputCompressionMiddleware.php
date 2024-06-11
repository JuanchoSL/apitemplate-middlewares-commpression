<?php

namespace App\Context\Infrastructure\Middlewares\Compression;

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
            $accepts = $request->getHeader('Accept-Encoding');
            foreach ($accepts as $accept) {
                $accept = trim($accept);
                switch ($accept) {
                    case 'deflate':
                        $encoding = 'deflate';
                        $compress = ZLIB_ENCODING_DEFLATE;
                        break;

                    case 'gzip':
                        $encoding = 'gzip';
                        $compress = ZLIB_ENCODING_GZIP;
                        break;
                }
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