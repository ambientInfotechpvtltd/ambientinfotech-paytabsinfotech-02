<?php

namespace Ambientinfotech\Paytabsinfotech\Model\Adminhtml\Source;

use Magento\Payment\Model\Method\AbstractMethod;

class Env implements \Magento\Framework\Option\ArrayInterface
{
	public function toOptionArray()
	{
		return array(
				array('value' => 'test','label' => 'Test'),
				array('value' => 'live','label' => 'Live')
				);
	}
}