<?php
/**
 *
 * @copyright  Ambientinfotech
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Ambientinfotech\Paytabsinfotech\Controller\Checkout;

class Start extends \Magento\Framework\App\Action\Action
{
    /**
    * @var \Magento\Checkout\Model\Session
    */
    protected $_checkoutSession;

    /**
    * @var \Coinbase\Magento2PaymentGateway\Model\PaymentMethod
    */
    protected $_paymentMethod;

	protected $_resultJsonFactory;

	protected $_logger;

    /**
    * @param \Magento\Framework\App\Action\Context $context
    * @param \Magento\Checkout\Model\Session $checkoutSession
    * @param \Coinbase\Magento2PaymentGateway\Model\PaymentMethod $paymentMethod
    */
    public function __construct(
    \Magento\Framework\App\Action\Context $context,
    \Magento\Checkout\Model\Session $checkoutSession,
    \Ambientinfotech\Paytabsinfotech\Model\PaymentMethod $paymentMethod,
	\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,\Psr\Log\LoggerInterface $logger
    ) {
        $this->_paymentMethod = $paymentMethod;
        $this->_checkoutSession = $checkoutSession;
		$this->_resultJsonFactory = $resultJsonFactory;
		$this->_logger = $logger;
        parent::__construct($context);
    }

    /**
    * Start checkout by requesting checkout code and dispatching customer to Coinbase.
    */
    public function execute()
    {
		$result = $this->_resultJsonFactory->create();
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
		$url = $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
		// $this->_logger->debug('ACUID - '.$_GET['acuid']);

		$acuid = 123;

		$retobj = $this->_paymentMethod->getPostHTML($this->getOrder(),$acuid);


		if($retobj['response_code'] == '4012')
		{
			$html = '<script language="javascript">window.location.href = "'.$retobj['payment_url'].'"</script>';
			$html .='<a href="'.$retobj['payment_url'].'">Please Wait... Redirecting to Paytabs Payment...</a>';
			//$this->getResponse()->setRedirect($retobj['payment_url']);
			return $result->setData(['html' => $html]);
		}
		else {
      // echo "<pre>";
      // print_r($retobj);
      // die();
			$html ='<label style="color:#fb0505;">'.$retobj['result'].'...</label>&nbsp;<a href="'.$url.'">Go to Home</a>';
			return $result->setData(['html' => $html]);
		}
		//$this->_logger->debug($html);
		//echo json_encode($data);



    //return json_encode($data);
		//AA Not Required $this->getResponse()->setRedirect($this->_paymentMethod->getCheckoutUrl($this->getOrder()));
    }

    /**
    * Get order object.
    *
    * @return \Magento\Sales\Model\Order
    */
    protected function getOrder()
    {
        return $this->_checkoutSession->getLastRealOrder();
    }
}
