<?php

namespace Getepay\Getepe\Model;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;


class FormFieldTrack extends \Magento\Config\Block\System\Config\Form\Field
{
    public function __construct(
        Context $context,
        array $data = []
        )
    {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element)
    {		
        $baseUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);

        $comment = $element->getComment();

        $copyButton = $comment . "<script type='text/javascript'>
						//<![CDATA[
						require([
						    'jquery'
						], function ($) {
							'use strict';

                            // Importing manually added validation functions
                            ". $this->jsValidation() ."

                            let elementId   = '" .$element->getHtmlId(). "'
							let element     = $('#' + elementId)

                            let fieldName = elementId.substring(20)
                            let fieldType = '". $element->getType() ."'
                  
						});
						//]]>
						</script>
						";
        $element->setComment($copyButton);
        return $element->getElementHtml();
    }

    public function jsValidation()
    {
        return "
                function checkRequiredEntry(field)
                {
                    return field == ''? false : true;
                }

                function checkIfValidDigits(field)
                {
                    return !isNaN(parseFloat(field)) && isFinite(field);
                }

                function checkIfNonNegative(field)
                {
                    let fieldNum = parseInt(field)
                    
                    return fieldNum < 0? false : true;
                }

                function checkIfInNumberRange(field, x, y)
                {
                    let fieldNum = parseInt(field)

                    return (fieldNum >= x && fieldNum <=y)? true : false;
                }
            ";
    }
}