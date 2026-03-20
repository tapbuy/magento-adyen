<?php

declare(strict_types=1);

namespace Tapbuy\Adyen\Test\Unit\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\Adyen\Model\AdyenOriginExtractor;
use Tapbuy\RedirectTracking\Api\LoggerInterface;

class AdyenOriginExtractorTest extends TestCase
{
    private AdyenOriginExtractor $extractor;
    private RequestInterface&MockObject $request;
    private Json&MockObject $json;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->request = $this->getMockBuilder(RequestInterface::class)
            ->addMethods(['getContent'])
            ->getMockForAbstractClass();
        $this->json = $this->createMock(Json::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->extractor = new AdyenOriginExtractor(
            $this->request,
            $this->json,
            $this->logger
        );
    }

    public function testReturnsNullWhenRequestBodyIsEmpty(): void
    {
        $this->request->method('getContent')->willReturn('');

        $this->assertNull($this->extractor->extractOriginFromTapbuyRequest());
    }

    public function testReturnsNullWhenRequestBodyIsNull(): void
    {
        $this->request->method('getContent')->willReturn(null);

        $this->assertNull($this->extractor->extractOriginFromTapbuyRequest());
    }

    public function testReturnsNullWhenRequestBodyIsInvalidJson(): void
    {
        $this->request->method('getContent')->willReturn('not-json');
        $this->json->method('unserialize')
            ->willThrowException(new \InvalidArgumentException('Invalid JSON'));

        $this->logger->expects($this->once())->method('warning');

        $this->assertNull($this->extractor->extractOriginFromTapbuyRequest());
    }

    public function testReturnsNullWhenNoPaymentMethodInPayload(): void
    {
        $body = json_encode(['variables' => ['other' => 'data']]);
        $this->request->method('getContent')->willReturn($body);
        $this->json->method('unserialize')->willReturn(['variables' => ['other' => 'data']]);

        $this->assertNull($this->extractor->extractOriginFromTapbuyRequest());
    }

    public function testReturnsNullWhenNoAdyenAdditionalDataPresent(): void
    {
        $payload = ['variables' => ['paymentMethod' => ['code' => 'checkmo']]];
        $body = json_encode($payload);
        $this->request->method('getContent')->willReturn($body);
        $this->json->method('unserialize')->willReturnCallback(function ($input) use ($payload, $body) {
            return $input === $body ? $payload : null;
        });

        $this->assertNull($this->extractor->extractOriginFromTapbuyRequest());
    }

    public function testReturnsNullWhenStateDataIsMissing(): void
    {
        $payload = ['variables' => ['paymentMethod' => ['adyen_additional_data_cc' => ['other' => 'value']]]];
        $body = json_encode($payload);
        $this->request->method('getContent')->willReturn($body);
        $this->json->method('unserialize')->willReturnCallback(function ($input) use ($payload, $body) {
            return $input === $body ? $payload : null;
        });

        $this->assertNull($this->extractor->extractOriginFromTapbuyRequest());
    }

    public function testReturnsNullWhenStateDataJsonIsInvalid(): void
    {
        $payload = ['variables' => ['paymentMethod' => ['adyen_additional_data_cc' => ['stateData' => 'bad-json']]]];
        $body = json_encode($payload);

        $this->request->method('getContent')->willReturn($body);
        $this->json->method('unserialize')->willReturnCallback(function ($input) use ($payload, $body) {
            if ($input === $body) {
                return $payload;
            }
            throw new \InvalidArgumentException('Invalid JSON');
        });

        $this->assertNull($this->extractor->extractOriginFromTapbuyRequest());
    }

    public function testReturnsNullWhenOriginIsMissingFromStateData(): void
    {
        $stateData = json_encode(['paymentMethod' => ['type' => 'scheme']]);
        $payload = ['variables' => ['paymentMethod' => ['adyen_additional_data_cc' => ['stateData' => $stateData]]]];
        $body = json_encode($payload);

        $this->request->method('getContent')->willReturn($body);
        $this->json->method('unserialize')->willReturnCallback(function ($input) use ($payload, $body, $stateData) {
            if ($input === $body) {
                return $payload;
            }
            if ($input === $stateData) {
                return ['paymentMethod' => ['type' => 'scheme']];
            }
            return null;
        });

        $this->assertNull($this->extractor->extractOriginFromTapbuyRequest());
    }

    public function testExtractsAndNormalizesOriginFromCcAdditionalData(): void
    {
        $stateData = json_encode(['origin' => 'https://www.example.com/checkout']);
        $payload = ['variables' => ['paymentMethod' => ['adyen_additional_data_cc' => ['stateData' => $stateData]]]];
        $body = json_encode($payload);

        $this->request->method('getContent')->willReturn($body);
        $this->json->method('unserialize')->willReturnCallback(function ($input) use ($payload, $body, $stateData) {
            if ($input === $body) {
                return $payload;
            }
            if ($input === $stateData) {
                return ['origin' => 'https://www.example.com/checkout'];
            }
            return null;
        });

        $this->assertSame('https://www.example.com', $this->extractor->extractOriginFromTapbuyRequest());
    }

    public function testExtractsOriginFromHppAdditionalData(): void
    {
        $stateData = json_encode(['origin' => 'https://shop.example.com:8443/pay']);
        $payload = ['variables' => ['paymentMethod' => ['adyen_additional_data_hpp' => ['stateData' => $stateData]]]];
        $body = json_encode($payload);

        $this->request->method('getContent')->willReturn($body);
        $this->json->method('unserialize')->willReturnCallback(function ($input) use ($payload, $body, $stateData) {
            if ($input === $body) {
                return $payload;
            }
            if ($input === $stateData) {
                return ['origin' => 'https://shop.example.com:8443/pay'];
            }
            return null;
        });

        $this->assertSame('https://shop.example.com:8443', $this->extractor->extractOriginFromTapbuyRequest());
    }

    public function testHppTakesPrecedenceOverCcWhenBothPresent(): void
    {
        $stateDataCc = json_encode(['origin' => 'https://cc.example.com']);
        $stateDataHpp = json_encode(['origin' => 'https://hpp.example.com']);
        $payload = ['variables' => ['paymentMethod' => [
            'adyen_additional_data_cc' => ['stateData' => $stateDataCc],
            'adyen_additional_data_hpp' => ['stateData' => $stateDataHpp],
        ]]];
        $body = json_encode($payload);

        $this->request->method('getContent')->willReturn($body);
        $this->json->method('unserialize')->willReturnCallback(function ($input) use ($payload, $body, $stateDataHpp) {
            if ($input === $body) {
                return $payload;
            }
            if ($input === $stateDataHpp) {
                return ['origin' => 'https://hpp.example.com'];
            }
            return null;
        });

        // HPP is the last key in findValueByKeys, so it wins
        $this->assertSame('https://hpp.example.com', $this->extractor->extractOriginFromTapbuyRequest());
    }

    public function testReturnsNullForMalformedOriginUrl(): void
    {
        $stateData = json_encode(['origin' => 'not-a-url']);
        $payload = ['variables' => ['paymentMethod' => ['adyen_additional_data_cc' => ['stateData' => $stateData]]]];
        $body = json_encode($payload);

        $this->request->method('getContent')->willReturn($body);
        $this->json->method('unserialize')->willReturnCallback(function ($input) use ($payload, $body, $stateData) {
            if ($input === $body) {
                return $payload;
            }
            if ($input === $stateData) {
                return ['origin' => 'not-a-url'];
            }
            return null;
        });

        $this->logger->expects($this->once())->method('warning');

        $this->assertNull($this->extractor->extractOriginFromTapbuyRequest());
    }

    public function testReturnsNullWhenOriginMissesScheme(): void
    {
        $stateData = json_encode(['origin' => 'www.example.com']);
        $payload = ['variables' => ['paymentMethod' => ['adyen_additional_data_cc' => ['stateData' => $stateData]]]];
        $body = json_encode($payload);

        $this->request->method('getContent')->willReturn($body);
        $this->json->method('unserialize')->willReturnCallback(function ($input) use ($payload, $body, $stateData) {
            if ($input === $body) {
                return $payload;
            }
            if ($input === $stateData) {
                return ['origin' => 'www.example.com'];
            }
            return null;
        });

        $this->logger->expects($this->once())->method('warning');

        $this->assertNull($this->extractor->extractOriginFromTapbuyRequest());
    }

    public function testReturnsNullWhenOriginIsEmptyString(): void
    {
        $stateData = json_encode(['origin' => '']);
        $payload = ['variables' => ['paymentMethod' => ['adyen_additional_data_cc' => ['stateData' => $stateData]]]];
        $body = json_encode($payload);

        $this->request->method('getContent')->willReturn($body);
        $this->json->method('unserialize')->willReturnCallback(function ($input) use ($payload, $body, $stateData) {
            if ($input === $body) {
                return $payload;
            }
            if ($input === $stateData) {
                return ['origin' => ''];
            }
            return null;
        });

        $this->assertNull($this->extractor->extractOriginFromTapbuyRequest());
    }

    public function testOriginWithPortIsPreserved(): void
    {
        $stateData = json_encode(['origin' => 'http://localhost:3000']);
        $payload = ['variables' => ['paymentMethod' => ['adyen_additional_data_cc' => ['stateData' => $stateData]]]];
        $body = json_encode($payload);

        $this->request->method('getContent')->willReturn($body);
        $this->json->method('unserialize')->willReturnCallback(function ($input) use ($payload, $body, $stateData) {
            if ($input === $body) {
                return $payload;
            }
            if ($input === $stateData) {
                return ['origin' => 'http://localhost:3000'];
            }
            return null;
        });

        $this->assertSame('http://localhost:3000', $this->extractor->extractOriginFromTapbuyRequest());
    }
}
