<?php

/**
 *
 * @copyright  Ambientinfotech
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Ambientinfotech\Paytabsinfotech\Model;

use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;

class PaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod {

    protected $_code = 'Paytabsinfotech';
    protected $_isInitializeNeeded = true;

    /**
     * @var \Magento\Framework\Exception\LocalizedExceptionFactory
     */
    protected $_exception;

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    protected $_transactionRepository;

    /**
     * @var Transaction\BuilderInterface
     */
    protected $_transactionBuilder;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;
    protected $_countryHelper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;
    protected $adnlinfo;
    protected $title;
    protected $_session;

    /**
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\Exception\LocalizedExceptionFactory $exception
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param Transaction\BuilderInterface $transactionBuilder
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
    \Magento\Framework\UrlInterface $urlBuilder, \Magento\Framework\Exception\LocalizedExceptionFactory $exception, \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository, \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder, \Magento\Sales\Model\OrderFactory $orderFactory, \Magento\Store\Model\StoreManagerInterface $storeManager, \Magento\Framework\Model\Context $context, \Magento\Customer\Model\Session $session, \Magento\Framework\Registry $registry, \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory, \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory, \Magento\Payment\Helper\Data $paymentData, \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, \Magento\Payment\Model\Method\Logger $logger, \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null, \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null, array $data = []
    ) {
        $this->_urlBuilder = $urlBuilder;
        $this->_exception = $exception;
        $this->_transactionRepository = $transactionRepository;
        $this->_transactionBuilder = $transactionBuilder;
        $this->_orderFactory = $orderFactory;
        $this->_storeManager = $storeManager;
        $this->_session = $session;
        $this->_countryHelper = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Directory\Model\Country');
        parent::__construct(
                $context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $resource, $resourceCollection, $data
        );
    }

    /**
     * Instantiate state and set it to state object.
     *
     * @param string                        $paymentAction
     * @param \Magento\Framework\DataObject $stateObject
     */
    public function initialize($paymentAction, $stateObject) {
        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        $stateObject->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);
    }

    public function getPostHTML($order, $acuid, $storeId = null) {
        $merchantemail = $this->getConfigData('merchantemail');
        $secretkey = $this->getConfigData('secretkey');

        $returnUrl = self::getReturnUrl();

        $txnid = $order->getIncrementId();
        $amount = $order->getGrandTotal();
        $amount = number_format((float) $amount, 2, '.', '');
        $currency = $order->getOrderCurrencyCode();
        $billingAddress = $order->getBillingAddress();
        ;
        $firstName = $billingAddress->getFirstname();
        $lastName = $billingAddress->getLastname();
        $email = $billingAddress->getEmail();
        $street = '';
        $starr = $billingAddress->getStreet();
        if (isset($starr[0])) {
            $street = $starr[0];
        }
        $city = $billingAddress->getCity();
        $postcode = $billingAddress->getPostcode();
        $region = $billingAddress->getRegion();
        $country_iso2 = $billingAddress->getData('country_id');
        //$countryObj = $this->_countryHelper->loadByCode($country);
        //$country = $countryObj->getName();
        $telephone = $billingAddress->getTelephone();
        $title = $firstName . " " . $lastName;

        $cdetails = $this->getCountryDetails($country_iso2);
        $phoneext = $cdetails['phone'];

        $country = $this->countryGetiso3($country_iso2);

        $customerid = 0;
        if ($this->_session->isLoggedIn())
            $customerid = $this->_session->getCustomer()->getId();
       $discount_amount = abs($order->getDiscountAmount());

        $products = "";
        $per_price = "";
        $quantity = "";
        $categories = "-";
        $product_title = "";
        $items = $order->getAllItems();
        $cnt = 1;
        $sumofproductprices = 0;
        foreach ($items as $i) {
            if ($cnt > 1) {

                $products .= ' || ' . $i->getName();
                $quantity .= ' || ' . $i->getQtyOrdered();
                $per_price .= ' || ' . $i->getPrice();
                $product_title .= ', ' . $i->getName();
            } else {

                $products = $i->getName();
                $quantity = $i->getQtyOrdered();
                $per_price = $i->getPrice();
                $product_title = $i->getName();
            }
            $sumofproductprices += $i->getQtyToInvoice() * $i->getPrice();
            $cnt++;
        }

        $othercharges = $order->getGrandTotal() + $discount_amount - $sumofproductprices;
        $amount_to_sent = $sumofproductprices + $othercharges;

        $shippingAddress = $order->getShippingAddress();

        $shpstreet = '';
        $starr = $shippingAddress->getStreet();
        if (isset($starr[0])) {
            $shpstreet = $starr[0];
        }
        $shpcity = $shippingAddress->getCity();
        $shppostcode = $shippingAddress->getPostcode();
        $shpregion = $shippingAddress->getRegion();
        $shpcountry_iso2 = $shippingAddress->getData('country_id');

        $shippingMethod = $order->getShippingMethod();
        $shippingAmount = $order->getShippingAmount();

        $protocol = 'http://';
        if ($_SERVER['SERVER_PORT'] == 443) {
            $protocol = 'https://';
        }

        $lang = $this->_getLang();
        if (strlen($lang) > 2) {
            if (substr($lang, 0, 2) == 'en')
                $lang = "English";
            if (substr($lang, 0, 2) == 'ar')
                $lang = "Arabic";
        }

        $shipping_method = $order->getShippingMethod();

        $gateway_url = 'https://www.paytabs.com/apiv2/create_pay_page';

        //'currency' => strtoupper($currency->iso_code),
        //'amount' =>$total_product_ammout + $cart->getOrderTotal(true, Cart::ONLY_SHIPPING),
        //"discount"  =>  $discount,
        //'other_charges'    => $cart->getOrderTotal(true, Cart::ONLY_SHIPPING) ,
        // 'ShippingMethod' => $shippingMethod->getData('title'),
        // 'DeliveryType' => $shippingMethod->delay[1],
        $request_param = array(
            'merchant_email' => $merchantemail,
            'secret_key' => $secretkey,
            'cc_first_name' => $firstName,
            'cc_last_name' => $lastName,
            'phone_number' => $telephone,
            'cc_phone_number' => $phoneext,
            'billing_address' => $street,
            'city' => $city,
            'state' => $region,
            'postal_code' => $postcode,
            'country' => $country,
            'email' => $email,
            'amount' => $amount,
            'other_charges' => $othercharges,
            'discount' => $discount_amount,
            'currency' => $currency,
            'title' => $firstName . '  ' . $lastName,
            'quantity' => $quantity,
            "unit_price" => $per_price,
            "products_per_title" => $products,
            'ProductCategory' => $categories,
            'ChannelOfOperations' => 'ChannelOfOperations',
            'ProductName' => $product_title,
            'ShippingMethod' => $shipping_method,
            'DeliveryType' => 'normal',
            'address_shipping' => $shpstreet,
            'city_shipping' => $shpcity,
            'state_shipping' => $shpregion,
            'postal_code_shipping' => $shppostcode,
            'country_shipping' => $country,
            'ip_customer' => $_SERVER['REMOTE_ADDR'],
            'ip_merchant' => $_SERVER['SERVER_ADDR'],
            'cms_with_version' => 'Magento 2.x',
            'reference_no' => $txnid,
            'msg_lang' => $lang,
            'CustomerId' => $customerid,
            'is_recurrence_payments' => "TRUE",
            "recurrence_start_date" => "18/03/2020",
            "recurrence_frequency" => "24",
            "recurrence_billing_cycle" => "monthly",
            'site_url' => $protocol . $_SERVER['HTTP_HOST'],
            'return_url' => $returnUrl
        );
       $fields = $request_param;
        $fields_string = "";
        foreach ($fields as $key => $value) {
            $fields_string .= urlencode($key) . '=' . urlencode($value) . '&';
        }
        $fields_string = substr($fields_string, 0, strrpos($fields_string, '&'));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $gateway_url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $ch_result = curl_exec($ch);
        $ch_error = curl_error($ch);

        //curl_getinfo($ch);
        //curl_close($ch);
        $object = json_decode($ch_result, true);



        $retobj['payment_url'] = "";
        $retobj['result'] = $object['result'];
        $retobj['response_code'] = $object['response_code'];
        if (isset($object['payment_url']) && $object['payment_url'] != '') {
            $retobj['payment_url'] = $object['payment_url'];
        }
        //$this->_logger->addError("Ambientinfotech Generated HTML ".$html);
        //$this->_logger->addError("Generated Ambientinfotech checkout for order $txnid");

        return $retobj;
    }

    public function getOrderPlaceRedirectUrl($storeId = null) {
        return $this->_getUrl('pumcp/checkout/start', $storeId);
    }

    protected function addHiddenField($arr) {
        $nm = $arr['name'];
        $vl = $arr['value'];
        $input = "<input name='" . $nm . "' type='hidden' value='" . $vl . "' />";

        return $input;
    }

    /**
     * Get return URL.
     *
     * @param int|null $storeId
     *
     * @return string
     */
    //AA may not be required
    public function getSuccessUrl($storeId = null) {
        return $this->_getUrl('checkout/onepage/success', $storeId);
    }

    /**
     * Get notify (IPN) URL.
     *
     * @param int|null $storeId
     *
     * @return string
     */
    //AA Done
    public function getReturnUrl($storeId = null) {
        return $this->_getUrl('sadad/ipn/callback', $storeId, false);
    }

    /**
     * Get cancel URL.
     *
     * @param int|null $storeId
     *
     * @return string
     */
    //AA Not required
    public function getCancelUrl($storeId = null) {
        return $this->_getUrl('checkout/onepage/failure', $storeId);
    }

    /**
     * Build URL for store.
     *
     * @param string    $path
     * @param int       $storeId
     * @param bool|null $secure
     *
     * @return string
     */
    //AA Done
    protected function _getUrl($path, $storeId, $secure = null) {
        $store = $this->_storeManager->getStore($storeId);

        return $this->_urlBuilder->getUrl(
                        $path, ['_store' => $store, '_secure' => $secure === null ? $store->isCurrentlySecure() : $secure]
        );
    }

    public function _getLang() {
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $resolver = $om->get('Magento\Framework\Locale\Resolver');
        return $resolver->getLocale();
    }

    public function _sendRequest($gateway_url, $request_string) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $gateway_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $result = curl_exec($ch);
        if (!$result)
            die(curl_error($ch));

        curl_close($ch);

        return $result;
    }

    protected function getCountryDetails($iso_2) {
        $countryPhoneList = array(
            'AD' => array('name' => 'ANDORRA', 'code' => '376'),
            'AE' => array('name' => 'UNITED ARAB EMIRATES', 'code' => '971'),
            'AF' => array('name' => 'AFGHANISTAN', 'code' => '93'),
            'AG' => array('name' => 'ANTIGUA AND BARBUDA', 'code' => '1268'),
            'AI' => array('name' => 'ANGUILLA', 'code' => '1264'),
            'AL' => array('name' => 'ALBANIA', 'code' => '355'),
            'AM' => array('name' => 'ARMENIA', 'code' => '374'),
            'AN' => array('name' => 'NETHERLANDS ANTILLES', 'code' => '599'),
            'AO' => array('name' => 'ANGOLA', 'code' => '244'),
            'AQ' => array('name' => 'ANTARCTICA', 'code' => '672'),
            'AR' => array('name' => 'ARGENTINA', 'code' => '54'),
            'AS' => array('name' => 'AMERICAN SAMOA', 'code' => '1684'),
            'AT' => array('name' => 'AUSTRIA', 'code' => '43'),
            'AU' => array('name' => 'AUSTRALIA', 'code' => '61'),
            'AW' => array('name' => 'ARUBA', 'code' => '297'),
            'AZ' => array('name' => 'AZERBAIJAN', 'code' => '994'),
            'BA' => array('name' => 'BOSNIA AND HERZEGOVINA', 'code' => '387'),
            'BB' => array('name' => 'BARBADOS', 'code' => '1246'),
            'BD' => array('name' => 'BANGLADESH', 'code' => '880'),
            'BE' => array('name' => 'BELGIUM', 'code' => '32'),
            'BF' => array('name' => 'BURKINA FASO', 'code' => '226'),
            'BG' => array('name' => 'BULGARIA', 'code' => '359'),
            'BH' => array('name' => 'BAHRAIN', 'code' => '973'),
            'BI' => array('name' => 'BURUNDI', 'code' => '257'),
            'BJ' => array('name' => 'BENIN', 'code' => '229'),
            'BL' => array('name' => 'SAINT BARTHELEMY', 'code' => '590'),
            'BM' => array('name' => 'BERMUDA', 'code' => '1441'),
            'BN' => array('name' => 'BRUNEI DARUSSALAM', 'code' => '673'),
            'BO' => array('name' => 'BOLIVIA', 'code' => '591'),
            'BR' => array('name' => 'BRAZIL', 'code' => '55'),
            'BS' => array('name' => 'BAHAMAS', 'code' => '1242'),
            'BT' => array('name' => 'BHUTAN', 'code' => '975'),
            'BW' => array('name' => 'BOTSWANA', 'code' => '267'),
            'BY' => array('name' => 'BELARUS', 'code' => '375'),
            'BZ' => array('name' => 'BELIZE', 'code' => '501'),
            'CA' => array('name' => 'CANADA', 'code' => '1'),
            'CC' => array('name' => 'COCOS (KEELING) ISLANDS', 'code' => '61'),
            'CD' => array('name' => 'CONGO, THE DEMOCRATIC REPUBLIC OF THE', 'code' => '243'),
            'CF' => array('name' => 'CENTRAL AFRICAN REPUBLIC', 'code' => '236'),
            'CG' => array('name' => 'CONGO', 'code' => '242'),
            'CH' => array('name' => 'SWITZERLAND', 'code' => '41'),
            'CI' => array('name' => 'COTE D IVOIRE', 'code' => '225'),
            'CK' => array('name' => 'COOK ISLANDS', 'code' => '682'),
            'CL' => array('name' => 'CHILE', 'code' => '56'),
            'CM' => array('name' => 'CAMEROON', 'code' => '237'),
            'CN' => array('name' => 'CHINA', 'code' => '86'),
            'CO' => array('name' => 'COLOMBIA', 'code' => '57'),
            'CR' => array('name' => 'COSTA RICA', 'code' => '506'),
            'CU' => array('name' => 'CUBA', 'code' => '53'),
            'CV' => array('name' => 'CAPE VERDE', 'code' => '238'),
            'CX' => array('name' => 'CHRISTMAS ISLAND', 'code' => '61'),
            'CY' => array('name' => 'CYPRUS', 'code' => '357'),
            'CZ' => array('name' => 'CZECH REPUBLIC', 'code' => '420'),
            'DE' => array('name' => 'GERMANY', 'code' => '49'),
            'DJ' => array('name' => 'DJIBOUTI', 'code' => '253'),
            'DK' => array('name' => 'DENMARK', 'code' => '45'),
            'DM' => array('name' => 'DOMINICA', 'code' => '1767'),
            'DO' => array('name' => 'DOMINICAN REPUBLIC', 'code' => '1809'),
            'DZ' => array('name' => 'ALGERIA', 'code' => '213'),
            'EC' => array('name' => 'ECUADOR', 'code' => '593'),
            'EE' => array('name' => 'ESTONIA', 'code' => '372'),
            'EG' => array('name' => 'EGYPT', 'code' => '20'),
            'ER' => array('name' => 'ERITREA', 'code' => '291'),
            'ES' => array('name' => 'SPAIN', 'code' => '34'),
            'ET' => array('name' => 'ETHIOPIA', 'code' => '251'),
            'FI' => array('name' => 'FINLAND', 'code' => '358'),
            'FJ' => array('name' => 'FIJI', 'code' => '679'),
            'FK' => array('name' => 'FALKLAND ISLANDS (MALVINAS)', 'code' => '500'),
            'FM' => array('name' => 'MICRONESIA, FEDERATED STATES OF', 'code' => '691'),
            'FO' => array('name' => 'FAROE ISLANDS', 'code' => '298'),
            'FR' => array('name' => 'FRANCE', 'code' => '33'),
            'GA' => array('name' => 'GABON', 'code' => '241'),
            'GB' => array('name' => 'UNITED KINGDOM', 'code' => '44'),
            'GD' => array('name' => 'GRENADA', 'code' => '1473'),
            'GE' => array('name' => 'GEORGIA', 'code' => '995'),
            'GH' => array('name' => 'GHANA', 'code' => '233'),
            'GI' => array('name' => 'GIBRALTAR', 'code' => '350'),
            'GL' => array('name' => 'GREENLAND', 'code' => '299'),
            'GM' => array('name' => 'GAMBIA', 'code' => '220'),
            'GN' => array('name' => 'GUINEA', 'code' => '224'),
            'GQ' => array('name' => 'EQUATORIAL GUINEA', 'code' => '240'),
            'GR' => array('name' => 'GREECE', 'code' => '30'),
            'GT' => array('name' => 'GUATEMALA', 'code' => '502'),
            'GU' => array('name' => 'GUAM', 'code' => '1671'),
            'GW' => array('name' => 'GUINEA-BISSAU', 'code' => '245'),
            'GY' => array('name' => 'GUYANA', 'code' => '592'),
            'HK' => array('name' => 'HONG KONG', 'code' => '852'),
            'HN' => array('name' => 'HONDURAS', 'code' => '504'),
            'HR' => array('name' => 'CROATIA', 'code' => '385'),
            'HT' => array('name' => 'HAITI', 'code' => '509'),
            'HU' => array('name' => 'HUNGARY', 'code' => '36'),
            'ID' => array('name' => 'INDONESIA', 'code' => '62'),
            'IE' => array('name' => 'IRELAND', 'code' => '353'),
            'IL' => array('name' => 'ISRAEL', 'code' => '972'),
            'IM' => array('name' => 'ISLE OF MAN', 'code' => '44'),
            'IN' => array('name' => 'INDIA', 'code' => '91'),
            'IQ' => array('name' => 'IRAQ', 'code' => '964'),
            'IR' => array('name' => 'IRAN, ISLAMIC REPUBLIC OF', 'code' => '98'),
            'IS' => array('name' => 'ICELAND', 'code' => '354'),
            'IT' => array('name' => 'ITALY', 'code' => '39'),
            'JM' => array('name' => 'JAMAICA', 'code' => '1876'),
            'JO' => array('name' => 'JORDAN', 'code' => '962'),
            'JP' => array('name' => 'JAPAN', 'code' => '81'),
            'KE' => array('name' => 'KENYA', 'code' => '254'),
            'KG' => array('name' => 'KYRGYZSTAN', 'code' => '996'),
            'KH' => array('name' => 'CAMBODIA', 'code' => '855'),
            'KI' => array('name' => 'KIRIBATI', 'code' => '686'),
            'KM' => array('name' => 'COMOROS', 'code' => '269'),
            'KN' => array('name' => 'SAINT KITTS AND NEVIS', 'code' => '1869'),
            'KP' => array('name' => 'KOREA DEMOCRATIC PEOPLES REPUBLIC OF', 'code' => '850'),
            'KR' => array('name' => 'KOREA REPUBLIC OF', 'code' => '82'),
            'KW' => array('name' => 'KUWAIT', 'code' => '965'),
            'KY' => array('name' => 'CAYMAN ISLANDS', 'code' => '1345'),
            'KZ' => array('name' => 'KAZAKSTAN', 'code' => '7'),
            'LA' => array('name' => 'LAO PEOPLES DEMOCRATIC REPUBLIC', 'code' => '856'),
            'LB' => array('name' => 'LEBANON', 'code' => '961'),
            'LC' => array('name' => 'SAINT LUCIA', 'code' => '1758'),
            'LI' => array('name' => 'LIECHTENSTEIN', 'code' => '423'),
            'LK' => array('name' => 'SRI LANKA', 'code' => '94'),
            'LR' => array('name' => 'LIBERIA', 'code' => '231'),
            'LS' => array('name' => 'LESOTHO', 'code' => '266'),
            'LT' => array('name' => 'LITHUANIA', 'code' => '370'),
            'LU' => array('name' => 'LUXEMBOURG', 'code' => '352'),
            'LV' => array('name' => 'LATVIA', 'code' => '371'),
            'LY' => array('name' => 'LIBYAN ARAB JAMAHIRIYA', 'code' => '218'),
            'MA' => array('name' => 'MOROCCO', 'code' => '212'),
            'MC' => array('name' => 'MONACO', 'code' => '377'),
            'MD' => array('name' => 'MOLDOVA, REPUBLIC OF', 'code' => '373'),
            'ME' => array('name' => 'MONTENEGRO', 'code' => '382'),
            'MF' => array('name' => 'SAINT MARTIN', 'code' => '1599'),
            'MG' => array('name' => 'MADAGASCAR', 'code' => '261'),
            'MH' => array('name' => 'MARSHALL ISLANDS', 'code' => '692'),
            'MK' => array('name' => 'MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF', 'code' => '389'),
            'ML' => array('name' => 'MALI', 'code' => '223'),
            'MM' => array('name' => 'MYANMAR', 'code' => '95'),
            'MN' => array('name' => 'MONGOLIA', 'code' => '976'),
            'MO' => array('name' => 'MACAU', 'code' => '853'),
            'MP' => array('name' => 'NORTHERN MARIANA ISLANDS', 'code' => '1670'),
            'MR' => array('name' => 'MAURITANIA', 'code' => '222'),
            'MS' => array('name' => 'MONTSERRAT', 'code' => '1664'),
            'MT' => array('name' => 'MALTA', 'code' => '356'),
            'MU' => array('name' => 'MAURITIUS', 'code' => '230'),
            'MV' => array('name' => 'MALDIVES', 'code' => '960'),
            'MW' => array('name' => 'MALAWI', 'code' => '265'),
            'MX' => array('name' => 'MEXICO', 'code' => '52'),
            'MY' => array('name' => 'MALAYSIA', 'code' => '60'),
            'MZ' => array('name' => 'MOZAMBIQUE', 'code' => '258'),
            'NA' => array('name' => 'NAMIBIA', 'code' => '264'),
            'NC' => array('name' => 'NEW CALEDONIA', 'code' => '687'),
            'NE' => array('name' => 'NIGER', 'code' => '227'),
            'NG' => array('name' => 'NIGERIA', 'code' => '234'),
            'NI' => array('name' => 'NICARAGUA', 'code' => '505'),
            'NL' => array('name' => 'NETHERLANDS', 'code' => '31'),
            'NO' => array('name' => 'NORWAY', 'code' => '47'),
            'NP' => array('name' => 'NEPAL', 'code' => '977'),
            'NR' => array('name' => 'NAURU', 'code' => '674'),
            'NU' => array('name' => 'NIUE', 'code' => '683'),
            'NZ' => array('name' => 'NEW ZEALAND', 'code' => '64'),
            'OM' => array('name' => 'OMAN', 'code' => '968'),
            'PA' => array('name' => 'PANAMA', 'code' => '507'),
            'PE' => array('name' => 'PERU', 'code' => '51'),
            'PF' => array('name' => 'FRENCH POLYNESIA', 'code' => '689'),
            'PG' => array('name' => 'PAPUA NEW GUINEA', 'code' => '675'),
            'PH' => array('name' => 'PHILIPPINES', 'code' => '63'),
            'PK' => array('name' => 'PAKISTAN', 'code' => '92'),
            'PL' => array('name' => 'POLAND', 'code' => '48'),
            'PM' => array('name' => 'SAINT PIERRE AND MIQUELON', 'code' => '508'),
            'PN' => array('name' => 'PITCAIRN', 'code' => '870'),
            'PR' => array('name' => 'PUERTO RICO', 'code' => '1'),
            'PT' => array('name' => 'PORTUGAL', 'code' => '351'),
            'PW' => array('name' => 'PALAU', 'code' => '680'),
            'PY' => array('name' => 'PARAGUAY', 'code' => '595'),
            'QA' => array('name' => 'QATAR', 'code' => '974'),
            'RO' => array('name' => 'ROMANIA', 'code' => '40'),
            'RS' => array('name' => 'SERBIA', 'code' => '381'),
            'RU' => array('name' => 'RUSSIAN FEDERATION', 'code' => '7'),
            'RW' => array('name' => 'RWANDA', 'code' => '250'),
            'SA' => array('name' => 'SAUDI ARABIA', 'code' => '966'),
            'SB' => array('name' => 'SOLOMON ISLANDS', 'code' => '677'),
            'SC' => array('name' => 'SEYCHELLES', 'code' => '248'),
            'SD' => array('name' => 'SUDAN', 'code' => '249'),
            'SE' => array('name' => 'SWEDEN', 'code' => '46'),
            'SG' => array('name' => 'SINGAPORE', 'code' => '65'),
            'SH' => array('name' => 'SAINT HELENA', 'code' => '290'),
            'SI' => array('name' => 'SLOVENIA', 'code' => '386'),
            'SK' => array('name' => 'SLOVAKIA', 'code' => '421'),
            'SL' => array('name' => 'SIERRA LEONE', 'code' => '232'),
            'SM' => array('name' => 'SAN MARINO', 'code' => '378'),
            'SN' => array('name' => 'SENEGAL', 'code' => '221'),
            'SO' => array('name' => 'SOMALIA', 'code' => '252'),
            'SR' => array('name' => 'SURINAME', 'code' => '597'),
            'ST' => array('name' => 'SAO TOME AND PRINCIPE', 'code' => '239'),
            'SV' => array('name' => 'EL SALVADOR', 'code' => '503'),
            'SY' => array('name' => 'SYRIAN ARAB REPUBLIC', 'code' => '963'),
            'SZ' => array('name' => 'SWAZILAND', 'code' => '268'),
            'TC' => array('name' => 'TURKS AND CAICOS ISLANDS', 'code' => '1649'),
            'TD' => array('name' => 'CHAD', 'code' => '235'),
            'TG' => array('name' => 'TOGO', 'code' => '228'),
            'TH' => array('name' => 'THAILAND', 'code' => '66'),
            'TJ' => array('name' => 'TAJIKISTAN', 'code' => '992'),
            'TK' => array('name' => 'TOKELAU', 'code' => '690'),
            'TL' => array('name' => 'TIMOR-LESTE', 'code' => '670'),
            'TM' => array('name' => 'TURKMENISTAN', 'code' => '993'),
            'TN' => array('name' => 'TUNISIA', 'code' => '216'),
            'TO' => array('name' => 'TONGA', 'code' => '676'),
            'TR' => array('name' => 'TURKEY', 'code' => '90'),
            'TT' => array('name' => 'TRINIDAD AND TOBAGO', 'code' => '1868'),
            'TV' => array('name' => 'TUVALU', 'code' => '688'),
            'TW' => array('name' => 'TAIWAN, PROVINCE OF CHINA', 'code' => '886'),
            'TZ' => array('name' => 'TANZANIA, UNITED REPUBLIC OF', 'code' => '255'),
            'UA' => array('name' => 'UKRAINE', 'code' => '380'),
            'UG' => array('name' => 'UGANDA', 'code' => '256'),
            'US' => array('name' => 'UNITED STATES', 'code' => '1'),
            'UY' => array('name' => 'URUGUAY', 'code' => '598'),
            'UZ' => array('name' => 'UZBEKISTAN', 'code' => '998'),
            'VA' => array('name' => 'HOLY SEE (VATICAN CITY STATE)', 'code' => '39'),
            'VC' => array('name' => 'SAINT VINCENT AND THE GRENADINES', 'code' => '1784'),
            'VE' => array('name' => 'VENEZUELA', 'code' => '58'),
            'VG' => array('name' => 'VIRGIN ISLANDS, BRITISH', 'code' => '1284'),
            'VI' => array('name' => 'VIRGIN ISLANDS, U.S.', 'code' => '1340'),
            'VN' => array('name' => 'VIET NAM', 'code' => '84'),
            'VU' => array('name' => 'VANUATU', 'code' => '678'),
            'WF' => array('name' => 'WALLIS AND FUTUNA', 'code' => '681'),
            'WS' => array('name' => 'SAMOA', 'code' => '685'),
            'XK' => array('name' => 'KOSOVO', 'code' => '381'),
            'YE' => array('name' => 'YEMEN', 'code' => '967'),
            'YT' => array('name' => 'MAYOTTE', 'code' => '262'),
            'ZA' => array('name' => 'SOUTH AFRICA', 'code' => '27'),
            'ZM' => array('name' => 'ZAMBIA', 'code' => '260'),
            'ZW' => array('name' => 'ZIMBABWE', 'code' => '263')
        );

        $arr = array();

        if (isset($countryPhoneList[$iso_2])) {
            $phcountry = $countryPhoneList[$iso_2];
            $arr['phone'] = $phcountry['code'];
            $arr['country'] = $phcountry['name'];
        }

        return $arr;
    }

    protected function countryGetiso3($iso_2) {
        $iso = array(
            'AND' => 'AD',
            'ARE' => 'AE',
            'AFG' => 'AF',
            'ATG' => 'AG',
            'AIA' => 'AI',
            'ALB' => 'AL',
            'ARM' => 'AM',
            'AGO' => 'AO',
            'ATA' => 'AQ',
            'ARG' => 'AR',
            'ASM' => 'AS',
            'AUT' => 'AT',
            'AUS' => 'AU',
            'ABW' => 'AW',
            'ALA' => 'AX',
            'AZE' => 'AZ',
            'BIH' => 'BA',
            'BRB' => 'BB',
            'BGD' => 'BD',
            'BEL' => 'BE',
            'BFA' => 'BF',
            'BGR' => 'BG',
            'BHR' => 'BH',
            'BDI' => 'BI',
            'BEN' => 'BJ',
            'BLM' => 'BL',
            'BMU' => 'BM',
            'BRN' => 'BN',
            'BOL' => 'BO',
            'BES' => 'BQ',
            'BRA' => 'BR',
            'BHS' => 'BS',
            'BTN' => 'BT',
            'BVT' => 'BV',
            'BWA' => 'BW',
            'BLR' => 'BY',
            'BLZ' => 'BZ',
            'CAN' => 'CA',
            'CCK' => 'CC',
            'COD' => 'CD',
            'CAF' => 'CF',
            'COG' => 'CG',
            'CHE' => 'CH',
            'CIV' => 'CI',
            'COK' => 'CK',
            'CHL' => 'CL',
            'CMR' => 'CM',
            'CHN' => 'CN',
            'COL' => 'CO',
            'CRI' => 'CR',
            'CUB' => 'CU',
            'CPV' => 'CV',
            'CUW' => 'CW',
            'CXR' => 'CX',
            'CYP' => 'CY',
            'CZE' => 'CZ',
            'DEU' => 'DE',
            'DJI' => 'DJ',
            'DNK' => 'DK',
            'DMA' => 'DM',
            'DOM' => 'DO',
            'DZA' => 'DZ',
            'ECU' => 'EC',
            'EST' => 'EE',
            'EGY' => 'EG',
            'ESH' => 'EH',
            'ERI' => 'ER',
            'ESP' => 'ES',
            'ETH' => 'ET',
            'FIN' => 'FI',
            'FJI' => 'FJ',
            'FLK' => 'FK',
            'FSM' => 'FM',
            'FRO' => 'FO',
            'FRA' => 'FR',
            'GAB' => 'GA',
            'GBR' => 'GB',
            'GRD' => 'GD',
            'GEO' => 'GE',
            'GUF' => 'GF',
            'GGY' => 'GG',
            'GHA' => 'GH',
            'GIB' => 'GI',
            'GRL' => 'GL',
            'GMB' => 'GM',
            'GIN' => 'GN',
            'GLP' => 'GP',
            'GNQ' => 'GQ',
            'GRC' => 'GR',
            'SGS' => 'GS',
            'GTM' => 'GT',
            'GUM' => 'GU',
            'GNB' => 'GW',
            'GUY' => 'GY',
            'HKG' => 'HK',
            'HMD' => 'HM',
            'HND' => 'HN',
            'HRV' => 'HR',
            'HTI' => 'HT',
            'HUN' => 'HU',
            'IDN' => 'ID',
            'IRL' => 'IE',
            'ISR' => 'IL',
            'IMN' => 'IM',
            'IND' => 'IN',
            'IOT' => 'IO',
            'IRQ' => 'IQ',
            'IRN' => 'IR',
            'ISL' => 'IS',
            'ITA' => 'IT',
            'JEY' => 'JE',
            'JAM' => 'JM',
            'JOR' => 'JO',
            'JPN' => 'JP',
            'KEN' => 'KE',
            'KGZ' => 'KG',
            'KHM' => 'KH',
            'KIR' => 'KI',
            'COM' => 'KM',
            'KNA' => 'KN',
            'PRK' => 'KP',
            'KOR' => 'KR',
            'XKX' => 'XK',
            'KWT' => 'KW',
            'CYM' => 'KY',
            'KAZ' => 'KZ',
            'LAO' => 'LA',
            'LBN' => 'LB',
            'LCA' => 'LC',
            'LIE' => 'LI',
            'LKA' => 'LK',
            'LBR' => 'LR',
            'LSO' => 'LS',
            'LTU' => 'LT',
            'LUX' => 'LU',
            'LVA' => 'LV',
            'LBY' => 'LY',
            'MAR' => 'MA',
            'MCO' => 'MC',
            'MDA' => 'MD',
            'MNE' => 'ME',
            'MAF' => 'MF',
            'MDG' => 'MG',
            'MHL' => 'MH',
            'MKD' => 'MK',
            'MLI' => 'ML',
            'MMR' => 'MM',
            'MNG' => 'MN',
            'MAC' => 'MO',
            'MNP' => 'MP',
            'MTQ' => 'MQ',
            'MRT' => 'MR',
            'MSR' => 'MS',
            'MLT' => 'MT',
            'MUS' => 'MU',
            'MDV' => 'MV',
            'MWI' => 'MW',
            'MEX' => 'MX',
            'MYS' => 'MY',
            'MOZ' => 'MZ',
            'NAM' => 'NA',
            'NCL' => 'NC',
            'NER' => 'NE',
            'NFK' => 'NF',
            'NGA' => 'NG',
            'NIC' => 'NI',
            'NLD' => 'NL',
            'NOR' => 'NO',
            'NPL' => 'NP',
            'NRU' => 'NR',
            'NIU' => 'NU',
            'NZL' => 'NZ',
            'OMN' => 'OM',
            'PAN' => 'PA',
            'PER' => 'PE',
            'PYF' => 'PF',
            'PNG' => 'PG',
            'PHL' => 'PH',
            'PAK' => 'PK',
            'POL' => 'PL',
            'SPM' => 'PM',
            'PCN' => 'PN',
            'PRI' => 'PR',
            'PSE' => 'PS',
            'PRT' => 'PT',
            'PLW' => 'PW',
            'PRY' => 'PY',
            'QAT' => 'QA',
            'REU' => 'RE',
            'ROU' => 'RO',
            'SRB' => 'RS',
            'RUS' => 'RU',
            'RWA' => 'RW',
            'SAU' => 'SA',
            'SLB' => 'SB',
            'SYC' => 'SC',
            'SDN' => 'SD',
            'SSD' => 'SS',
            'SWE' => 'SE',
            'SGP' => 'SG',
            'SHN' => 'SH',
            'SVN' => 'SI',
            'SJM' => 'SJ',
            'SVK' => 'SK',
            'SLE' => 'SL',
            'SMR' => 'SM',
            'SEN' => 'SN',
            'SOM' => 'SO',
            'SUR' => 'SR',
            'STP' => 'ST',
            'SLV' => 'SV',
            'SXM' => 'SX',
            'SYR' => 'SY',
            'SWZ' => 'SZ',
            'TCA' => 'TC',
            'TCD' => 'TD',
            'ATF' => 'TF',
            'TGO' => 'TG',
            'THA' => 'TH',
            'TJK' => 'TJ',
            'TKL' => 'TK',
            'TLS' => 'TL',
            'TKM' => 'TM',
            'TUN' => 'TN',
            'TON' => 'TO',
            'TUR' => 'TR',
            'TTO' => 'TT',
            'TUV' => 'TV',
            'TWN' => 'TW',
            'TZA' => 'TZ',
            'UKR' => 'UA',
            'UGA' => 'UG',
            'UMI' => 'UM',
            'USA' => 'US',
            'URY' => 'UY',
            'UZB' => 'UZ',
            'VAT' => 'VA',
            'VCT' => 'VC',
            'VEN' => 'VE',
            'VGB' => 'VG',
            'VIR' => 'VI',
            'VNM' => 'VN',
            'VUT' => 'VU',
            'WLF' => 'WF',
            'WSM' => 'WS',
            'YEM' => 'YE',
            'MYT' => 'YT',
            'ZAF' => 'ZA',
            'ZMB' => 'ZM',
            'ZWE' => 'ZW',
            'SCG' => 'CS',
            'ANT' => 'AN',
        );

        $iso_3 = "";

        foreach ($iso as $key => $val) {
            if ($val == $iso_2) {
                $iso_3 = $key;
                break;
            }
        }

        return $iso_3;
    }

}
