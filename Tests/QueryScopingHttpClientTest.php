<?php

namespace Bytes\HttpClient\Common\Tests;

use Bytes\HttpClient\Common\HttpClient\QueryScopingHttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;


/**
 * Class QueryScopingHttpClientTest
 * Based on the Symfony ScopingHttpClient test
 * @package Bytes\HttpClient\Common\Tests
 */
class QueryScopingHttpClientTest extends TestCase
{

    public function testMatchingUrlsAndOptions()
    {
        $defaultOptions = [
            '.*/foo-bar' => [
                'headers' => [
                    'X-FooBar' => 'unit-test-foo-bar'
                ],
                'query' => [
                    'abc' => 'def',
                    'ghi' => 'jkl',
                    'sdf' => 'sd111111'
                ]
            ],
            '.*' => [
                'headers' => [
                    'Content-Type' => 'text/html'
                ],
                'query' => [
                    'abc' => 'ydp',
                    'ghi' => 'jkl',
                    'sdf' => 'fgoih'
                ]
            ],
        ];

        $mockClient = new MockHttpClient();
        $client = new QueryScopingHttpClient($mockClient, $defaultOptions);

        $response = $client->request('GET', 'http://example.com/foo-bar', ['json' => ['url' => 'http://example.com'], 'query' => [
            'ffff' => 'gg',
            'ghi' => 'ddddddddddddd',
        ]]);
        $requestOptions = $response->getRequestOptions();

        $this->assertSame([
            'abc' => 'def',
            'ghi' => 'ddddddddddddd',
            'sdf' => 'sd111111',
            'ffff' => 'gg',
        ], $requestOptions['query']);

        $this->assertSame('Content-Type: application/json', $requestOptions['headers'][1]);
        $requestJson = json_decode($requestOptions['body'], true);
        $this->assertSame('http://example.com', $requestJson['url']);
        $this->assertSame('X-FooBar: ' . $defaultOptions['.*/foo-bar']['headers']['X-FooBar'], $requestOptions['headers'][0]);


        $response = $client->request('GET', 'http://example.com/bar-foo', ['headers' => ['X-FooBar' => 'unit-test'], 'query' => [
            'ffff' => 'gg',
            'ghi' => 'ddddddddddddd',
        ]]);
        $requestOptions = $response->getRequestOptions();

        $this->assertSame([
            'abc' => 'ydp',
            'ghi' => 'ddddddddddddd',
            'sdf' => 'fgoih',
            'ffff' => 'gg',
        ], $requestOptions['query']);
        $this->assertSame('X-FooBar: unit-test', $requestOptions['headers'][0]);
        $this->assertSame('Content-Type: text/html', $requestOptions['headers'][1]);
    }
}
