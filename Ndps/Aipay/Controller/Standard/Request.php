<?php

namespace Ndps\Aipay\Controller\Standard;

use Ndps\Aipay\Controller\CfAbstract;
use Ndps\Aipay\Helper\Aipaycheckout;
use Ndps\Aipay\Model\Config;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DB\Transaction;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;

/**
 * Class Request
 * Generate request data to create order and proceed payment
 * @package Ndps\Aipay\Controller\Standard\Notify
 */

class Request extends CfAbstract
{
    
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Context
     */

    protected $context;

    /**
     * @var Aipaycheckout
     */

    protected $helper;

    /**
     * @var Transaction
     */
    protected $transaction;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var OrderSender
     */
    protected $orderSender;

    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @param LoggerInterface $logger
     * @param Config $config
     * @param Context $context
     * @param Aipaycheckout $helper
     * @param Transaction $transaction
     * @param Session $checkoutSession
     * @param InvoiceService $invoiceService
     * @param CartRepositoryInterface $quoteRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderSender $orderSender
     * @param InvoiceSender $invoiceSender
     */
    public function __construct(
        LoggerInterface $logger,
        Config $config,
        Context $context,
        Aipaycheckout $helper,
        Transaction $transaction,
        Session $checkoutSession,
        InvoiceService $invoiceService,
        CartRepositoryInterface $quoteRepository,
        OrderRepositoryInterface $orderRepository,
        OrderSender $orderSender,
        InvoiceSender $invoiceSender
    ) {
        parent::__construct(
            $logger,
            $config,
            $context,
            $transaction,
            $checkoutSession,
            $invoiceService,
            $quoteRepository,
            $orderRepository,
            $orderSender,
            $invoiceSender
        );

        $this->helper   = $helper;
    }

    /**
     * Get order token for process the payment
     * @return ResultInterface
     */
    public function execute()
    {      
        $order = $this->checkoutSession->getLastRealOrder();
        $ndpsOrderId = $order->getIncrementId();
        $new_order_status = $this->config->getNewOrderStatus();

        $magento_version = $this->_objectManager->get('Magento\Framework\App\ProductMetadataInterface')->getVersion();
        $module_version =  $this->_objectManager->get('Magento\Framework\Module\ModuleList')->getOne('Ndps_Aipay')['setup_version'];

        $orderModel = $this->_objectManager->get('Magento\Sales\Model\Order')->load($order->getEntityId());

        $orderModel->setState('new')
                   ->setStatus($new_order_status)
                   ->save();

        $code = 400;

        $payMode = $this->config->getCfEnvironment(); 
        $merchId = $this->config->getConfigData('ndps_merchId');
        $productId = $this->config->getConfigData('ndps_product_id');
        $txnPassword = $this->config->getConfigData('ndps_txn_password');
        $encryptionKey = $this->config->getConfigData('ndps_encryption_key');
        $decryptionKey = $this->config->getConfigData('ndps_decryption_key');
        $txnCurrency = $order->getOrderCurrencyCode();

        $this->logger->info(__("NDPS AUTH API URL: ".$this->getOrderUrl()));
        
        $atomTokenId = 0;
        
        $countryCode = "";
        if(empty($order->getShippingAddress())) {
            $countryId = $order->getBillingAddress()->getCountryId();
            $getCustomerNumber = $order->getBillingAddress()->getTelephone();
        } else {
            $countryId = $order->getShippingAddress()->getCountryId();
            $getCustomerNumber = $order->getShippingAddress()->getTelephone();
        }

        if(!empty($countryId)){
            $countryCode = $this->helper->getPhoneCode($countryId);
        }

        $customerNumber = preg_replace("/[^0-9]/", '', $getCustomerNumber);

        $email = strip_tags($order->getCustomerEmail());
        $mobileNumber = strip_tags($customerNumber);

        $amount = round($order->getGrandTotal(), 2);

        if(!empty($email)) {

            $jsondata = '{"payInstrument":{"headDetails":{"version":"OTSv1.1","api":"AUTH","platform":"FLASH"},"merchDetails":{"merchId":"'.$merchId.'","userId":"","password":"'.$txnPassword.'","merchTxnId":"'.$ndpsOrderId.'","merchTxnDate":"'.date('Y-m-d H:i:s').'"},"payDetails":{"amount":"'.$amount.'","product":"'.$productId.'","custAccNo":"213232323","txnCurrency":"'.$txnCurrency.'"},"custDetails":{"custEmail":"'.$email.'","custMobile":"'.$mobileNumber.'"},"extras":{"udf1":"","udf2":"","udf3":"","udf4":"","udf5":""}}}';    

            $this->logger->info(__("NDPS AUTH API JSON string request: ".$jsondata));   

            $encData = $this->encrypt($jsondata, $encryptionKey, $encryptionKey);

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $this->getOrderUrl(),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => 1,
                CURLOPT_CAINFO => __DIR__ . '\cacert.pem',
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "encData=".$encData."&merchId=".$merchId,
                CURLOPT_HTTPHEADER => array(
                  "Content-Type: application/x-www-form-urlencoded"
                ),
              ));
             
              $response = curl_exec($curl);
              $this->logger->info(__("NDPS AUTH API response: ".$response));
              $getresp = explode("&", $response);
              $encresp = substr($getresp[1], strpos($getresp[1], "=") + 1);

              $decData = $this->decrypt($encresp, $decryptionKey, $decryptionKey);
              $this->logger->info(__("NDPS AUTH API decrypted response: ".$decData));
            
              if(curl_errno($curl)) {
                  $error_msg = curl_error($curl);
                  echo "error = ".$error_msg;
              }      
          
              if(isset($error_msg)) {
                  // TODO - Handle cURL error accordingly
                  echo "error = ".$error_msg;
              }   
                
              curl_close($curl);
              $res = json_decode($decData, true);
              if($res){
                if($res['responseDetails']['txnStatusCode'] == 'OTS0000'){
                   $atomTokenId = $res['atomTokenId'];
                   $code = 200; // success response
                   $responseContent = [
                    'success'               => true,
                    'customerNumber'        => $mobileNumber,
                    'customerEmailId'       => $email,
                    'merchTxnId'            => $ndpsOrderId,
                    'merchantId'            => (string)$merchId,
                    'returnURL'             => $this->config->getReturnUrl(),
                    'atomTokenId'           => (string)$atomTokenId,
                    'environment'           => $payMode
                  ]; 
                }else{
                   $atomTokenId = null;
                   $code = 400; // failed response
                   $responseContent = [
                    'message'       => 'Atom token id is not generated. Please check your configuration or contact our support team.',
                    'parameters'    => []
                  ];
                }
              }
 
        }else{
            $responseContent = [
                'message'       => 'Email is mandatory. Please add a valid email.',
                'parameters'    => []
            ];
        }    

        $this->logger->info(__("NDPS execute response code: ".$code));   
        $this->logger->info(__("NDPS atom token Id: ".$atomTokenId));   
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response->setData($responseContent);
        $response->setHttpResponseCode($code);

        return $response;

    }

    public function encrypt($data = '', $key = NULL, $salt = "") {
        if($key != NULL && $data != "" && $salt != ""){
           $method = "AES-256-CBC";
            $iv = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
            $chars = array_map("chr", $iv);
            $IVbytes = join($chars);
            $salt1 = mb_convert_encoding($salt, "UTF-8"); //Encoding to UTF-8
            $key1 = mb_convert_encoding($key, "UTF-8"); //Encoding to UTF-8
            //SecretKeyFactory Instance of PBKDF2WithHmacSHA1 Java Equivalent
            $hash = openssl_pbkdf2($key1,$salt1,'256','65536', 'sha512'); 
            $encrypted = openssl_encrypt($data, $method, $hash, OPENSSL_RAW_DATA, $IVbytes);
            return strtoupper(bin2hex($encrypted));
        }else{
            return "String to encrypt, Salt and Key is required.";
        }
    }

    public function decrypt($data="", $key = NULL, $salt = "") {
        if($key != NULL && $data != "" && $salt != ""){
            $dataEncypted = hex2bin($data);
            $method = "AES-256-CBC";
            //Converting Array to bytes
            $iv = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
            $chars = array_map("chr", $iv);
            $IVbytes = join($chars);
            $salt1 = mb_convert_encoding($salt, "UTF-8");//Encoding to UTF-8
            $key1 = mb_convert_encoding($key, "UTF-8");//Encoding to UTF-8
            //SecretKeyFactory Instance of PBKDF2WithHmacSHA1 Java Equivalent
            $hash = openssl_pbkdf2($key1,$salt1,'256','65536', 'sha512'); 
            $decrypted = openssl_decrypt($dataEncypted, $method, $hash, OPENSSL_RAW_DATA, $IVbytes);
            return $decrypted;
        }else{
            return "Encrypted String to decrypt, Salt and Key is required.";
        }
    }

}
