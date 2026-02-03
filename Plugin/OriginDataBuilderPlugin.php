<?php

namespace Tapbuy\Adyen\Plugin;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Adyen\Payment\Gateway\Request\OriginDataBuilder as AdyenOriginDataBuilder;
use Tapbuy\RedirectTracking\Logger\TapbuyLogger;

class OriginDataBuilderPlugin
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
     * @var TapbuyLogger
     */
    private $logger;

    /**
     * @param RequestInterface $request
     * @param Json $json
     * @param TapbuyLogger $logger
     */
    public function __construct(
        RequestInterface $request,
        Json $json,
        TapbuyLogger $logger
    ) {
        $this->request = $request;
        $this->json = $json;
        $this->logger = $logger;
    }

    /**
     * Plugin to modify origin data after it is built.
     *
     * @param AdyenOriginDataBuilder $subject
     * @param array $result
     * @param array $buildSubject
     * @return array
     */
    public function afterBuild(
        AdyenOriginDataBuilder $subject,
        array $result,
        array $buildSubject
    ): array {
        // Optional: only act on Tapbuy calls
        $isTapbuyCall = $this->request->getHeader('X-Tapbuy-Call');

        $tapbuyOrigin = $this->extractOriginFromTapbuyRequest();
        if (!empty($isTapbuyCall) && $tapbuyOrigin) {
            $originalOrigin = $result['body']['origin'] ?? null;
            $result['body']['origin'] = $tapbuyOrigin;
            
            $this->logger->info('Adyen origin modified for Tapbuy call', [
                'original_origin' => $originalOrigin,
                'tapbuy_origin' => $tapbuyOrigin,
            ]);
        }

        return $result;
    }

    /**
     * Extracts and normalizes origin from Tapbuy GraphQL request body stateData.
     * Path: variables.paymentMethod.adyen_additional_data_cc.stateData
     */
    private function extractOriginFromTapbuyRequest(): ?string
    {
        // Only use the request's getContent; no php://input fallback
        if (!method_exists($this->request, 'getContent')) {
            return null;
        }

        $raw = (string)($this->request->getContent() ?? '');
        if ($raw === '') {
            return null;
        }

        try {
            $payload = $this->json->unserialize($raw);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to parse Tapbuy request body for origin extraction', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (!is_array($payload)) {
            return null;
        }

        $paymentMethod = $this->getNestedValue($payload, ['variables', 'paymentMethod'], null);

        if ($paymentMethod === null) {
            return null;
        }

        $additionalData = null;

        if (array_key_exists('adyen_additional_data_cc', $paymentMethod)) {
            $additionalData = $paymentMethod['adyen_additional_data_cc'];
        }
        if (array_key_exists('adyen_additional_data_hpp', $paymentMethod)) {
            $additionalData = $paymentMethod['adyen_additional_data_hpp'];
        }

        if (!is_array($additionalData)) {
            return null;
        }

        $stateDataJson = $this->getNestedValue(
            $additionalData,
            ['stateData']
        );

        if (!is_string($stateDataJson) || $stateDataJson === '') {
            return null;
        }

        try {
            $stateData = $this->json->unserialize($stateDataJson);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to parse stateData JSON for origin extraction', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        $origin = is_array($stateData) ? ($stateData['origin'] ?? null) : null;
        if (!is_string($origin) || $origin === '') {
            return null;
        }

        $parts = @parse_url($origin);
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
