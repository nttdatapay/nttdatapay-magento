define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
],function(Component,renderList){
    'use strict';
    renderList.push({
        type : 'ndps',
        component : 'Ndps_Aipay/js/view/payment/method-renderer/ndps-method'
    });

    return Component.extend({});
})
