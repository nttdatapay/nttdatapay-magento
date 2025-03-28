<?php

namespace Ndps\Aipay\Model;

use Ndps\Aipay\Helper\Aipaycheckout;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    const KEY_ALLOW_SPECIFIC    = 'allowspecific';
    const KEY_SPECIFIC_COUNTRY  = 'specificcountry';
    const KEY_ACTIVE            = 'active';
    const KEY_APP_ID            = 'app_id';
    const KEY_SECRET_ID         = 'secret_key';
    const KEY_TITLE             = 'title';
    const KEY_NEW_ORDER_STATUS  = 'order_status';
    const KEY_ENABLE_INVOICE    = 'enable_invoice';
    const  KEY_ENVIRONMENT      = "environment";
    const KEY_PAYMENT_ACTION    = "payment_action";

    /**
     * @var string
     */
    protected $methodCode = 'ndps';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var int
     */
    protected $storeId = null;
    /**
     * @var Aipaycheckout
     */
    private $helper;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     * @param Aipaycheckout $helper
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter,
        Aipaycheckout $helper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->helper           = $helper;
    }

    public function getAppId()
    {
        return $this->getConfigData(self::KEY_APP_ID);
    }

    public function getSecretKey()
    {
        return $this->getConfigData(self::KEY_SECRET_ID);
    }

    public function getTitle()
    {
        return $this->getConfigData(self::KEY_TITLE);
    }

    public function getCfEnvironment()
    {
        return $this->getConfigData(self::KEY_ENVIRONMENT);
    }

    public function getNewOrderStatus()
    {
        return $this->getConfigData(self::KEY_NEW_ORDER_STATUS);
    }

    /**
     * @return string
     */
    public function getReturnUrl() {
        $baseUrl = $this->helper->getUrl($this->getConfigData('return_url'),array('_secure'=>true));
        $returnUrl = $baseUrl;
        return $returnUrl;
    }

    /**
     * @return null
     */
    public function getNotifyUrl() {
        return $this->helper->getUrl($this->getConfigData('notify_url'),array('_secure'=>true));
    }

    /**
     * @param $field
     * @param $storeId
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        if ($storeId == null) {
            $storeId = $this->storeId;
        }

        $code = $this->methodCode;

        $path = 'payment/' . $code . '/' . $field;
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $field
     * @param $value
     * @return null
     */
    public function setConfigData($field, $value)
    {
        $code = $this->methodCode;

        $path = 'payment/' . $code . '/' . $field;

        return $this->configWriter->save($path, $value);
    }

    /**
     * @return bool
     */
    public function canSendInvoice()
    {
        return (bool) (int) $this->getConfigData(self::KEY_ENABLE_INVOICE, $this->storeId);
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return (bool) (int) $this->getConfigData(self::KEY_ACTIVE, $this->storeId);
    }

    /**
     * @param $country
     * @return bool
     */
    public function canUseForCountry($country)
    {
        /*
        for specific country, the flag will set up as 1
        */
        if ($this->getConfigData(self::KEY_ALLOW_SPECIFIC) == 1) {
            $availableCountries = explode(',', $this->getConfigData(self::KEY_SPECIFIC_COUNTRY));
            if (!in_array($country, $availableCountries)) {
                return false;
            }
        }

        return true;
    }

    public function getPaymentAction()
    {
        return $this->getConfigData(self::KEY_PAYMENT_ACTION);
    }
}
