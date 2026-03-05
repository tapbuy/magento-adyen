<?php

declare(strict_types=1);

namespace Tapbuy\Adyen\Plugin;

use Adyen\Payment\Gateway\Request\OriginDataBuilder as AdyenOriginDataBuilder;
use Tapbuy\Adyen\Model\AdyenOriginExtractor;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;
use Tapbuy\RedirectTracking\Api\TapbuyRequestDetectorInterface;

class OriginDataBuilderPlugin
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TapbuyRequestDetectorInterface
     */
    private $requestDetector;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var AdyenOriginExtractor
     */
    private $originExtractor;

    /**
     * @param LoggerInterface $logger
     * @param TapbuyRequestDetectorInterface $requestDetector
     * @param ConfigInterface $config
     * @param AdyenOriginExtractor $originExtractor
     */
    public function __construct(
        LoggerInterface $logger,
        TapbuyRequestDetectorInterface $requestDetector,
        ConfigInterface $config,
        AdyenOriginExtractor $originExtractor
    ) {
        $this->logger = $logger;
        $this->requestDetector = $requestDetector;
        $this->config = $config;
        $this->originExtractor = $originExtractor;
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
        if (!$this->requestDetector->isTapbuyCall() || !$this->config->isEnabled()) {
            return $result;
        }

        $tapbuyOrigin = $this->originExtractor->extractOriginFromTapbuyRequest();
        if ($tapbuyOrigin) {
            $originalOrigin = $result['body']['origin'] ?? null;
            $result['body']['origin'] = $tapbuyOrigin;
            
            $this->logger->info('Adyen origin modified for Tapbuy call', [
                'original_origin' => $originalOrigin,
                'tapbuy_origin' => $tapbuyOrigin,
            ]);
        }

        return $result;
    }
}
