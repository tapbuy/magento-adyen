<?php

namespace Tapbuy\Adyen\Plugin;

use Magento\Framework\App\RequestInterface;

class OriginDataBuilderPlugin
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @param RequestInterface $request
     */
    public function __construct(
        RequestInterface $request
    ) {
        $this->request = $request;
    }

    /**
     * Plugin to modify origin data before it is built.
     *
     * @param array $result
     * @param array $buildSubject
     * @return array
     */
    public function afterBuild(array $result, array $buildSubject): array
    {
        $isTapbuyCall = $this->request->getHeader('X-Tapbuy-Call');

        // @TODO: Change to tapbuy origin url if it's a Tapbuy call


        return $result;
    }
}
