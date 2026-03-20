<?php

declare(strict_types=1);

namespace Tapbuy\Adyen\Test\Unit\Plugin;

use Adyen\Payment\Gateway\Request\OriginDataBuilder as AdyenOriginDataBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\Adyen\Model\AdyenOriginExtractor;
use Tapbuy\Adyen\Plugin\OriginDataBuilderPlugin;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;
use Tapbuy\RedirectTracking\Api\TapbuyRequestDetectorInterface;

class OriginDataBuilderPluginTest extends TestCase
{
    private OriginDataBuilderPlugin $plugin;
    private LoggerInterface&MockObject $logger;
    private TapbuyRequestDetectorInterface&MockObject $requestDetector;
    private ConfigInterface&MockObject $config;
    private AdyenOriginExtractor&MockObject $originExtractor;
    private AdyenOriginDataBuilder&MockObject $subject;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->requestDetector = $this->createMock(TapbuyRequestDetectorInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->originExtractor = $this->createMock(AdyenOriginExtractor::class);
        $this->subject = $this->createMock(AdyenOriginDataBuilder::class);

        $this->plugin = new OriginDataBuilderPlugin(
            $this->logger,
            $this->requestDetector,
            $this->config,
            $this->originExtractor
        );
    }

    public function testAfterBuildReturnsUnmodifiedResultWhenNotTapbuyCall(): void
    {
        $this->requestDetector->method('isTapbuyCall')->willReturn(false);
        $this->config->method('isEnabled')->willReturn(true);

        $result = ['body' => ['origin' => 'https://original.com']];

        $this->assertSame($result, $this->plugin->afterBuild($this->subject, $result, []));
    }

    public function testAfterBuildReturnsUnmodifiedResultWhenDisabled(): void
    {
        $this->requestDetector->method('isTapbuyCall')->willReturn(true);
        $this->config->method('isEnabled')->willReturn(false);

        $result = ['body' => ['origin' => 'https://original.com']];

        $this->assertSame($result, $this->plugin->afterBuild($this->subject, $result, []));
    }

    public function testAfterBuildReturnsUnmodifiedResultWhenExtractionReturnsNull(): void
    {
        $this->requestDetector->method('isTapbuyCall')->willReturn(true);
        $this->config->method('isEnabled')->willReturn(true);
        $this->originExtractor->method('extractOriginFromTapbuyRequest')->willReturn(null);

        $result = ['body' => ['origin' => 'https://original.com']];

        $this->assertSame($result, $this->plugin->afterBuild($this->subject, $result, []));
    }

    public function testAfterBuildOverridesOriginWhenTapbuyOriginExtracted(): void
    {
        $this->requestDetector->method('isTapbuyCall')->willReturn(true);
        $this->config->method('isEnabled')->willReturn(true);
        $this->originExtractor->method('extractOriginFromTapbuyRequest')
            ->willReturn('https://tapbuy.io');

        $result = ['body' => ['origin' => 'https://original.com']];

        $modified = $this->plugin->afterBuild($this->subject, $result, []);

        $this->assertSame('https://tapbuy.io', $modified['body']['origin']);
    }

    public function testAfterBuildLogsOriginModification(): void
    {
        $this->requestDetector->method('isTapbuyCall')->willReturn(true);
        $this->config->method('isEnabled')->willReturn(true);
        $this->originExtractor->method('extractOriginFromTapbuyRequest')
            ->willReturn('https://tapbuy.io');

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Adyen origin modified for Tapbuy call',
                [
                    'original_origin' => 'https://original.com',
                    'tapbuy_origin' => 'https://tapbuy.io',
                ]
            );

        $result = ['body' => ['origin' => 'https://original.com']];

        $this->plugin->afterBuild($this->subject, $result, []);
    }

    public function testAfterBuildHandlesMissingOriginalOrigin(): void
    {
        $this->requestDetector->method('isTapbuyCall')->willReturn(true);
        $this->config->method('isEnabled')->willReturn(true);
        $this->originExtractor->method('extractOriginFromTapbuyRequest')
            ->willReturn('https://tapbuy.io');

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Adyen origin modified for Tapbuy call',
                [
                    'original_origin' => null,
                    'tapbuy_origin' => 'https://tapbuy.io',
                ]
            );

        $result = ['body' => []];

        $modified = $this->plugin->afterBuild($this->subject, $result, []);

        $this->assertSame('https://tapbuy.io', $modified['body']['origin']);
    }
}
