<?php
namespace Giftd\Platform\Block;

class JsCode extends \Magento\Framework\View\Element\AbstractBlock
{
    protected $_giftdHelper;

    /**
     * Constructor
     *
     * @param \Magento\Framework\View\Element\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        \Giftd\Platform\Helper\Data $giftdHelper,
        array $data = []
    ) {
        $this->_giftdHelper = $giftdHelper;
        parent::__construct($context, $data);
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml()
    {
        if ($jsCode = $this->_giftdHelper->getGiftdJsCode()) {
            return sprintf('<script type="text/javascript">%s</script>', $jsCode);
        }

        return '';
    }
}