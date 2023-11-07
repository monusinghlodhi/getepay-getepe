<?php
namespace Getepay\Getepe\Controller\Redirect;

use Magento\Framework\View\Result\PageFactory;
//use Getepay\Getepe\Model\GetepayPaymentMethod;
use Magento\Framework\App\Action\Context;
   
class Index extends  \Magento\Framework\App\Action\Action
{
	protected $pageFactory;
	 public function __construct(Context $context,PageFactory $pageFactory) {
		$this->pageFactory = $pageFactory;
        
		parent::__construct($context);
        					
    }

	public function execute()
	{
		 return $this->pageFactory->create();
	}

 }
