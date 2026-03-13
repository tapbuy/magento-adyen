<?php

declare(strict_types=1);

namespace Tapbuy\Adyen\Model;

use Laminas\Uri\Uri;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Tapbuy\RedirectTracking\Api\LoggerInterface;

class AdyenOriginExtractor
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param RequestInterface $request
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        RequestInterface $request,
        Json $json,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->json = $json;
        $this->logger = $logger;
    }

    /**
     * Extracts and normalizes origin from Tapbuy GraphQL request body stateData.
     *
     * Path: variables.paymentMethod.(adyen_additional_data_cc|adyen_additional_data_hpp).stateData
     *
     * @return string|null
     */
    public function extractOriginFromTapbuyRequest(): ?string
    {
        $raw = $this->getRawRequestBody();
        if ($raw === null || $raw === '') {
            return null;
        }

        $payload = $this->parseRequestBody($raw);
        if ($payload === null) {
            return null;
        }

        $paymentMethod = $this->extractPaymentMethod($payload);
        if ($paymentMethod === null) {
            return null;
        }

        $additionalData = $this->extractAdditionalData($paymentMethod);
        if ($additionalData === null) {
            return null;
        }

        $stateDataJson = $this->getNestedValue($additionalData, ['stateData']);
        if (!is_string($stateDataJson) || $stateDataJson === '') {
            return null;
        }

        $stateData = $this->parseStateData($stateDataJson);
        if ($stateData === null) {
            return null;
        }

        $origin = $stateData['origin'] ?? null;
        if (!is_string($origin) || $origin === '') {
            return null;
        }

        return $this->normalizeOrigin($origin);
    }

    /**
     * Returns the raw request body content, or null if the request does not support it.
     *
     * @return string|null
     */
    private function getRawRequestBody(): ?string
    {
        if (!method_exists($this->request, 'getContent')) {
            return null;
        }

        return (string)($this->request->getContent() ?? '');
    }

    /**
     * Deserializes the raw request body JSON into an array.
     *
     * @param string $raw
     * @return array|null
     */
    private function parseRequestBody(string $raw): ?array
    {
        return $this->unserializeToArray($raw, 'Failed to parse Tapbuy request body for origin extraction');
    }

    /**
     * Extracts the paymentMethod node from the GraphQL request payload.
     *
     * @param array $payload
     * @return array|null
     */
    private function extractPaymentMethod(array $payload): ?array
    {
        $paymentMethod = $this->getNestedValue($payload, ['variables', 'paymentMethod']);

        return is_array($paymentMethod) ? $paymentMethod : null;
    }

    /**
     * Extracts the Adyen additional data block (CC or HPP) from the paymentMethod node.
     *
     * @param array $paymentMethod
     * @return array|null
     */
    private function extractAdditionalData(array $paymentMethod): ?array
    {
        $additionalData = $this->findValueByKeys(
            $paymentMethod,
            ['adyen_additional_data_cc', 'adyen_additional_data_hpp']
        );

        return is_array($additionalData) ? $additionalData : null;
    }

    /**
     * Deserializes the stateData JSON string nested inside the Adyen additional data.
     *
     * @param string $stateDataJson
     * @return array|null
     */
    private function parseStateData(string $stateDataJson): ?array
    {
        return $this->unserializeToArray($stateDataJson, 'Failed to parse stateData JSON for origin extraction');
    }

    /**
     * Unserialize a JSON string to an array, logging a warning on failure.
     *
     * @param string $json
     * @param string $logContext Warning message logged when deserialization fails
     * @return array|null
     */
    private function unserializeToArray(string $json, string $logContext): ?array
    {
        try {
            $result = $this->json->unserialize($json);
        } catch (\Throwable $e) {
            $this->logger->warning($logContext, [
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        return is_array($result) ? $result : null;
    }

    /**
     * Validates and normalizes an origin URL to scheme://host[:port] format.
     *
     * @param string $origin
     * @return string|null
     */
    private function normalizeOrigin(string $origin): ?string
    {
        try {
            $uri = new Uri($origin);
        } catch (\Throwable $e) {
            $this->logger->warning('Malformed origin URL in Adyen stateData', [
                'origin' => $origin,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (!$uri->isValid() || !$uri->getScheme() || !$uri->getHost()) {
            $this->logger->warning('Invalid origin URL format in Adyen stateData', [
                'origin' => $origin,
            ]);
            return null;
        }

        $port = $uri->getPort() ? ':' . $uri->getPort() : '';

        return sprintf('%s://%s%s', $uri->getScheme(), $uri->getHost(), $port);
    }

    /**
     * Returns the value for the last existing key found among $keys in $data.
     * Iterates in order so later keys take precedence (last-wins).
     *
     * @param array $data
     * @param string[] $keys
     * @return mixed|null
     */
    private function findValueByKeys(array $data, array $keys)
    {
        $value = null;
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
            }
        }
        return $value;
    }

    /**
     * Safely fetch a nested value.
     *
     * @param array $data
     * @param string[] $path
     * @return mixed|null
     */
    private function getNestedValue(array $data, array $path)
    {
        $current = $data;
        foreach ($path as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }
        return $current;
    }
}
