<?php

namespace Bytes\HttpClient\Common\Tests;

use Bytes\HttpClient\Common\HttpClient\ConfigurableScopingHttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;


/**
 * Class ConfigurableScopingHttpClientTest
 * Based on the Symfony ScopingHttpClient test
 * @package Bytes\HttpClient\Common\Tests
 */
class ConfigurableScopingHttpClientTest extends TestCase
{
    /**
     * @return \Generator
     */
    public function provideDefaultOptions()
    {
        yield [[
            '.*/foo-bar' => [
                'headers' => [
                    'X-FooBar' => 'unit-test-foo-bar'
                ],
                'query' => [
                    'abc' => 'def',
                    'ghi' => 'jkl',
                    'sdf' => 'sd111111'
                ],
                'json' => [
                    'qqq' => 'rrr'
                ]
            ],
            '.*/body' => [
                'headers' => [
                    'X-FooBar' => 'unit-test-foo-bar'
                ],
                'query' => [
                    'abc' => 'def',
                    'ghi' => 'jkl',
                    'sdf' => 'sd111111'
                ],
                'body' => [
                    'qqq' => 'rrr'
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
                ],
                'body' => [
                    'qqq' => 'rrr'
                ]
            ],
        ]];
    }

    /**
     * @dataProvider provideDefaultOptions
     */
    public function testMatchingUrlsAndOptionsQueryOnly(array $defaultOptions)
    {
        $mockClient = new MockHttpClient();
        $client = new ConfigurableScopingHttpClient($mockClient, $defaultOptions, ['query']);

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
        $this->assertSame('Content-Type: application/x-www-form-urlencoded', $requestOptions['headers'][1]);



        $response = $client->request('GET', 'http://example.com/body', ['body' => ['url' => 'http://example.com'], 'query' => [
            'ffff' => 'gg',
            'ghi' => 'ddddddddddddd',
        ]]);
        $requestOptions = $response->getRequestOptions();

        $this->assertSame('url=' . urlencode('http://example.com'), $requestOptions['body']);
    }

    /**
     * @dataProvider provideDefaultOptions
     */
    public function testMatchingUrlsAndOptionsBodyOnly(array $defaultOptions)
    {
        $mockClient = new MockHttpClient();
        $client = new ConfigurableScopingHttpClient($mockClient, $defaultOptions, ['body']);

        $response = $client->request('GET', 'http://example.com/foo-bar', ['json' => ['url' => 'http://example.com'], 'query' => [
            'ffff' => 'gg',
            'ghi' => 'ddddddddddddd',
        ]]);
        $requestOptions = $response->getRequestOptions();

        $this->assertSame([
            'ffff' => 'gg',
            'ghi' => 'ddddddddddddd',
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
            'ffff' => 'gg',
            'ghi' => 'ddddddddddddd',
        ], $requestOptions['query']);
        $this->assertSame('X-FooBar: unit-test', $requestOptions['headers'][0]);
        $this->assertSame('Content-Type: application/x-www-form-urlencoded', $requestOptions['headers'][1]);

        $response = $client->request('GET', 'http://example.com/body', ['body' => ['url' => 'http://example.com'], 'query' => [
            'ffff' => 'gg',
            'ghi' => 'ddddddddddddd',
        ]]);
        $requestOptions = $response->getRequestOptions();

        $this->assertSame([
            'ffff' => 'gg',
            'ghi' => 'ddddddddddddd',
        ], $requestOptions['query']);
        $this->assertSame('qqq=rrr&url=' . urlencode('http://example.com'), $requestOptions['body']);
    }
}
