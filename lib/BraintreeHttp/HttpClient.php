<?php

namespace BraintreeHttp;

use BraintreeHttp;

/**
 * Class HttpClient
 * @package BraintreeHttp
 */
class HttpClient
{
    /**
     * @var Environment
     */
    public $environment;

    /**
     * @var Injector[]
     */
    public $injectors = [];

    /**
     * HttpClient constructor.
     * @param $environment Environment
     */
    function __construct(Environment $environment)
    {
        $this->environment = $environment;
    }

    public function userAgent()
    {
        return "BraintreeHttp-PHP HTTP/1.1";
    }

    public function addInjector(Injector $inj)
    {
        $this->injectors[] = $inj;
    }

    /**
     * @param $httpRequest HttpRequest
     * @return HttpResponse
     */
    public function execute($httpRequest)
    {
        foreach ($this->injectors as $inj)
        {
            $inj->inject($httpRequest);
        }

        if ($httpRequest->headers["User-Agent"] === NULL)
        {
            $httpRequest->headers["User-Agent"] = $this->userAgent();
        }

        # Init curl
        $curl = curl_init();

        # Set the headers
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->serializeHeaders($httpRequest->headers));

        # Set verb
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $httpRequest->verb);

        # Set the URL
        $url = $this->environment->baseUrl() . $httpRequest->path;
        curl_setopt($curl, CURLOPT_URL, $url);

        # Set the body
        curl_setopt($curl, CURLOPT_POSTFIELDS, $httpRequest->body);

        # Get a response back instead of printing to page
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, 1);

        # Perform the curl, get a response back
        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $errorCode = curl_errno($curl);
        $error = curl_error($curl);

        curl_close($curl);

        if ($errorCode > 0)
        {
            throw new IOException($error, $errorCode);
        }

        list($headers, $body) = explode("\r\n\r\n", $response, 2);

        $response = new BraintreeHttp\HttpResponse(
            $errorCode === 0 ? $statusCode : $errorCode,
            $body,
            $this->deserializeHeaders($headers)
        );

        if ($response->statusCode >= 200 && $response->statusCode < 300)
        {
            return $response;
        }
        else
        {
            throw new HttpException($response);
        }
    }

    /**
     * @param $headers string
     * @return array
     */
    private function deserializeHeaders($headers)
    {
        $split = explode("\n", $headers);
        $separatedHeaders = [];
        foreach ($split as $header) {
            list($key, $val) = explode(":", $header);
            $separatedHeaders[$key] = trim($val);
        }

        return $separatedHeaders;
    }

    /**
     * @param $headers array[]
     * @return array
     */
    private function serializeHeaders($headers)
    {
        $h = [];
        if ($headers) {
            foreach ($headers as $key => $val) {
               $h[] = $key . ": " . $val;
            }
        }

        return $h;
    }

    public function serializeRequest(/* Request */)
    {

    }

    public function deserializeRequest(/* Request */)
    {

    }
}