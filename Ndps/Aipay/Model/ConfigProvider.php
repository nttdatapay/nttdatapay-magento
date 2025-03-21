<?php

namespace Ndps\Aipay\Model;

use Ndps\Aipay\Model\PaymentMethod;
use Magento\Checkout\Model\ConfigProviderInterface;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCode = PaymentMethod::METHOD_CODE;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @param Config
     */
    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * @return array|void
     */
    public function getConfig()
    {
        if (!$this->config->isActive()) {
            return [];
        }

        $config = [
            'payment' => [
                'ndps' => [
                    'title'         => $this->config->getTitle()
                ],
            ],
        ];

        return $config;
    }
}
