<?php
namespace Giftd\Platform\Helper;

/**
 * Giftd Platform data helper
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const XML_PATH_USER_ID          = 'giftd_platform/settings/user_id';
    const XML_PATH_API_KEY          = 'giftd_platform/settings/api_key';
    const XML_PATH_COUPON_PREFIX    = 'giftd_platform/settings/partner_token_prefix';
    const XML_PATH_JS_CODE          = 'giftd_platform/settings/js_code';

    public function getGiftdUserId($storeId = null)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_USER_ID, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getGiftdApiKey($storeId = null)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_API_KEY, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getGiftdCouponPrefix($storeId = null)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_COUPON_PREFIX, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getGiftdJsCode($storeId = null)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_JS_CODE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

}