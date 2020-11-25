<?php
class ModelExtensionPaymentPointCheckoutCard extends Model {
	const PMT = '_card';
	private $prefixPaymentMethodKey = 'payment_pointcheckout' . self::PMT;

	public function getMethod($address, $total) {
		$this->load->language('extension/payment/pointcheckout' . self::PMT);

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get($this->prefixPaymentMethodKey . '_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

		if ($this->config->get($this->prefixPaymentMethodKey . '_total') > 0 && $this->config->get($this->prefixPaymentMethodKey . '_total') > $total) {
			$status = false;
		} elseif (!$this->config->get($this->prefixPaymentMethodKey . '_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}
		if($status && $this->config->get($this->prefixPaymentMethodKey . '_applicable_countries')){
		    //check if payment_country is valid
		    $status = false;
		    foreach ($this->config->get($this->prefixPaymentMethodKey . '_country') as $applicableCountry){
		        if($applicableCountry == $this->session->data['payment_address']['country_id']){
		            $status = true;
		        }
		    }
		}
		
		//check if user_group is valid
		if($status && $this->config->get($this->prefixPaymentMethodKey . '_applicable_usergroups')){
		    $this->load->model('account/customer');
		    $customerInfo = $this->model_account_customer->getCustomer($this->session->data['customer_id']);
		    $status=false;
		    foreach ($this->config->get($this->prefixPaymentMethodKey . '_user_group') as $applicableUserGroup){
		        if($applicableUserGroup == $customerInfo['customer_group_id']){
		            $status = true;
		        }
		    }
		}
		
		

		$method_data = array();

		if ($status) {
			$method_data = array(
				'code'       => 'pointcheckout' . self::PMT . '',
				// as of OpenCart 3.0.3.6, max length for the title in the admin is 128 characters, try no to exceed it
				'title'      =>  $this->language->get('text_title') . ' <img src="' . HTTPS_SERVER .'catalog/view/theme/default/image/pc_cards.png" height="25"/>',
				'terms'      => '',
				'sort_order' => $this->config->get($this->prefixPaymentMethodKey . '_sort_order')
			);
		}

		return $method_data;
	}	
}