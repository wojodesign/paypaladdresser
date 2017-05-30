<?php

namespace Craft;

class PaypalAddresser_PaypaladdressService extends BaseApplicationComponent {

	public function setAddress($transaction){
		$paymentMethod = craft()->commerce_paymentMethods->getPaymentMethodById($transaction->paymentMethodId);
		//if the transaction is a paypal express success (coming back from the gateway) carry on
		if ($transaction->status == 'success' && $paymentMethod->class == 'PayPal_Express') {

			//Get the order the transaction relates to
			$criteria = craft()->elements->getCriteria('Commerce_Order');
			$order = $criteria->find(['id'=> $transaction->orderId])[0];

			//Check if the order already has a shipping address
			$orderHasShippingAddress = craft()->commerce_addresses->getAddressById($order->shippingAddressId);
			if(!$orderHasShippingAddress){
				//Create a new address model and set its attributes
				$address = new Commerce_AddressModel();

				//Get address data from paypal
				$paypalAddressData = $this->getPaypalAddressData($transaction->response);
				$address->setAttributes($paypalAddressData);
				//apply the address to the order and save it
				craft()->commerce_orders->setOrderAddresses($order, $address, $address);
				craft()->commerce_orders->saveOrder($order);
			}
		}
	}

	public function getPaypalAddressData($response){
		$paypalCreds = $this->getPaypalCreds();
		if(empty($paypalCreds)){return false;}
		$apiUrl = $paypalCreds['testMode'] ? 'https://api-3t.sandbox.paypal.com/nvp' : 'https://api-3t.paypal.com/nvp';
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_URL, $apiUrl);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query(array(
		    'USER' => $paypalCreds['username'],
		    'PWD' => $paypalCreds['password'],
		    'SIGNATURE' => $paypalCreds['signature'],

		    'METHOD' => 'GetExpressCheckoutDetails',
		    'VERSION' => $response['VERSION'],

		    'TOKEN' => $response['TOKEN']
		)));

		$response = curl_exec($curl);
		curl_close($curl);
		$nvp = array();
		if (preg_match_all('/(?<name>[^\=]+)\=(?<value>[^&]+)&?/', $response, $matches)) {
		    foreach ($matches['name'] as $offset => $name) {
		        $nvp[$name] = urldecode($matches['value'][$offset]);
		    }
		}

		//echo('<pre>');print_r($nvp);echo('</pre>');exit;
		$stateId = craft()->commerce_states->getStateByAttributes(['abbreviation'=> $nvp['SHIPTOSTATE']]);
		$countryId = craft()->commerce_countries->getCountryByAttributes(['iso'=> $nvp['SHIPTOCOUNTRYCODE']]);
		return array(
			'firstName' => $nvp['FIRSTNAME'],
			'lastName' => $nvp['LASTNAME'],
			'address1' => $nvp['SHIPTOSTREET'],
			'city' => $nvp['SHIPTOCITY'],
			'zipCode' => $nvp['SHIPTOZIP'],
			'stateId' => $stateId->id,
			'countryId' => $countryId->id
		);
	}


	public function getPaypalCreds(){
		$paymentMethods = craft()->commerce_paymentMethods->getAllPaymentMethods();
		$settings = array();

		foreach ($paymentMethods as $key => $method) {
			if($method->class == 'PayPal_Express'){
				$settings = $method->settings;
			}
		}

		return $settings;
	}


}
