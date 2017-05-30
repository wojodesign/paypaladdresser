<?php

namespace Craft;

class PaypalAddresserPlugin extends BasePlugin
{
    public function getName()
    {
        return Craft::t('Paypal Addressers');
    }

    public function getVersion()
    {
        return '1.0';
    }

    public function getDeveloper()
    {
        return 'Wojo Design';
    }

    public function getDeveloperUrl()
    {
        return 'http://wojo.design';
    }

    public function init(){

    	craft()->on( 'commerce_transactions.onSaveTransaction', function(Event $event) {

    		//Get the transaction from the event
    		$transaction = $event->params['transaction'];
    		craft()->paypalAddresser_paypaladdress->setAddress($transaction);

    	});
    }

    public function commerce_modifyPaymentRequest($data){
    	$data['noShipping'] = 0;
      	return $data;
    }
}
