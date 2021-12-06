<?php

namespace BusyPHP\swoole\concerns;

use BusyPHP\swoole\App;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Status;
use Swoole\Server;
use Symfony\Component\VarDumper\VarDumper;
use think\Container;
use think\Cookie;
use think\Event;
use think\exception\Handle;
use think\helper\Str;
use think\Http;
use think\Middleware;
use BusyPHP\swoole\middleware\ResetVarDumper;
use BusyPHP\swoole\response\File as FileResponse;
use Throwable;

/**
 * Trait InteractsWithHttp
 * @package BusyPHP\swoole\concerns
 * @property App       $app
 * @property Container $container
 * @method Server getServer()
 */
trait InteractsWithHttp
{
    /**
     * "onRequest" listener.
     *
     * @param Request  $req
     * @param Response $res
     */
    public function onRequest($req, $res)
    {
        $this->waitCoordinator('workerStart');
        
        $args = func_get_args();
        $this->runInSandbox(function(Http $http, Event $event, App $app, Middleware $middleware) use ($args, $req, $res) {
            $app->setInConsole(false);
            $event->trigger('swoole.request', $args);
            
            //兼容var-dumper
            if (class_exists(VarDumper::class)) {
                $middleware->add(ResetVarDumper::class);
            }
            
            $request = $this->prepareRequest($req);
            try {
                $response = $this->handleRequest($http, $request);
            } catch (Throwable $e) {
                $response = $this->app->make(Handle::class)->render($request, $e);
            }
            
            $this->setCookie($res, $app->cookie);
            $this->sendResponse($res, $request, $response);
        });
    }
    
    
    protected function setCookie(Response $res, Cookie $cookie)
    {
        foreach ($cookie->getCookie() as $name => $val) {
            [$value, $expire, $option] = $val;
            
            $res->cookie($name, $value, $expire, $option['path'], $option['domain'], (bool) $option['secure'], (bool) $option['httponly'], $option['samesite']);
        }
    }
    
    
    protected function setHeader(Response $res, array $headers)
    {
        foreach ($headers as $key => $val) {
            $res->header($key, $val);
        }
    }
    
    
    protected function setStatus(Response $res, $code)
    {
        $res->status($code, Status::getReasonPhrase($code));
    }
    
    
    protected function handleRequest(Http $http, $request)
    {
        $level = ob_get_level();
        ob_start();
        
        $response = $http->run($request);
        
        $content = $response->getContent();
        
        if (ob_get_level() == 0) {
            ob_start();
        }
        
        $http->end($response);
        
        if (ob_get_length() > 0) {
            $response->content(ob_get_contents() . $content);
        }
        
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        
        return $response;
    }
    
    
    protected function prepareRequest(Request $req)
    {
        $header = $req->header ?: [];
        $server = $req->server ?: [];
        
        foreach ($header as $key => $value) {
            $server["http_" . str_replace('-', '_', $key)] = $value;
        }
        
        // 重新实例化请求对象 处理swoole请求数据
        /** @var \BusyPHP\Request $request */
        $request = $this->app->make('request', [], true);
        
        return $request->withHeader($header)
            ->withServer($server)
            ->withGet($req->get ?: [])
            ->withPost($req->post ?: [])
            ->withCookie($req->cookie ?: [])
            ->withFiles($req->files ?: [])
            ->withInput($req->rawContent())
            ->setBaseUrl($req->server['request_uri'])
            ->setUrl($req->server['request_uri'] . (!empty($req->server['query_string']) ? '?' . $req->server['query_string'] : ''))
            ->setPathinfo(ltrim($req->server['path_info'], '/'));
    }
    
    
    protected function sendFile(Response $res, \think\Request $request, FileResponse $response)
    {
        $code     = $response->getCode();
        $ifRange  = $request->header('If-Range');
        $file     = $response->getFile();
        $fileSize = $file->getSize();
        
        $offset = 0;
        $maxlen = -1;
        
        if (!$ifRange || $ifRange === $response->getHeader('ETag') || $ifRange === $response->getHeader('Last-Modified')) {
            $range = $request->header('Range', '');
            if (Str::startsWith($range, 'bytes=')) {
                [$start, $end] = explode('-', substr($range, 6), 2) + [0];
                
                $end = ('' === $end) ? $fileSize - 1 : (int) $end;
                
                if ('' === $start) {
                    $start = $fileSize - $end;
                    $end   = $fileSize - 1;
                } else {
                    $start = (int) $start;
                }
                
                if ($start <= $end) {
                    $end = min($end, $fileSize - 1);
                    if ($start < 0 || $start > $end) {
                        $code = 416;
                        $response->header([
                            'Content-Range' => sprintf('bytes */%s', $fileSize),
                        ]);
                    } elseif ($end - $start < $fileSize - 1) {
                        $maxlen = $end < $fileSize ? $end - $start + 1 : -1;
                        $offset = $start;
                        $code   = 206;
                        $response->header([
                            'Content-Range'  => sprintf('bytes %s-%s/%s', $start, $end, $fileSize),
                            'Content-Length' => $end - $start + 1,
                        ]);
                    }
                }
            }
        }
        
        $this->setStatus($res, $code);
        $this->setHeader($res, $response->getHeader());
        
        if ($code >= 200 && $code < 300 && $maxlen !== 0) {
            $res->sendfile($file->getPathname(), $offset, $maxlen);
        } else {
            $res->end();
        }
    }
    
    
    protected function sendContent(Response $res, \think\Response $response)
    {
        // 由于开启了 Transfer-Encoding: chunked，根据 HTTP 规范，不再需要设置 Content-Length
        $response->header(['Content-Length' => null]);
        
        $this->setStatus($res, $response->getCode());
        $this->setHeader($res, $response->getHeader());
        
        $content = $response->getContent();
        if ($content) {
            $contentSize = strlen($content);
            $chunkSize   = 8192;
            
            if ($contentSize > $chunkSize) {
                $sendSize = 0;
                do {
                    if (!$res->write(substr($content, $sendSize, $chunkSize))) {
                        break;
                    }
                } while (($sendSize += $chunkSize) < $contentSize);
            } else {
                $res->write($content);
            }
        }
        $res->end();
    }
    
    
    protected function sendResponse(Response $res, \BusyPHP\Request $request, \think\Response $response)
    {
        if ($response instanceof FileResponse) {
            $this->sendFile($res, $request, $response);
        } else {
            $this->sendContent($res, $response);
        }
    }
}
