<?php

namespace Tapbuy\Adyen\Model;

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
     * Path: variables.paymentMethod.adyen_additional_data_cc.stateData
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
     * @param string $raw
     * @return array|null
     */
    private function parseRequestBody(string $raw): ?array
    {
        try {
            $payload = $this->json->unserialize($raw);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to parse Tapbuy request body for origin extraction', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param array $payload
     * @return array|null
     */
    private function extractPaymentMethod(array $payload): ?array
    {
        $paymentMethod = $this->getNestedValue($payload, ['variables', 'paymentMethod']);

        return is_array($paymentMethod) ? $paymentMethod : null;
    }

    /**
     * @param array $paymentMethod
     * @return array|null
     */
    private function extractAdditionalData(array $paymentMethod): ?array
    {
        $additionalData = null;

        if (array_key_exists('adyen_additional_data_cc', $paymentMethod)) {
            $additionalData = $paymentMethod['adyen_additional_data_cc'];
        }
        if (array_key_exists('adyen_additional_data_hpp', $paymentMethod)) {
            $additionalData = $paymentMethod['adyen_additional_data_hpp'];
        }

        return is_array($additionalData) ? $additionalData : null;
    }

    /**
     * @param string $stateDataJson
     * @return array|null
     */
    private function parseStateData(string $stateDataJson): ?array
    {
        try {
            $stateData = $this->json->unserialize($stateDataJson);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to parse stateData JSON for origin extraction', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        return is_array($stateData) ? $stateData : null;
    }

    /**
     * @param string $origin
     * @return string|null
     */
    private function normalizeOrigin(string $origin): ?string
    {
        try {
            $parts = parse_url($origin);
        } catch (\ValueError $e) {
            $this->logger->warning('Malformed origin URL in Adyen stateData', [
                'origin' => $origin,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            $this->logger->warning('Invalid origin URL format in Adyen stateData', [
                'origin' => $origin,
            ]);
            return null;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return sprintf('%s://%s%s', $parts['scheme'], $parts['host'], $port);
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
