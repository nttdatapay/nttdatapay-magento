define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'jquery',
        'ko',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/set-payment-information',
        'mage/url',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Ui/js/model/messageList',
        'Magento_Checkout/js/model/shipping-save-processor'
    ],
    function (Component, quote, $, ko, additionalValidators, setPaymentInformationAction, url, customer, placeOrderAction, fullScreenLoader, messageList) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Ndps_Aipay/payment/cfcheckout',
                ndpsDataFrameLoaded: false,
                cf_response: {
                    transaction: {},
                    order: {}
                }
            },

            context: function () {
                return this;
            },

            isShowLegend: function () {
                return true;
            },

            getCode: function () {
                return 'ndps';
            },

            getTitle: function () {
                return window.checkoutConfig.payment.ndps.title;
            },

            isActive: function () {
                return true;
            },

            isAvailable: function () {
                return this.ndpsDataFrameLoaded;
            },

            handleError: function (error) {
                alert(error);
                if (_.isObject(error)) {
                    this.messageContainer.addErrorMessage(error);
                } else {
                    this.messageContainer.addErrorMessage({
                        message: error
                    });
                }
            },

            initObservable: function () {
                var self = this._super();

                if (!self.ndpsDataFrameLoaded) {

                    self.ndpsDataFrameLoaded = true;
                }
                return self;
            },

            /**
            * @override
            */
            /** Process Payment */
            preparePayment: function (context, event) {
                if (!additionalValidators.validate()) {
                    return false;
                }

                fullScreenLoader.startLoader();
                this.placeOrder(event);
                return;
            },

            placeOrder: function (event) {
                var self = this;

                if (event) {
                    event.preventDefault();
                }

                if (!self.orderId) {
                    this.isPlaceOrderActionAllowed(false);
                    this.getPlaceOrderDeferredObject()
                        .fail(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                            }
                        ).done(
                            function (orderId) {
                                self.getNdpsOrder(orderId);
                                self.orderId = orderId;
                            }
                        );
                } else {
                    self.getNdpsOrder(self.orderId);
                }

                return;

            },

            getNdpsOrder: function (orderId) {
                var self = this;
                self.isPaymentProcessing = $.Deferred();
                $.ajax({
                    type: 'POST',
                    url: url.build('ndps/standard/request'),

                    /**
                     * Success callback
                     * @param {Object} response
                     */
                    success: function (response) {
                        fullScreenLoader.stopLoader();
                        if (response.success) {
                            self.doCheckoutPayment(response);
                        } else {
                            self.isPaymentProcessing.reject(response.message);
                        }
                    },

                    /**
                     * Error callback
                     * @param {*} response
                     */
                    error: function (response) {
                        fullScreenLoader.stopLoader();
                        self.isPaymentProcessing.reject(response.message);
                        self.handleError(response.responseJSON.message);
                        self.isPlaceOrderActionAllowed(true);
                    }
                });
            },

            doCheckoutPayment: function (aipayresponse) {
                var jsCdnUrl = 'https://pgtest.atomtech.in/staticdata/ots/js/atomcheckout.js';  
                if(aipayresponse.environment != 'sandbox') {
                  jsCdnUrl = 'https://psa.atomtech.in/staticdata/ots/js/atomcheckout.js';
                }
                
                $.getScript(jsCdnUrl, function () {
                    // This function will be executed once the script is loaded and executed.

                    const options = {
                        "atomTokenId": aipayresponse.atomTokenId,
                        "merchId": aipayresponse.merchantId,
                        "custEmail": aipayresponse.customerEmailId,
                        "custMobile": aipayresponse.customerNumber,
                        "returnUrl": aipayresponse.returnURL,
                    };
                    console.log(options);
                    let atom = new AtomPaynetz(options, 'uat');
                });

                return;

            }
        });
    });
