<?php
class ControllerExtensionPaymentPointCheckoutCard extends Controller {
    const PC_EXT_VERSION = "OpenCart-Card-2.0.0";
    const PMT = '_card';
    const API_VER = 'v1.2';
    const PAYMENT_METHOD = 'CARD';

    private $prefixPaymentMethodKey = 'payment_pointcheckout' . self::PMT;

    public function index() {
        $this->load->language('extension/payment/pointcheckout' . self::PMT);
        return $this->load->view('extension/payment/pointcheckout' . self::PMT);
    }
    
    //Stage 1 Sending data to pointcheckout and redirect user to paymet page if success
    
    public function send() {
        $this->load->model('checkout/order');
        $data = array_change_key_case($this->session->data, CASE_LOWER);

        $order_info = $this->model_checkout_order->getOrder($data['order_id']);
        $_BASE_URL=$this->getApiBaseUrl();
            $headers = array(
                'Content-Type: application/json',
                'X-PointCheckout-Api-Key:'.$this->config->get($this->prefixPaymentMethodKey . '_key'),
                'X-PointCheckout-Api-Secret:'.$this->config->get($this->prefixPaymentMethodKey . '_secret'),
            );
            
            $products= $this->model_checkout_order->getOrderProducts($data['order_id']);

            $json = array();
            $storeOrder = array();
            $storeOrder['transactionId'] = $order_info['order_id'];
            $storeOrder['currency'] = $order_info['currency_code'];
            $storeOrder['paymentMethods'] = [self::PAYMENT_METHOD];
            $storeOrder['resultUrl'] = $order_info['store_url'].'index.php?route=extension/payment/pointcheckout' . self::PMT . '/confirm';

            $storeOrder['extVersion'] = self::PC_EXT_VERSION;
            $storeOrder['ecommerce'] = 'OpenCart ' . VERSION;

            //calculating totals
            //looping totals and store data in our storeOrder
            $order_totals=$this->model_checkout_order->getOrderTotals($data['order_id']);
            foreach ($order_totals as $total) {
                switch( $total['code']){
                    case 'sub_total':
                        $storeOrder['subtotal'] = $this->currency->format($total['value'], $data['currency'], '', false);
                        break;
                    case 'shipping':
                        $shipping = $this->currency->format($total['value'], $data['currency'], '', false);
                        //in case more than one shipping charges are there 
                        if(isset($storeOrder['shipping'])){
                            $storeOrder['shipping'] += $shipping;
                        }else{
                            $storeOrder['shipping'] = $shipping;
                        }
                        break;
                    case 'tax':
                        $tax = $this->currency->format($total['value'], $data['currency'], '', false);
                        //in case more than one tax charges are there
                        if(isset($storeOrder['tax'])){
                            $storeOrder['tax'] += $tax;
                        }else{
                            $storeOrder['tax'] = $tax;
                        }
                        break;
                    case 'discount':
                        $discount = $this->currency->format(($total['value']), $data['currency'], '', false);
                        //in case more than one discount charges are there
                        if(isset($storeOrder['discount'])){
                            $storeOrder['discount'] +=  $discount;
                        }else{
                            $storeOrder['discount'] =  $discount;
                        }
                        break;
                    case 'total':
                        $storeOrder['amount'] = $this->currency->format($total['value'], $data['currency'], '', false);
                        break;
                    default:
                        $storeOrder[$total['code']] = $this->currency->format($total['value'], $data['currency'], '', false);
                }
            }
            
            // items
            $items = array();
            $i = 0;
            foreach ($products as $product){
                $item = (object) array(
                    'name'=> $product['name'],
                    'sku' => $product['product_id'],
                    'quantity' => $product['quantity'],
                    'total' =>$this->currency->format($product['price']*$product['quantity'], $data['currency'], '', false));
                $items[$i++] = $item;
            }
            $storeOrder['items'] = array_values($items);

            //prepare customer Information
            $customer = array();
            if($order_info['customer_id'] !== '' && $order_info['customer_id'] != 0) {
                $customer['id'] = $order_info['customer_id'];
            }
            if(trim($order_info['firstname']) !== '') {
                $customer['firstName'] = $order_info['firstname'];
                $customer['lastName'] = $order_info['lastname'];
            } else {
                $customer['firstName'] = $order_info['payment_firstname'];
                $customer['lastName'] = $order_info['payment_lastname'];
            }
            $customer['email'] = $order_info['email'];
            $customer['phone'] = $order_info['telephone'];

            
            $billingAddress = array();
            $billingAddress['name'] = $order_info['payment_firstname'].' '.$order_info['payment_lastname'];
            $billingAddress['address1'] = $order_info['payment_address_1'];
            $billingAddress['address2'] = $order_info['payment_address_2'];
            $billingAddress['city'] = $order_info['payment_city'];
            $billingAddress['state'] = $order_info['payment_zone'];
            $billingAddress['country'] = $order_info['payment_country'];
            $billingAddress['zip'] = $order_info['payment_postcode'];

            $shippingAddress = array();
            $shippingAddress['name'] = $order_info['shipping_firstname'].' '.$order_info['shipping_lastname'];
            $shippingAddress['address1'] = $order_info['shipping_address_1'];
            $shippingAddress['address2'] = $order_info['shipping_address_2'];
            $shippingAddress['city'] = $order_info['shipping_city'];
            $shippingAddress['state'] = $order_info['shipping_zone'];
            $shippingAddress['country'] = $order_info['shipping_country'];
            $shippingAddress['zip'] = $order_info['shipping_postcode'];
            
            $customer['billingAddress'] = $billingAddress;
            $customer['shippingAddress'] = $shippingAddress;
            
            $storeOrder['customer'] = $customer;
            
            //check php version and if 7.1 or above set ini value -serialize_precision- to -1 to avoid two many decimal places
            //known problem in json_encode method since php7.1
            $old_value = ini_get( 'serialize_precision');
            if (version_compare(phpversion(), '7.1', '>=')) {
                ini_set( 'serialize_precision', -1 );
            }
            //convert storeOrder array to json format object
            $storeOrder = json_encode($storeOrder);
            if (version_compare(phpversion(), '7.1', '>=')) {
                ini_set( 'serialize_precision', $old_value  );
            }
            //open http connection
            $curl = curl_init($_BASE_URL.'/checkouts');
            
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $storeOrder);
            //sending request
            $response = curl_exec($curl);
            //close connection
            curl_close($curl);
            
            //alert error if response is failure
            if (!$response) {
                $json['error']='Error Connecting to PointCheckout - Please Try again later';
            }else{
                $response_info = json_decode($response);
                //prepare response to pointcheckout payment tag ajax request
                if ($response_info->success == 'true') {
                    $message = '';
                    if (isset($response_info->result)) {
                        $resultData = $response_info->result;
                        if (isset($resultData->checkoutId)){
                            $message.=$this->getPointCheckoutOrderHistoryMessage($resultData->checkoutId,0,$resultData->status);
                            $data['checkoutId']=$resultData->checkoutId;
                        }
                        $this->model_checkout_order->addOrderHistory($data['order_id'], $this->config->get($this->prefixPaymentMethodKey . '_order_status_id'), $message, false);
                    }
                    $json['success'] = $resultData->redirectUrl;
                } else {
                    $json['error'] = $response_info->error ;
                }
                //clear session data to prevent giving same order number in checkout
                unset($data['shipping_method']);
                unset($data['shipping_methods']);
                unset($data['payment_method']);
                unset($data['payment_methods']);
                unset($data['guest']);
                unset($data['comment']);
                unset($data['order_id']);
                unset($data['coupon']);
                unset($data['reward']);
                unset($data['voucher']);
                unset($data['vouchers']);
                unset($data['totals']);
            }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    //Stage 2 Finalize Payment after user return back from payment page either success or failure
    
    public function confirm() {
        
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($_REQUEST['reference']);
        
        $_BASE_URL=$this->getApiBaseUrl();
        $headers = array(
            'Content-Type: application/json',
            'X-PointCheckout-Api-Key:'.$this->config->get($this->prefixPaymentMethodKey . '_key'),
            'X-PointCheckout-Api-Secret:'.$this->config->get($this->prefixPaymentMethodKey . '_secret'),
        );
        $curl = curl_init($_BASE_URL.'/checkouts/'.$_REQUEST['checkout']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($curl);
        
        if (!$response) {
            $this->log->write('[ERROR] connection error: ' . curl_error($curl) . '(' . curl_errno($curl) . ')');
            curl_close($curl);
            $message ='Error Connecting to PointCheckout - Payment Failed Please see log for details ';
            $this->forwardFailure($message,$_REQUEST['reference']);
        }
        curl_close($curl);
        $message = '';
        $response_info = json_decode($response);
        //check response and redirect user to either success or failure page
        if ($response_info->success == 'true' && $response_info->result->status =='PAID') {
            $message.= $this->getPointCheckoutOrderHistoryMessage($_REQUEST['checkout'],$response_info->result->code,$response_info->result->status);
            $this->forwardSuccess($message,$_REQUEST['reference']);
        }elseif(!$response_info->success == 'true'){
            $message.='Error Connecting to PointCheckout - Payment Failed Please see log for details ';
            $this->log-write('[ERROR] PointCheckout response with error - payment failed   error msg is :' . $response_info->error);
            $this->forwardFailure($message,$_REQUEST['reference']);
        }else{
            $message.=$this->getPointCheckoutOrderHistoryMessage($_REQUEST['checkout'],0,$response_info->result->status);
            $this->forwardFailure($message,$_REQUEST['reference']);
        }
        
    }
    private function forwardFailure($message,$currentOrderId){
        $this->load->model('checkout/order');
        $this->model_checkout_order->addOrderHistory($currentOrderId, $this->config->get($this->prefixPaymentMethodKey . '_payment_failed_status_id'), $message, false);
        $failureurl = $this->url->link('checkout/failure');
        ob_start();
        header('Location: '.$failureurl);
        ob_end_flush();
        die();
    }
    
    private function forwardSuccess($message,$currentOrderId){
        $this->load->model('checkout/order');
        $this->session->data['order_id'] = $currentOrderId;
        $this->model_checkout_order->addOrderHistory($currentOrderId, $this->config->get($this->prefixPaymentMethodKey . '_payment_success_status_id'), $message, false);
        $successurl = $this->url->link('checkout/success');
        ob_start();
        header('Location: '.$successurl);
        ob_end_flush();
        die();
    }
    
    private function getPointCheckoutOrderHistoryMessage($checkout,$codAmount,$orderStatus) {
        switch($orderStatus){
            case 'PAID':
                $color='style="color:green;"';
                break;
            case 'PENDING':
                $color='style="color:BLUE;"';
                break;
            default:
                $color='style="color:RED;"';
        }
        $message = 'PointCheckout Status: <b '.$color.'>'.$orderStatus.'</b><br/>PointCheckout Transaction ID: <a href="'.$this->getAdminUrl().'/merchant/transactions/'.$checkout.'/read " target="_blank"><b>'.$checkout.'</b></a>'."\n" ;
        if($codAmount>0){
            $data = array_change_key_case($this->session->data, CASE_LOWER);
            $message.= '<b style="color:red;">[NOTICE] </b><i>COD Amount: <b>'.$codAmount.' '.$data['currency'].'</b></i>'."\n";
        }
        
        return $message;
    }
    private function getAdminUrl(){
        if ($this->config->get($this->prefixPaymentMethodKey . '_env') == '2'){
            $_ADMIN_URL='https://admin.staging.pointcheckout.com';
        } elseif($this->config->get($this->prefixPaymentMethodKey . '_env') == '0'){
            $_ADMIN_URL='https://admin.pointcheckout.com';
        } else {
            $_ADMIN_URL='https://admin.test.pointcheckout.com';
        }
        return $_ADMIN_URL;   
    }
    
    private function getApiBaseUrl(){
        if ($this->config->get($this->prefixPaymentMethodKey . '_env') == '2'){
            $_BASE_URL='https://api.staging.pointcheckout.com/mer/' . self::API_VER;
        } elseif ($this->config->get($this->prefixPaymentMethodKey . '_env') == '0'){
            $_BASE_URL='https://api.pointcheckout.com/mer/' . self::API_VER;
        } else {
            $_BASE_URL='https://api.test.pointcheckout.com/mer/' . self::API_VER;
        }
        return $_BASE_URL;
    }
}