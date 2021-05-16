<?php
class ControllerExtensionPaymentPointCheckoutCard extends Controller
{
    const PC_EXT_VERSION = "OpenCart-Card-2.0.3";
    const PMT = '_card';
    const API_VER = 'v1.2';
    const PAYMENT_METHOD = 'CARD';

    private $prefixPaymentMethodKey = 'payment_pointcheckout' . self::PMT;

    public function index()
    {
        $this->load->language('extension/payment/pointcheckout' . self::PMT);
        return $this->load->view('extension/payment/pointcheckout' . self::PMT);
    }

    //Stage 1 Sending data to pointcheckout and redirect user to paymet page if success
    public function send()
    {
        return $this->sendOrder(true);
    }

    private function sendOrder($retry)
    {
        $this->load->model('checkout/order');
        $data = array_change_key_case($this->session->data, CASE_LOWER);

        $order_info = $this->model_checkout_order->getOrder($data['order_id']);
        $_BASE_URL = $this->getApiBaseUrl();
        $headers = array(
            'Content-Type: application/json',
            'X-PointCheckout-Api-Key:' . $this->config->get($this->prefixPaymentMethodKey . '_key'),
            'X-PointCheckout-Api-Secret:' . $this->config->get($this->prefixPaymentMethodKey . '_secret'),
        );

        $products = $this->model_checkout_order->getOrderProducts($data['order_id']);

        $json = array();
        $storeOrder = array();
        $storeOrder['transactionId'] = $order_info['order_id'];
        $storeOrder['currency'] = $order_info['currency_code'];
        $storeOrder['paymentMethods'] = [self::PAYMENT_METHOD];
        $storeOrder['resultUrl'] = $order_info['store_url'] . 'index.php?route=extension/payment/pointcheckout' . self::PMT . '/confirm';

        $storeOrder['extVersion'] = self::PC_EXT_VERSION;
        $storeOrder['ecommerce'] = 'OpenCart ' . VERSION;

        //calculating totals
        //looping totals and store data in our storeOrder
        $order_totals = $this->model_checkout_order->getOrderTotals($data['order_id']);
        foreach ($order_totals as $total) {
            switch ($total['code']) {
                case 'sub_total':
                    $storeOrder['subtotal'] = $this->currency->format($total['value'], $data['currency'], '', false);
                    break;
                case 'shipping':
                    $shipping = $this->currency->format($total['value'], $data['currency'], '', false);
                    //in case more than one shipping charges are there 
                    if (isset($storeOrder['shipping'])) {
                        $storeOrder['shipping'] += $shipping;
                    } else {
                        $storeOrder['shipping'] = $shipping;
                    }
                    break;
                case 'tax':
                    $tax = $this->currency->format($total['value'], $data['currency'], '', false);
                    //in case more than one tax charges are there
                    if (isset($storeOrder['tax'])) {
                        $storeOrder['tax'] += $tax;
                    } else {
                        $storeOrder['tax'] = $tax;
                    }
                    break;
                case 'discount':
                    $discount = $this->currency->format(($total['value']), $data['currency'], '', false);
                    //in case more than one discount charges are there
                    if (isset($storeOrder['discount'])) {
                        $storeOrder['discount'] +=  $discount;
                    } else {
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
        foreach ($products as $product) {
            $item = (object) array(
                'name' => $product['name'],
                'sku' => $product['product_id'],
                'quantity' => $product['quantity'],
                'total' => $this->currency->format($product['price'] * $product['quantity'], $data['currency'], '', false)
            );
            $items[$i++] = $item;
        }
        $storeOrder['items'] = array_values($items);

        //prepare customer Information
        $customer = array();
        if ($order_info['customer_id'] !== '' && $order_info['customer_id'] != 0) {
            $customer['id'] = $order_info['customer_id'];
        }
        if (trim($order_info['firstname']) !== '') {
            $customer['firstName'] = $order_info['firstname'];
            $customer['lastName'] = $order_info['lastname'];
        } else {
            $customer['firstName'] = $order_info['payment_firstname'];
            $customer['lastName'] = $order_info['payment_lastname'];
        }
        $customer['email'] = $order_info['email'];
        $customer['phone'] = $order_info['telephone'];


        $billingAddress = array();
        $billingAddress['name'] = $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];
        $billingAddress['address1'] = $order_info['payment_address_1'];
        $billingAddress['address2'] = $order_info['payment_address_2'];
        $billingAddress['city'] = $order_info['payment_city'];
        $billingAddress['state'] = $order_info['payment_zone'];
        $billingAddress['country'] = $order_info['payment_country'];
        $billingAddress['zip'] = $order_info['payment_postcode'];

        $shippingAddress = array();
        $shippingAddress['name'] = $order_info['shipping_firstname'] . ' ' . $order_info['shipping_lastname'];
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
        $old_value = ini_get('serialize_precision');
        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set('serialize_precision', -1);
        }
        //convert storeOrder array to json format object
        $storeOrder = json_encode($storeOrder);
        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set('serialize_precision', $old_value);
        }
        //open http connection
        $curl = curl_init($_BASE_URL . '/checkouts');

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $storeOrder);
        //sending request
        $response = curl_exec($curl);
        //close connection
        curl_close($curl);

        //alert error if response is failure
        if (!$response) {
            $json['error'] = 'Error Connecting to PointCheckout - Please Try again later';
        } else {
            $response_info = json_decode($response);
            //prepare response to pointcheckout payment tag ajax request
            if ($response_info->success == 'true') {
                $message = '';
                if (isset($response_info->result)) {
                    $resultData = $response_info->result;
                    if (isset($resultData->checkoutId)) {
                        $message .= $this->getPointCheckoutOrderHistoryMessage($resultData->checkoutId, 0, $resultData->status);
                        $data['checkoutId'] = $resultData->checkoutId;
                    }
                    $this->model_checkout_order->addOrderHistory($data['order_id'], $this->config->get($this->prefixPaymentMethodKey . '_order_status_id'), $message, false);
                }
                $json['success'] = $resultData->redirectUrl;
            } else {
                $json['error'] = $response_info->error;
                $message = 'PointCheckout API Error error : ' . $response_info->error;

                $this->log->write($message);
                if ($retry && strpos($response_info->error, "checkout already exists for this merchant with")) {
                    // non-pending checkout already exists with the same order_id, mark order as failed and create new order
                    $this->model_checkout_order->addOrderHistory($data['order_id'], $this->config->get($this->prefixPaymentMethodKey . '_payment_failed_status_id'), $message, false);
                    $this->createNewOrder();

                    // try payment again, if failed dont retry
                    return $this->sendOrder(false);
                }
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

    public function confirm()
    {
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($_REQUEST['reference']);

        $_BASE_URL = $this->getApiBaseUrl();
        $headers = array(
            'Content-Type: application/json',
            'X-PointCheckout-Api-Key:' . $this->config->get($this->prefixPaymentMethodKey . '_key'),
            'X-PointCheckout-Api-Secret:' . $this->config->get($this->prefixPaymentMethodKey . '_secret'),
        );
        $curl = curl_init($_BASE_URL . '/checkouts/' . $_REQUEST['checkout']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);

        if (!$response) {
            $this->log->write('[ERROR] connection error: ' . curl_error($curl) . '(' . curl_errno($curl) . ')');
            curl_close($curl);
            $message = 'Error Connecting to PointCheckout - Payment Failed Please see log for details ';
            $this->forwardFailure($message, $_REQUEST['reference']);
        }
        curl_close($curl);
        $message = '';
        $response_info = json_decode($response);
        //check response and redirect user to either success or failure page
        if ($response_info->success == 'true' && $response_info->result->status == 'PAID') {
            $message .= $this->getPointCheckoutOrderHistoryMessage($_REQUEST['checkout'], $response_info->result->cash, $response_info->result->status);
            $this->forwardSuccess($message, $_REQUEST['reference']);
        } elseif (!$response_info->success == 'true') {
            $message .= 'Error Connecting to PointCheckout - Payment Failed Please see log for details ';
            $this->log->write('[ERROR] PointCheckout response with error - payment failed   error msg is :' . $response_info->error);
            $this->forwardFailure($message, $_REQUEST['reference']);
        } else {
            $message .= $this->getPointCheckoutOrderHistoryMessage($_REQUEST['checkout'], 0, $response_info->result->status);
            $this->forwardFailure($message, $_REQUEST['reference']);
        }
    }

    private function forwardFailure($message, $currentOrderId)
    {
        $this->load->model('checkout/order');
        $this->model_checkout_order->addOrderHistory($currentOrderId, $this->config->get($this->prefixPaymentMethodKey . '_payment_failed_status_id'), $message, false);
        $failureurl = $this->url->link('checkout/failure');
        ob_start();
        header('Location: ' . $failureurl);
        ob_end_flush();
        die();
    }

    private function forwardSuccess($message, $currentOrderId)
    {
        $this->load->model('checkout/order');
        $this->session->data['order_id'] = $currentOrderId;
        $this->model_checkout_order->addOrderHistory($currentOrderId, $this->config->get($this->prefixPaymentMethodKey . '_payment_success_status_id'), $message, false);
        $successurl = $this->url->link('checkout/success');
        ob_start();
        header('Location: ' . $successurl);
        ob_end_flush();
        die();
    }

    private function getPointCheckoutOrderHistoryMessage($checkout, $codAmount, $orderStatus)
    {
        switch ($orderStatus) {
            case 'PAID':
                $color = 'style="color:green;"';
                break;
            case 'PENDING':
                $color = 'style="color:BLUE;"';
                break;
            default:
                $color = 'style="color:RED;"';
        }
        $message = 'PointCheckout Status: <b ' . $color . '>' . $orderStatus . '</b><br/>PointCheckout Transaction ID: <a href="' . $this->getAdminUrl() . '/merchant/transactions/' . $checkout . '/read " target="_blank"><b>' . $checkout . '</b></a>' . "\n";
        if ($codAmount > 0) {
            $data = array_change_key_case($this->session->data, CASE_LOWER);
            $message .= '<b style="color:red;">[NOTICE] </b><i>COD Amount: <b>' . $codAmount . ' ' . $data['currency'] . '</b></i>' . "\n";
        }

        return $message;
    }
    private function getAdminUrl()
    {
        if ($this->config->get($this->prefixPaymentMethodKey . '_env') == '2') {
            $_ADMIN_URL = 'https://admin.staging.pointcheckout.com';
        } elseif ($this->config->get($this->prefixPaymentMethodKey . '_env') == '0') {
            $_ADMIN_URL = 'https://admin.pointcheckout.com';
        } else {
            $_ADMIN_URL = 'https://admin.test.pointcheckout.com';
        }
        return $_ADMIN_URL;
    }

    private function getApiBaseUrl()
    {
        if ($this->config->get($this->prefixPaymentMethodKey . '_env') == '2') {
            $_BASE_URL = 'https://api.staging.pointcheckout.com/mer/' . self::API_VER;
        } elseif ($this->config->get($this->prefixPaymentMethodKey . '_env') == '0') {
            $_BASE_URL = 'https://api.pointcheckout.com/mer/' . self::API_VER;
        } else {
            $_BASE_URL = 'https://api.test.pointcheckout.com/mer/' . self::API_VER;
        }
        return $_BASE_URL;
    }

    /** 
     * see /catalog/controller/checkout/confirm.php
     */
    private function createNewOrder()
    {

        $order_data = array();

        $totals = array();
        $taxes = $this->cart->getTaxes();
        $total = 0;

        // Because __call can not keep var references so we put them into an array.
        $total_data = array(
            'totals' => &$totals,
            'taxes'  => &$taxes,
            'total'  => &$total
        );

        $this->load->model('setting/extension');

        $sort_order = array();

        $results = $this->model_setting_extension->getExtensions('total');

        foreach ($results as $key => $value) {
            $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
        }

        array_multisort($sort_order, SORT_ASC, $results);

        foreach ($results as $result) {
            if ($this->config->get('total_' . $result['code'] . '_status')) {
                $this->load->model('extension/total/' . $result['code']);

                // We have to put the totals in an array so that they pass by reference.
                $this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
            }
        }

        $sort_order = array();

        foreach ($totals as $key => $value) {
            $sort_order[$key] = $value['sort_order'];
        }

        array_multisort($sort_order, SORT_ASC, $totals);

        $order_data['totals'] = $totals;

        $this->load->language('checkout/checkout');

        $order_data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
        $order_data['store_id'] = $this->config->get('config_store_id');
        $order_data['store_name'] = $this->config->get('config_name');

        if ($order_data['store_id']) {
            $order_data['store_url'] = $this->config->get('config_url');
        } else {
            if ($this->request->server['HTTPS']) {
                $order_data['store_url'] = HTTPS_SERVER;
            } else {
                $order_data['store_url'] = HTTP_SERVER;
            }
        }

        $this->load->model('account/customer');

        if ($this->customer->isLogged()) {
            $customer_info = $this->model_account_customer->getCustomer($this->customer->getId());

            $order_data['customer_id'] = $this->customer->getId();
            $order_data['customer_group_id'] = $customer_info['customer_group_id'];
            $order_data['firstname'] = $customer_info['firstname'];
            $order_data['lastname'] = $customer_info['lastname'];
            $order_data['email'] = $customer_info['email'];
            $order_data['telephone'] = $customer_info['telephone'];
            $order_data['custom_field'] = json_decode($customer_info['custom_field'], true);
        } elseif (isset($this->session->data['guest'])) {
            $order_data['customer_id'] = 0;
            $order_data['customer_group_id'] = $this->session->data['guest']['customer_group_id'];
            $order_data['firstname'] = $this->session->data['guest']['firstname'];
            $order_data['lastname'] = $this->session->data['guest']['lastname'];
            $order_data['email'] = $this->session->data['guest']['email'];
            $order_data['telephone'] = $this->session->data['guest']['telephone'];
            $order_data['custom_field'] = $this->session->data['guest']['custom_field'];
        }

        $order_data['payment_firstname'] = $this->session->data['payment_address']['firstname'];
        $order_data['payment_lastname'] = $this->session->data['payment_address']['lastname'];
        $order_data['payment_company'] = $this->session->data['payment_address']['company'];
        $order_data['payment_address_1'] = $this->session->data['payment_address']['address_1'];
        $order_data['payment_address_2'] = $this->session->data['payment_address']['address_2'];
        $order_data['payment_city'] = $this->session->data['payment_address']['city'];
        $order_data['payment_postcode'] = $this->session->data['payment_address']['postcode'];
        $order_data['payment_zone'] = $this->session->data['payment_address']['zone'];
        $order_data['payment_zone_id'] = $this->session->data['payment_address']['zone_id'];
        $order_data['payment_country'] = $this->session->data['payment_address']['country'];
        $order_data['payment_country_id'] = $this->session->data['payment_address']['country_id'];
        $order_data['payment_address_format'] = $this->session->data['payment_address']['address_format'];
        $order_data['payment_custom_field'] = (isset($this->session->data['payment_address']['custom_field']) ? $this->session->data['payment_address']['custom_field'] : array());

        if (isset($this->session->data['payment_method']['title'])) {
            $order_data['payment_method'] = $this->session->data['payment_method']['title'];
        } else {
            $order_data['payment_method'] = '';
        }

        if (isset($this->session->data['payment_method']['code'])) {
            $order_data['payment_code'] = $this->session->data['payment_method']['code'];
        } else {
            $order_data['payment_code'] = '';
        }

        if ($this->cart->hasShipping()) {
            $order_data['shipping_firstname'] = $this->session->data['shipping_address']['firstname'];
            $order_data['shipping_lastname'] = $this->session->data['shipping_address']['lastname'];
            $order_data['shipping_company'] = $this->session->data['shipping_address']['company'];
            $order_data['shipping_address_1'] = $this->session->data['shipping_address']['address_1'];
            $order_data['shipping_address_2'] = $this->session->data['shipping_address']['address_2'];
            $order_data['shipping_city'] = $this->session->data['shipping_address']['city'];
            $order_data['shipping_postcode'] = $this->session->data['shipping_address']['postcode'];
            $order_data['shipping_zone'] = $this->session->data['shipping_address']['zone'];
            $order_data['shipping_zone_id'] = $this->session->data['shipping_address']['zone_id'];
            $order_data['shipping_country'] = $this->session->data['shipping_address']['country'];
            $order_data['shipping_country_id'] = $this->session->data['shipping_address']['country_id'];
            $order_data['shipping_address_format'] = $this->session->data['shipping_address']['address_format'];
            $order_data['shipping_custom_field'] = (isset($this->session->data['shipping_address']['custom_field']) ? $this->session->data['shipping_address']['custom_field'] : array());

            if (isset($this->session->data['shipping_method']['title'])) {
                $order_data['shipping_method'] = $this->session->data['shipping_method']['title'];
            } else {
                $order_data['shipping_method'] = '';
            }

            if (isset($this->session->data['shipping_method']['code'])) {
                $order_data['shipping_code'] = $this->session->data['shipping_method']['code'];
            } else {
                $order_data['shipping_code'] = '';
            }
        } else {
            $order_data['shipping_firstname'] = '';
            $order_data['shipping_lastname'] = '';
            $order_data['shipping_company'] = '';
            $order_data['shipping_address_1'] = '';
            $order_data['shipping_address_2'] = '';
            $order_data['shipping_city'] = '';
            $order_data['shipping_postcode'] = '';
            $order_data['shipping_zone'] = '';
            $order_data['shipping_zone_id'] = '';
            $order_data['shipping_country'] = '';
            $order_data['shipping_country_id'] = '';
            $order_data['shipping_address_format'] = '';
            $order_data['shipping_custom_field'] = array();
            $order_data['shipping_method'] = '';
            $order_data['shipping_code'] = '';
        }

        $order_data['products'] = array();

        foreach ($this->cart->getProducts() as $product) {
            $option_data = array();

            foreach ($product['option'] as $option) {
                $option_data[] = array(
                    'product_option_id'       => $option['product_option_id'],
                    'product_option_value_id' => $option['product_option_value_id'],
                    'option_id'               => $option['option_id'],
                    'option_value_id'         => $option['option_value_id'],
                    'name'                    => $option['name'],
                    'value'                   => $option['value'],
                    'type'                    => $option['type']
                );
            }

            $order_data['products'][] = array(
                'product_id' => $product['product_id'],
                'name'       => $product['name'],
                'model'      => $product['model'],
                'option'     => $option_data,
                'download'   => $product['download'],
                'quantity'   => $product['quantity'],
                'subtract'   => $product['subtract'],
                'price'      => $product['price'],
                'total'      => $product['total'],
                'tax'        => $this->tax->getTax($product['price'], $product['tax_class_id']),
                'reward'     => $product['reward']
            );
        }

        // Gift Voucher
        $order_data['vouchers'] = array();

        if (!empty($this->session->data['vouchers'])) {
            foreach ($this->session->data['vouchers'] as $voucher) {
                $order_data['vouchers'][] = array(
                    'description'      => $voucher['description'],
                    'code'             => token(10),
                    'to_name'          => $voucher['to_name'],
                    'to_email'         => $voucher['to_email'],
                    'from_name'        => $voucher['from_name'],
                    'from_email'       => $voucher['from_email'],
                    'voucher_theme_id' => $voucher['voucher_theme_id'],
                    'message'          => $voucher['message'],
                    'amount'           => $voucher['amount']
                );
            }
        }

        $order_data['comment'] = $this->session->data['comment'];
        $order_data['total'] = $total_data['total'];

        if (isset($this->request->cookie['tracking'])) {
            $order_data['tracking'] = $this->request->cookie['tracking'];

            $subtotal = $this->cart->getSubTotal();

            // Affiliate
            $affiliate_info = $this->model_account_customer->getAffiliateByTracking($this->request->cookie['tracking']);

            if ($affiliate_info) {
                $order_data['affiliate_id'] = $affiliate_info['customer_id'];
                $order_data['commission'] = ($subtotal / 100) * $affiliate_info['commission'];
            } else {
                $order_data['affiliate_id'] = 0;
                $order_data['commission'] = 0;
            }

            // Marketing
            $this->load->model('checkout/marketing');

            $marketing_info = $this->model_checkout_marketing->getMarketingByCode($this->request->cookie['tracking']);

            if ($marketing_info) {
                $order_data['marketing_id'] = $marketing_info['marketing_id'];
            } else {
                $order_data['marketing_id'] = 0;
            }
        } else {
            $order_data['affiliate_id'] = 0;
            $order_data['commission'] = 0;
            $order_data['marketing_id'] = 0;
            $order_data['tracking'] = '';
        }

        $order_data['language_id'] = $this->config->get('config_language_id');
        $order_data['currency_id'] = $this->currency->getId($this->session->data['currency']);
        $order_data['currency_code'] = $this->session->data['currency'];
        $order_data['currency_value'] = $this->currency->getValue($this->session->data['currency']);
        $order_data['ip'] = $this->request->server['REMOTE_ADDR'];

        if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
            $order_data['forwarded_ip'] = $this->request->server['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($this->request->server['HTTP_CLIENT_IP'])) {
            $order_data['forwarded_ip'] = $this->request->server['HTTP_CLIENT_IP'];
        } else {
            $order_data['forwarded_ip'] = '';
        }

        if (isset($this->request->server['HTTP_USER_AGENT'])) {
            $order_data['user_agent'] = $this->request->server['HTTP_USER_AGENT'];
        } else {
            $order_data['user_agent'] = '';
        }

        if (isset($this->request->server['HTTP_ACCEPT_LANGUAGE'])) {
            $order_data['accept_language'] = $this->request->server['HTTP_ACCEPT_LANGUAGE'];
        } else {
            $order_data['accept_language'] = '';
        }

        $this->load->model('checkout/order');

        $this->session->data['order_id'] = $this->model_checkout_order->addOrder($order_data);

        $this->load->model('tool/upload');

        $data['products'] = array();

        foreach ($this->cart->getProducts() as $product) {
            $option_data = array();

            foreach ($product['option'] as $option) {
                if ($option['type'] != 'file') {
                    $value = $option['value'];
                } else {
                    $upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

                    if ($upload_info) {
                        $value = $upload_info['name'];
                    } else {
                        $value = '';
                    }
                }

                $option_data[] = array(
                    'name'  => $option['name'],
                    'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
                );
            }

            $recurring = '';

            if ($product['recurring']) {
                $frequencies = array(
                    'day'        => $this->language->get('text_day'),
                    'week'       => $this->language->get('text_week'),
                    'semi_month' => $this->language->get('text_semi_month'),
                    'month'      => $this->language->get('text_month'),
                    'year'       => $this->language->get('text_year'),
                );

                if ($product['recurring']['trial']) {
                    $recurring = sprintf($this->language->get('text_trial_description'), $this->currency->format($this->tax->calculate($product['recurring']['trial_price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']), $product['recurring']['trial_cycle'], $frequencies[$product['recurring']['trial_frequency']], $product['recurring']['trial_duration']) . ' ';
                }

                if ($product['recurring']['duration']) {
                    $recurring .= sprintf($this->language->get('text_payment_description'), $this->currency->format($this->tax->calculate($product['recurring']['price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']), $product['recurring']['cycle'], $frequencies[$product['recurring']['frequency']], $product['recurring']['duration']);
                } else {
                    $recurring .= sprintf($this->language->get('text_payment_cancel'), $this->currency->format($this->tax->calculate($product['recurring']['price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']), $product['recurring']['cycle'], $frequencies[$product['recurring']['frequency']], $product['recurring']['duration']);
                }
            }

            $data['products'][] = array(
                'cart_id'    => $product['cart_id'],
                'product_id' => $product['product_id'],
                'name'       => $product['name'],
                'model'      => $product['model'],
                'option'     => $option_data,
                'recurring'  => $recurring,
                'quantity'   => $product['quantity'],
                'subtract'   => $product['subtract'],
                'price'      => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
                'total'      => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')) * $product['quantity'], $this->session->data['currency']),
                'href'       => $this->url->link('product/product', 'product_id=' . $product['product_id'])
            );
        }

        // Gift Voucher
        $data['vouchers'] = array();

        if (!empty($this->session->data['vouchers'])) {
            foreach ($this->session->data['vouchers'] as $voucher) {
                $data['vouchers'][] = array(
                    'description' => $voucher['description'],
                    'amount'      => $this->currency->format($voucher['amount'], $this->session->data['currency'])
                );
            }
        }

        $data['totals'] = array();

        foreach ($order_data['totals'] as $total) {
            $data['totals'][] = array(
                'title' => $total['title'],
                'text'  => $this->currency->format($total['value'], $this->session->data['currency'])
            );
        }

        $data['payment'] = $this->load->controller('extension/payment/' . $this->session->data['payment_method']['code']);
    }
}
