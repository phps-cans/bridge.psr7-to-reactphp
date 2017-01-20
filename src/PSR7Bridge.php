<?php

namespace PSCS\Bridge;

use React\Http\Response as ReactResponse;
use React\Http\Request as ReactRequest;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequestFactory;

class PSR7Bridge
{
    /**
     * @param ReactRequest $reactRequest
     *
     * @return mixed
     */
    public function mapRequest(ReactRequest $reactRequest)
    {
        $method = $reactRequest->getMethod();
        $headers = $reactRequest->getHeaders();
        $query = $reactRequest->getQuery();
        $_COOKIE = [];
        $sessionCookieSet = false;
        if (isset($headers['Cookie']) || isset($headers['cookie'])) {
            $headersCookie = explode(';', isset($headers['Cookie']) ? $headers['Cookie'] : $headers['cookie']);
            foreach ($headersCookie as $cookie) {
                list($name, $value) = explode('=', trim($cookie));
                $_COOKIE[$name] = $value;
                if ($name === session_name()) {
                    session_id($value);
                    $sessionCookieSet = true;
                }
            }
        }
        if (!$sessionCookieSet && session_id()) {
            //session id already set from the last round but not got from the cookie header,
            //so generate a new one, since php is not doing it automatically with session_start() if session
            //has already been started.
            session_id($this->generateSessionId());
        }
        $files = $reactRequest->getFiles();
        $post = $reactRequest->getPost();

        $syRequest = ServerRequestFactory::fromGlobals($_SERVER, $query, $post, $_COOKIE, $files);

        return $syRequest;
    }

    /**
     * @param ReactResponse $reactResponse
     * @param Response      $syResponse
     *
     * @return ReactResponse
     */
    public function mapResponse(ReactResponse $reactResponse, Response $syResponse)
    {
        // end active session
        if (PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
            session_unset(); // reset $_SESSION
        }
        $nativeHeaders = [];
        foreach (headers_list() as $header) {
            if (false !== $pos = strpos($header, ':')) {
                $name = substr($header, 0, $pos);
                $value = trim(substr($header, $pos + 1));
                if (isset($nativeHeaders[$name])) {
                    if (!is_array($nativeHeaders[$name])) {
                        $nativeHeaders[$name] = [$nativeHeaders[$name]];
                    }
                    $nativeHeaders[$name][] = $value;
                } else {
                    $nativeHeaders[$name] = $value;
                }
            }
        }
        // after reading all headers we need to reset it, so next request
        // operates on a clean header.
        header_remove();
        $headers = array_merge($nativeHeaders, $syResponse->getHeaders());
        ob_start();
        $content = $syResponse->getBody();
        @ob_end_flush();
        if (!isset($headers['Content-Length'])) {
            $headers['Content-Length'] = strlen($content);
        }
        $reactResponse->writeHead($syResponse->getStatusCode(), $headers);
        $reactResponse->end($content);

        return $reactResponse;
    }

    /**
     * @return string
     */
    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }
}
