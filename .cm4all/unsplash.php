<?php

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR."include/config.php");


function userErrorHandler($errno , $errstr) {
    error_log($errstr);
    header("Content-Type: text/plain", true, 501);
    header("Content-Length: 0");
    exit(0);
}

// php warning message destroys otherwise perfectly sane image file
error_reporting(E_ERROR);
set_error_handler('userErrorHandler', E_USER_ERROR);


class UnsplashHandler {

    var $baseUrl = 'https://images.unsplash.com';
    var $allowedResponseHeaders = array('cache-control', 'content-length', 'content-type', 'last-modified');
    var $responseHeaders;
    var $statusCode = '500';
    var $body;

    function __construct() {
    }

    function serve() {
        $this->sendUnsplashRequest();
        $this->sendResponse();
    }

    function sendUnsplashRequest() {
        $url = $this->baseUrl . $_SERVER['PATH_INFO'] . '?' . $_SERVER['QUERY_STRING'];
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $url);
        curl_setopt($curlHandle, CURLOPT_HEADER, true);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curlHandle);
        curl_close($curlHandle);
        $this->parseUnsplashResponse($response);
    }

    function parseUnsplashResponse($response) {
        // Split response into header and body sections
        list($headers, $body) = explode("\r\n\r\n", $response, 2);
        
        $header_lines = explode("\r\n", $headers);

        // first line of headers is the HTTP response code
        $http_response_line = array_shift($header_lines);
        if (preg_match('@^HTTP/[0-9.]+ ([0-9]{3})@', $http_response_line, $matches)) {
            $this->statusCode = $matches[1];
        }

        // put the rest of the headers in an array
        $this->responseHeaders = array();
        foreach($header_lines as $header_line) {
            list($header, $value) = explode(': ', $header_line, 2);
            $name = strtolower($header);
            if (in_array($name, $this->allowedResponseHeaders)) {
                $this->responseHeaders[$name] = $value;
            }
        }

        $this->body = $body;
    }

    function sendResponse() {
        http_response_code($this->statusCode);

        foreach($this->responseHeaders as $name => $value) {
            header("$name: $value");
        }

        echo $this->body;
    }
}


$unsplash = new UnsplashHandler();
$unsplash->serve();
