<?php
/**
 * @copyright  Ambientinfotech
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Ambientinfotech\Paytabsinfotech\Controller\Ipn;

use Magento\Framework\App\Config\ScopeConfigInterface;

use Magento\Framework\App\Action\Action as AppAction;

class Callback extends AppAction
{
    /**
    * @var \Citrus\Icp\Model\PaymentMethod
    */
    protected $_paymentMethod;

    /**
    * @var \Magento\Sales\Model\Order
    */
    protected $_order;

    /**
    * @var \Magento\Sales\Model\OrderFactory
    */
    protected $_orderFactory;

    /**
    * @var Magento\Sales\Model\Order\Email\Sender\OrderSender
    */
    protected $_orderSender;

    /**
    * @var \Psr\Log\LoggerInterface
    */
    protected $_logger;

	protected $request;

    /**
    * @param \Magento\Framework\App\Action\Context $context
    * @param \Magento\Sales\Model\OrderFactory $orderFactory
    * @param \Citrus\Icp\Model\PaymentMethod $paymentMethod
    * @param Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
    * @param  \Psr\Log\LoggerInterface $logger
    */
    public function __construct(
    \Magento\Framework\App\Action\Context $context,
	\Magento\Framework\App\Request\Http $request,
    \Magento\Sales\Model\OrderFactory $orderFactory,
    \Ambientinfotech\Paytabsinfotech\Model\PaymentMethod $paymentMethod,
    \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
    \Psr\Log\LoggerInterface $logger
    ) {
        $this->_paymentMethod = $paymentMethod;
        $this->_orderFactory = $orderFactory;
        $this->_client = $this->_paymentMethod->getClient();
        $this->_orderSender = $orderSender;
        $this->_logger = $logger;
		    $this->request = $request;
        parent::__construct($context);
    }

    /**
    * Handle POST request to Citrus callback endpoint.
    */
    public function execute()
    {
        try {
            // Cryptographically verify authenticity of callback
            if($this->getRequest ()->isPost ())
			{
				$this->_success();
				$this->paymentAction();
			}
			else
			{
	            $this->_logger->addError("Ambientinfotech: no post back data received in callback");
				return $this->_failure();
			}
        } catch (Exception $e) {
            $this->_logger->addError("Ambientinfotech: error processing callback");
            $this->_logger->addError($e->getMessage());
            return $this->_failure();
        }

		$this->_logger->addInfo("Ambientinfotech Transaction END from Ambientinfotech");
    }

	protected function paymentAction()
	{
		$orderid = "-1";

		$merchantemail = $this->_paymentMethod->getConfigData('merchantemail');
		$secretkey = $this->_paymentMethod->getConfigData('secretkey');
		$gateway_url = 'https://www.paytabs.com/apiv2/verify_payment';

		$postdata = $this->request->getPost();

		$pref='';
		foreach($postdata as $key => $val)
		{
			 if($key == 'payment_reference') $pref=$val;
		}

		if (isset($pref) && $pref !='') {

			$request_param =array('secret_key'=>$secretkey,'merchant_email'=>$merchantemail, 'payment_reference'=>$pref);

			$request_string = http_build_query($request_param);

			$response_data = $this->_paymentMethod->_sendRequest($gateway_url, $request_string);

			$object = json_decode($response_data);

			$orderid=$object->reference_no;

			$this->_loadOrder($orderid);

			if($object->response_code == "100")
			{

					$this->_registerPaymentCapture($object->transaction_id, $object->amount, $object->result);
					//$this->_logger->addInfo("Ambientinfotech Response Order success..".$txMsg);

					$redirectUrl = $this->_paymentMethod->getSuccessUrl();
					//AA Where
					$this->_redirect($redirectUrl);

			}
			else
			{

				$this->_createAmbientinfotechComment($object->result);
				$this->_order->cancel()->save();

				//$this->_logger->addInfo("Ambientinfotech Response Order cancelled ..".$object->result);

				$this->messageManager->addError("<strong>PayTab Error:</strong>".$object->result);
				//AA where
				$redirectUrl = $this->_paymentMethod->getCancelUrl();
				$this->_redirect($redirectUrl);
			}
		}
	}


	//AA - To review - required
    protected function _registerPaymentCapture($transactionId, $amount, $message)
    {
        $payment = $this->_order->getPayment();


        $payment->setTransactionId($transactionId)
        ->setPreparedMessage($this->_createAmbientinfotechComment($message))
        ->setShouldCloseParentTransaction(true)
        ->setIsTransactionClosed(0)
		->setAdditionalInformation(['Ambientinfotechexpress','expresscheckout'])
        ->registerCaptureNotification(
		//AA
            $amount,
            true
        );

        $this->_order->save();

        $invoice = $payment->getCreatedInvoice();
        if ($invoice && !$this->_order->getEmailSent()) {
            $this->_orderSender->send($this->_order);
            $this->_order->addStatusHistoryComment(
                __('You notified customer about invoice #%1.', $invoice->getIncrementId())
            )->setIsCustomerNotified(
                true
            )->save();
        }
    }

	//AA Done
    protected function _loadOrder($order_id)
    {
        $this->_order = $this->_orderFactory->create()->loadByIncrementId($order_id);

        if (!$this->_order && $this->_order->getId()) {
            throw new Exception('Could not find Magento order with id $order_id');
        }
    }

	//AA Done
    protected function _success()
    {
        $this->getResponse()
             ->setStatusHeader(200);
    }

	//AA Done
    protected function _failure()
    {
        $this->getResponse()
             ->setStatusHeader(400);
    }

    /**
    * Returns the generated comment or order status history object.
    *
    * @return string|\Magento\Sales\Model\Order\Status\History
    */
	//AA Done
    protected function _createAmbientinfotechComment($message = '')
    {
        if ($message != '')
        {
            $message = $this->_order->addStatusHistoryComment($message);
            $message->setIsCustomerNotified(null);
        }

        return $message;
    }

}
