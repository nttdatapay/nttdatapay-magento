<?php

namespace Ndps\Aipay\Controller\Standard;

use Ndps\Aipay\Controller\CfAbstract;
use Ndps\Aipay\Model\Config;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\DB\Transaction;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;

/**
 * Class Notify
 * To notify customer when if there is any network failure during payment
 * @package Ndps\Aipay\Controller\Standard\Notify
 */
class Notify extends CfAbstract implements CsrfAwareActionInterface
{
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
     * @param Transaction $transaction
     * @param Session $checkoutSession
     * @param InvoiceService $invoiceService
     * @param CartRepositoryInterface $quoteRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderSender $orderSender
     * @param InvoiceSender $invoiceSender
     */

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        Config                   $config,
        Context                  $context,
        Transaction              $transaction,
        Session                  $checkoutSession,
        InvoiceService           $invoiceService,
        CartRepositoryInterface  $quoteRepository,
        OrderRepositoryInterface $orderRepository,
        OrderSender              $orderSender,
        InvoiceSender            $invoiceSender
    )
    {
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
     * Execute webhook in case of network failure
     *
     * @return void
     */
    public function execute() {
    }

}
