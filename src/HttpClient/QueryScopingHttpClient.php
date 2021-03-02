<?php


namespace Bytes\HttpClient\Common\HttpClient;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\HttpClientTrait;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;
use function array_key_exists;
use function is_string;

/**
 * Class QueryScopingHttpClient
 * Overloaded copy of ScopingHttpClient that merges queries, sets up a ScopingHttpClient
 *
 * @package Bytes\HttpClient\Common\HttpClient
 */
class QueryScopingHttpClient implements HttpClientInterface, ResetInterface, LoggerAwareInterface
{
    use HttpClientTrait;

    /**
     * @var HttpClientInterface
     */
    private $client;

    /**
     * @var array
     */
    private $defaultOptionsByRegexp;

    /**
     * @var string|null
     */
    private $defaultRegexp;

    /**
     * QueryScopingHttpClient constructor.
     * @param HttpClientInterface $client
     * @param array $defaultOptionsByRegexp
     * @param string|null $defaultRegexp
     */
    public function __construct(HttpClientInterface $client, array $defaultOptionsByRegexp, string $defaultRegexp = null)
    {
        $this->client = $client;
        $this->defaultOptionsByRegexp = $defaultOptionsByRegexp;
        $this->defaultRegexp = $defaultRegexp;

        if (null !== $defaultRegexp && !isset($defaultOptionsByRegexp[$defaultRegexp])) {
            throw new InvalidArgumentException(sprintf('No options are mapped to the provided "%s" default regexp.', $defaultRegexp));
        }

        $this->client = new ScopingHttpClient($client, $defaultOptionsByRegexp, $defaultRegexp);
    }

    /**
     * @param HttpClientInterface $client
     * @param string $baseUri
     * @param array $defaultOptions
     * @param null $regexp
     * @return $this
     */
    public static function forBaseUri(HttpClientInterface $client, string $baseUri, array $defaultOptions = [], $regexp = null): self
    {
        if (null === $regexp) {
            $regexp = preg_quote(implode('', self::resolveUrl(self::parseUrl('.'), self::parseUrl($baseUri))));
        }

        $defaultOptions['base_uri'] = $baseUri;

        return new self($client, [$regexp => $defaultOptions], $regexp);
    }

    /**
     * {@inheritdoc}
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $url = self::parseUrl($url, $options['query'] ?? []);

        if (is_string($options['base_uri'] ?? null)) {
            $options['base_uri'] = self::parseUrl($options['base_uri']);
        }

        try {
            $url = implode('', self::resolveUrl($url, $options['base_uri'] ?? null));
        } catch (InvalidArgumentException $e) {
            if (null === $this->defaultRegexp) {
                throw $e;
            }

            [$url, $options] = self::prepareRequest($method, implode('', $url), $options, $this->defaultOptionsByRegexp[$this->defaultRegexp], true);
            $url = implode('', $url);
        }

        foreach ($this->defaultOptionsByRegexp as $regexp => $defaultOptions) {
            if (preg_match("{{$regexp}}A", $url)) {
                $options = self::mergeDefaultOptions($options, $defaultOptions, true);
                break;
            }
        }

        return $this->client->request($method, $url, $options);
    }

    /**
     * Merges the current and default query strings
     * @param array $options
     * @param array $defaultOptions
     * @param bool $allowExtraOptions
     *
     * @return array
     *
     * @throws InvalidArgumentException When an invalid option is found
     */
    private static function mergeDefaultOptions(array $options, array $defaultOptions, bool $allowExtraOptions = false): array
    {
        // Option "query" is never inherited from defaults
        //$options['query'] = $options['query'] ?? [];

        if (array_key_exists('query', $defaultOptions)) {
            if (array_key_exists('query', $options)) {
                $options['query'] = array_merge($defaultOptions['query'], $options['query']);
            } else {
                $options['query'] = $defaultOptions['query'];
            }
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    /**
     *
     */
    public function reset()
    {
        if ($this->client instanceof ResetInterface) {
            $this->client->reset();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setLogger(LoggerInterface $logger): void
    {
        if ($this->client instanceof LoggerAwareInterface) {
            $this->client->setLogger($logger);
        }
    }
}