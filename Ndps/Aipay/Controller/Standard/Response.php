<?php

namespace Ndps\Aipay\Controller\Standard;

use Ndps\Aipay\Controller\CfAbstract;
use Ndps\Aipay\Helper\Aipaycheckout;
use Ndps\Aipay\Model\Config;
use Exception;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\DB\Transaction;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey;

class Response extends CfAbstract implements CsrfAwareActionInterface
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
    protected $customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
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
     * @var OrderFactory
     */
    private $orderFactory;

    protected $request;

    protected $_request;

    /**
     * @var FormKey
     */
    private $formKey;

    /**
     * @param LoggerInterface $logger
     * @param Config $config
     * @param Context $context
     * @param Transaction $transaction
     * @param \Magento\Checkout\Model\Session $checkoutSession
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
        Transaction $transaction,
        OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        Aipaycheckout $checkoutHelper,
        InvoiceService $invoiceService,
        CartRepositoryInterface $quoteRepository,
        OrderRepositoryInterface $orderRepository,
        OrderSender $orderSender,
        InvoiceSender $invoiceSender,
        Http $request,
        FormKey $formKey
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
            $invoiceSender,
            $request,
            $formKey
        );
        $this->orderFactory     = $orderFactory;
        $this->request = $request;
        $this->_request = $context->getRequest();
        $this->formKey = $formKey;
        $this->request->setParam('form_key', $this->formKey->getFormKey());
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Get order response from Ndps to complete order
     * @return Redirect
     * @throws Exception
     */
    public function execute()
    {
        $request = $this->getRequest()->getParams();
        $this->logger->info('Ndps transaction response Data: ' . print_r($request, true));
        $resultRedirect = $this->resultRedirectFactory->create();
        $encData = $request['encData'];
        $decryptedData = $this->decrypt($encData, $this->config->getConfigData('ndps_decryption_key'), $this->config->getConfigData('ndps_decryption_key'));
        $this->logger->info('Ndps transaction response decrypted Data: ' . $decryptedData);
        $jsonData = json_decode($decryptedData, true);
      
        $orderIncrementId = $jsonData['payInstrument']['merchDetails']['merchTxnId'];
        $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);

        if ($jsonData['payInstrument']['responseDetails']['statusCode'] == "OTS0000") {
            $mageOrderStatus = $order->getStatus();
            if($mageOrderStatus === 'pending') {
                $this->logger->info(__("Ndps AIPAY order pending status: ". $mageOrderStatus));
                $this->processPayment( $orderIncrementId, $order);
            }
            $this->messageManager->addSuccess(__('Your payment was successful'));
            $resultRedirect->setPath('checkout/onepage/success');
            return $resultRedirect;
        } else if ($jsonData['payInstrument']['responseDetails']['statusCode'] == "CANCELLED") {
            $this->messageManager->addWarning(__('Your payment was cancel'));
            $this->checkoutSession->restoreQuote();
            $resultRedirect->setPath('checkout/cart');
            return $resultRedirect;
        } else if ($jsonData['payInstrument']['responseDetails']['statusCode'] == "OTS0600") {
            $this->messageManager->addErrorMessage(__('Your payment was failed'));
            $order->cancel();
            $order->save();
            $order->setState(CfAbstract::STATE_CANCELED)->setStatus(CfAbstract::STATE_CANCELED);
            $order->save();
            $resultRedirect->setPath('checkout/onepage/failure');
            return $resultRedirect;
        } else if($jsonData['payInstrument']['responseDetails']['statusCode'] == "OTS0551"){
            $this->checkoutSession->restoreQuote();
            $this->messageManager->addWarning(__('Your payment is pending'));
            $resultRedirect->setPath('checkout/cart');
            return $resultRedirect;
        } else{
            $this->checkoutSession->restoreQuote();
            $this->messageManager->addErrorMessage(__('There is an error. Payment status is pending'));
            $resultRedirect->setPath('checkout/cart');
            return $resultRedirect;
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
