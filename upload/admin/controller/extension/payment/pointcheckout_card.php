<?php
class ControllerExtensionPaymentPointCheckoutCard extends Controller {
	private $error = array();

	const PMT = '_card';
	const ENABLE_STG = false;
	private $prefixPaymentMethodKey = 'payment_pointcheckout' . self::PMT;
	private $extPath = 'extension/payment/pointcheckout' . self::PMT;
	private $extnsionPath = 'marketplace/extension';

	public function index() {
		$this->load->language($this->extPath);

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting($this->prefixPaymentMethodKey . '', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link($this->extnsionPath, 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}

		$data['pointcheckout_staging'] = self::ENABLE_STG;
		
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['key'])) {
			$data['error_key'] = $this->error['key'];
		} else {
			$data['error_key'] = '';
		}

		if (isset($this->error['secret'])) {
			$data['error_secret'] = $this->error['secret'];
		} else {
			$data['error_secret'] = '';
		}

		if (isset($this->error['specific_countries'])) {
		    $data['error_specific_countries'] = $this->error['specific_countries'];
		} else {
		    $data['error_specific_countries'] = '';
		}
		
		if (isset($this->error['user_group'])) {
		    $data['error_user_group'] = $this->error['user_group'];
		} else {
		    $data['error_user_group'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link($this->extnsionPath, 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link($this->extPath, 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link($this->extPath, 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link($this->extnsionPath, 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->request->post[$this->prefixPaymentMethodKey . '_key'])) {
			$data[$this->prefixPaymentMethodKey . '_key'] = $this->request->post[$this->prefixPaymentMethodKey . '_key'];
		} else {
			$data[$this->prefixPaymentMethodKey . '_key'] = $this->config->get($this->prefixPaymentMethodKey . '_key');
		}

		if (isset($this->request->post[$this->prefixPaymentMethodKey . '_secret'])) {
			$data[$this->prefixPaymentMethodKey . '_secret'] = $this->request->post[$this->prefixPaymentMethodKey . '_secret'];
		} else {
			$data[$this->prefixPaymentMethodKey . '_secret'] = $this->config->get($this->prefixPaymentMethodKey . '_secret');
		}
		
		if (isset($this->request->post[$this->prefixPaymentMethodKey . '_env'])) {
		    $data[$this->prefixPaymentMethodKey . '_env'] = $this->request->post[$this->prefixPaymentMethodKey . '_env'];
		} else {
		    $data[$this->prefixPaymentMethodKey . '_env'] = $this->config->get($this->prefixPaymentMethodKey . '_env');
		}
		

		if (isset($this->request->post[$this->prefixPaymentMethodKey . '_applicable_countries'])) {
			$data[$this->prefixPaymentMethodKey . '_applicable_countries'] = $this->request->post[$this->prefixPaymentMethodKey . '_applicable_countries'];
		} else {
			$data[$this->prefixPaymentMethodKey . '_applicable_countries'] = $this->config->get($this->prefixPaymentMethodKey . '_applicable_countries');
		}
		
		$data[$this->prefixPaymentMethodKey . '_country']=array();
		if (isset($this->request->post[$this->prefixPaymentMethodKey . '_country'])) {
		    $data[$this->prefixPaymentMethodKey . '_country']=$this->request->post[$this->prefixPaymentMethodKey . '_country'];
		    $countries = $this->request->post[$this->prefixPaymentMethodKey . '_country'];
		} else {
		    $countries = $this->config->get($this->prefixPaymentMethodKey . '_country');
		}
		
		
		$this->load->model('localisation/country');
		if(isset($countries)){
		    foreach ($countries as $country_id) {
		        $country_info = $this->model_localisation_country->getCountry($country_id);		        
		        if ($country_info) {
		            $data[$this->prefixPaymentMethodKey . '_country'][] = array(
		                'country_id' => $country_info['country_id'],
		                'name'        => $country_info['name']
		            );
		        }
		    }
		}
		
		
		if($data[$this->prefixPaymentMethodKey . '_applicable_countries']){
		    $data['pointcheckout_specific_countries']= '';
		    $data['pointcheckout_hide_countries']= '';
		}else{
		    $data['pointcheckout_specific_countries']= 'disabled';
		    $data['pointcheckout_hide_countries']= 'hidden';
		}
		
		
		if (isset($this->request->post[$this->prefixPaymentMethodKey . '_applicable_usergroups'])) {
		    $data[$this->prefixPaymentMethodKey . '_applicable_usergroups'] = $this->request->post[$this->prefixPaymentMethodKey . '_applicable_usergroups'];
		} else {
		    $data[$this->prefixPaymentMethodKey . '_applicable_usergroups'] = $this->config->get($this->prefixPaymentMethodKey . '_applicable_usergroups');
		}
		
		if($data[$this->prefixPaymentMethodKey . '_applicable_usergroups']){
		    $data['pointcheckout_specific_user_groups']= '';
		    $data['pointcheckout_hide_groups']= '';
		}else{
		    $data['pointcheckout_specific_user_groups']= 'disabled';
		    $data['pointcheckout_hide_groups']= 'hidden';
		}
		
		
		$data[$this->prefixPaymentMethodKey . '_user_group']=array();
		if (isset($this->request->post[$this->prefixPaymentMethodKey . '_user_group'])) {
		    $data[$this->prefixPaymentMethodKey . '_user_group']=$this->request->post[$this->prefixPaymentMethodKey . '_user_group'];
		    $user_groups = $this->request->post[$this->prefixPaymentMethodKey . '_user_group'];
		} else {
		    $user_groups = $this->config->get($this->prefixPaymentMethodKey . '_user_group');
		}
		
		
		$this->load->model('customer/customer_group');
		if(isset($user_groups)){
		    foreach ($user_groups as $group_id) {
		        $group_info = $this->model_customer_customer_group->getCustomerGroup($group_id);	
		        if ($group_info) {
		            $data[$this->prefixPaymentMethodKey . '_user_group'][] = array(
		                'group_id' => $group_info['customer_group_id'],
		                'name'     => $group_info['name']
		            );
		        }
		    }
		}
		

		if (isset($this->request->post[$this->prefixPaymentMethodKey . '_total'])) {
			$data[$this->prefixPaymentMethodKey . '_total'] = $this->request->post[$this->prefixPaymentMethodKey . '_total'];
		} else {
			$data[$this->prefixPaymentMethodKey . '_total'] = $this->config->get($this->prefixPaymentMethodKey . '_total');
		}
		
		if (isset($this->request->post[$this->prefixPaymentMethodKey . '_order_status_id'])) {
		    $data[$this->prefixPaymentMethodKey . '_order_status_id'] = $this->request->post[$this->prefixPaymentMethodKey . '_order_status_id'];
		} else if(null !== $this->config->get($this->prefixPaymentMethodKey . '_order_status_id')) {
		    $data[$this->prefixPaymentMethodKey . '_order_status_id'] = $this->config->get($this->prefixPaymentMethodKey . '_order_status_id');
		}else{
		    $data[$this->prefixPaymentMethodKey . '_order_status_id']=1;//default value is pendding 1
		}
		
		if (isset($this->request->post[$this->prefixPaymentMethodKey . '_payment_failed_status_id'])) {
		    $data[$this->prefixPaymentMethodKey . '_payment_failed_status_id'] = $this->request->post[$this->prefixPaymentMethodKey . '_payment_failed_status_id'];
		} else if(null !== $this->config->get($this->prefixPaymentMethodKey . '_payment_failed_status_id')) {
		    $data[$this->prefixPaymentMethodKey . '_payment_failed_status_id'] = $this->config->get($this->prefixPaymentMethodKey . '_payment_failed_status_id');
		}else{
		    $data[$this->prefixPaymentMethodKey . '_payment_failed_status_id']=10;//default value is failed 10
		}
		
		if (isset($this->request->post[$this->prefixPaymentMethodKey . '_payment_success_status_id'])) {
		    $data[$this->prefixPaymentMethodKey . '_payment_success_status_id'] = $this->request->post[$this->prefixPaymentMethodKey . '_payment_success_status_id'];
		} else if (null !== $this->config->get($this->prefixPaymentMethodKey . '_payment_success_status_id')){
		    $data[$this->prefixPaymentMethodKey . '_payment_success_status_id'] = $this->config->get($this->prefixPaymentMethodKey . '_payment_success_status_id');
		}else{
		    $data[$this->prefixPaymentMethodKey . '_payment_success_status_id']=2;//default value is proccessing 2
		}
		
		
		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->request->post[$this->prefixPaymentMethodKey . '_geo_zone_id'])) {
			$data[$this->prefixPaymentMethodKey . '_geo_zone_id'] = $this->request->post[$this->prefixPaymentMethodKey . '_geo_zone_id'];
		} else {
			$data[$this->prefixPaymentMethodKey . '_geo_zone_id'] = $this->config->get($this->prefixPaymentMethodKey . '_geo_zone_id');
		}

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post[$this->prefixPaymentMethodKey . '_status'])) {
			$data[$this->prefixPaymentMethodKey . '_status'] = $this->request->post[$this->prefixPaymentMethodKey . '_status'];
		} else {
			$data[$this->prefixPaymentMethodKey . '_status'] = $this->config->get($this->prefixPaymentMethodKey . '_status');
		}

		if (isset($this->request->post[$this->prefixPaymentMethodKey . '_sort_order'])) {
			$data[$this->prefixPaymentMethodKey . '_sort_order'] = $this->request->post[$this->prefixPaymentMethodKey . '_sort_order'];
		} else {
			$data[$this->prefixPaymentMethodKey . '_sort_order'] = $this->config->get($this->prefixPaymentMethodKey . '_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view($this->extPath, $data));
	}
	
	
	public function country_autocomplete() {
	    $json = array();
	    
	    if (isset($this->request->get['filter_name'])) {
	        $this->load->model($this->extPath);
	        
	        $filter_data = array(
	            'filter_name' => $this->request->get['filter_name'],
	            'sort'        => 'name',
	            'order'       => 'ASC',
	            'start'       => 0,
	            'limit'       => 10
	        );
	        
	        $results = $this->model_extension_payment_pointcheckout_helper->getCountries($filter_data);
	        
	        foreach ($results as $result) {
	            $json[] = array(
	                'country_id' => $result['country_id'],
	                'name'        => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8'))
	            );
	        }
	    }
	    
	    $sort_order = array();
	    
	    foreach ($json as $key => $value) {
	        $sort_order[$key] = $value['name'];
	    }
	    
	    array_multisort($sort_order, SORT_ASC, $json);
	    
	    $this->response->addHeader('Content-Type: application/json');
	    $this->response->setOutput(json_encode($json));
	}
	
	public function user_group_autocomplete() {
	    $json = array();
	    
	    if (isset($this->request->get['filter_name'])) {
	        $this->load->model($this->extPath);
	        
	        $filter_data = array(
	            'filter_name' => $this->request->get['filter_name'],
	            'sort'        => 'cgd.name',
	            'order'       => 'ASC',
	            'start'       => 0,
	            'limit'       => 10
	        );
	        
	        $results = $this->model_extension_payment_pointcheckout_helper->getUserGroups($filter_data);
	        
	        foreach ($results as $result) {
	            $json[] = array(
	                'group_id' => $result['customer_group_id'],
	                'name'        => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8'))
	            );
	        }
	    }
	    
	    $sort_order = array();
	    
	    foreach ($json as $key => $value) {
	        $sort_order[$key] = $value['name'];
	    }
	    
	    array_multisort($sort_order, SORT_ASC, $json);
	    
	    $this->response->addHeader('Content-Type: application/json');
	    $this->response->setOutput(json_encode($json));
	}
	

	protected function validate() {
		if (!$this->user->hasPermission('modify', $this->extPath)) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post[$this->prefixPaymentMethodKey . '_key']) {
			$this->error['key'] = $this->language->get('error_key');
		}

		if (!$this->request->post[$this->prefixPaymentMethodKey . '_secret']) {
			$this->error['secret'] = $this->language->get('error_secret');
		}
		
		if($this->request->post[$this->prefixPaymentMethodKey . '_applicable_usergroups'] && !isset($this->request->post[$this->prefixPaymentMethodKey . '_user_group'])){
		    $this->error['user_group']=$this->language->get('error_user_group');
		}
		
		if($this->request->post[$this->prefixPaymentMethodKey . '_applicable_countries'] && !isset($this->request->post[$this->prefixPaymentMethodKey . '_country'])){
		    $this->error['specific_countries']=$this->language->get('error_specific_country');
		}

		return !$this->error;
	}
}