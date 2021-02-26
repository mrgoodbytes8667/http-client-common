<?php


namespace Bytes\HttpClient\Common\Retry;


use Exception;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\Response\AsyncContext;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\Retry\RetryStrategyInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Class APIRetryStrategy
 * Similar to the GenericRetryStrategy with some logic from other http client frameworks
 * @package Bytes\HttpClient\Common\Retry
 */
abstract class APIRetryStrategy implements RetryStrategyInterface
{
    /**
     * @var array List of HTTP status codes that trigger a retry
     */
    private $statusCodes;

    /**
     * @var int Amount of time to delay (or the initial value when multiplier is used)
     */
    private $delayMs;

    /**
     * @var float Multiplier to apply to the delay each time a retry occurs
     */
    private $multiplier;

    /**
     * @var int Maximum delay to allow (0 means no maximum)
     */
    private $maxDelayMs;

    /**
     * @var float Probability of randomness int delay (0 = none, 1 = 100% random)
     */
    private $jitter;

    /**
     * @var int
     */
    private int $maxRetries;

    /**
     * @param array $statusCodes List of HTTP status codes that trigger a retry
     * @param int $delayMs Amount of time to delay (or the initial value when multiplier is used)
     * @param float $multiplier Multiplier to apply to the delay each time a retry occurs
     * @param int $maxDelayMs Maximum delay to allow (0 means no maximum)
     * @param float $jitter Probability of randomness int delay (0 = none, 1 = 100% random)
     * @param int $maxRetries Number of times to retry before failing
     */
    public function __construct(array $statusCodes = GenericRetryStrategy::DEFAULT_RETRY_STATUS_CODES, int $delayMs = 1000, float $multiplier = 2.0, int $maxDelayMs = 0, float $jitter = 0.1, int $maxRetries = 3)
    {
        $this->statusCodes = $statusCodes;

        if ($maxRetries < 0) {
            throw new InvalidArgumentException(sprintf('Max retries must be greater than or equal to zero: "%s" given.', $maxRetries));
        }
        $this->maxRetries = $maxRetries;

        if ($delayMs < 0) {
            throw new InvalidArgumentException(sprintf('Delay must be greater than or equal to zero: "%s" given.', $delayMs));
        }
        $this->delayMs = $delayMs;

        if ($multiplier < 1) {
            throw new InvalidArgumentException(sprintf('Multiplier must be greater than or equal to one: "%s" given.', $multiplier));
        }
        $this->multiplier = $multiplier;

        if ($maxDelayMs < 0) {
            throw new InvalidArgumentException(sprintf('Max delay must be greater than or equal to zero: "%s" given.', $maxDelayMs));
        }
        $this->maxDelayMs = $maxDelayMs;

        if ($jitter < 0 || $jitter > 1) {
            throw new InvalidArgumentException(sprintf('Jitter must be between 0 and 1: "%s" given.', $jitter));
        }
        $this->jitter = $jitter;
    }

    /**
     * @param AsyncContext $context
     * @param string|null $responseContent
     * @param TransportExceptionInterface|null $exception
     * @return bool|null
     */
    public function shouldRetry(AsyncContext $context, ?string $responseContent, ?TransportExceptionInterface $exception): ?bool
    {
        $statusCode = $context->getStatusCode();
        if (in_array($statusCode, $this->statusCodes, true)) {
            return true;
        }
        if (isset($this->statusCodes[$statusCode]) && is_array($this->statusCodes[$statusCode])) {
            return in_array($context->getInfo('http_method'), $this->statusCodes[$statusCode], true);
        }
        if (null === $exception) {
            return false;
        }

        if (in_array(0, $this->statusCodes, true)) {
            return true;
        }
        if (isset($this->statusCodes[0]) && is_array($this->statusCodes[0])) {
            return in_array($context->getInfo('http_method'), $this->statusCodes[0], true);
        }

        if (($context->getInfo('retry_count') ?? 1) > $this->maxRetries) {
            return false;
        }

        return false;
    }

    /**
     * @param AsyncContext $context
     * @param string|null $responseContent
     * @param TransportExceptionInterface|null $exception
     * @return int Amount of time to delay in milliseconds
     * @throws Exception
     */
    public function getDelay(AsyncContext $context, ?string $responseContent, ?TransportExceptionInterface $exception): int
    {
        $delay = $this->delayMs;
        switch ($context->getStatusCode()) {
            case Response::HTTP_TOO_MANY_REQUESTS:
                $delay = $this->getRateLimitDelay($context, $exception);
                break;
            default:
                $delay = $this->calculateDelay($context, $exception);
                break;
        }
        return $this->standardizeDelay((int)$delay);
    }

    /**
     * @param AsyncContext $context
     * @param TransportExceptionInterface|null $exception
     * @return int Amount of time to delay in milliseconds
     * @throws Exception
     */
    abstract protected function getRateLimitDelay(AsyncContext $context, ?TransportExceptionInterface $exception): int;

    /**
     * @param AsyncContext $context
     * @param TransportExceptionInterface|null $exception
     * @return int Amount of time to delay in milliseconds
     * @throws Exception
     */
    protected function calculateDelay(AsyncContext $context, ?TransportExceptionInterface $exception): int
    {
        $delay = $this->delayMs * $this->multiplier ** ($context->getInfo('retry_count') ?? 1);

        return (int)$delay;
    }

    /**
     * @param int $delay
     * @return int Amount of time to delay in milliseconds
     * @throws Exception
     */
    protected function standardizeDelay(int $delay): int
    {
        $delay = $this->applyJitter($delay);
        if ($delay > $this->maxDelayMs && 0 !== $this->maxDelayMs) {
            return $this->maxDelayMs;
        }

        return (int)$delay;
    }

    /**
     * @param int $delay
     * @return int
     * @throws Exception
     */
    protected function applyJitter(int $delay): int
    {
        if ($this->jitter > 0) {
            $randomness = $delay * $this->jitter;
            // We don't want to fall below 1s
            if ($delay > 1000) {
                $delay = $delay + random_int(-$randomness, +$randomness);
            } else {
                $delay = $delay + $randomness;
            }
        }

        return (int)$delay;
    }
}