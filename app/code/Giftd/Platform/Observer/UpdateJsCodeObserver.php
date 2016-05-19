<?php
namespace Giftd\Platform\Observer;

use Giftd\Platform\lib\Client as Giftd_Client;
use Giftd\Platform\lib\Exception as Giftd_Exception;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

class UpdateJsCodeObserver implements ObserverInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var \Giftd\Platform\Helper\Data
     */
    protected $_giftdHelper;

    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    protected $_resourceConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Giftd\Platform\Helper\Data $giftdHelper,
        \Magento\Config\Model\ResourceModel\Config $resourceConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_logger = $logger;
        $this->_giftdHelper = $giftdHelper;
        $this->_resourceConfig = $resourceConfig;
        $this->_storeManager = $storeManager;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     * @SuppressWarnings(PHPMD.ExitExpression)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(EventObserver $observer)
    {
        /**
         * @var $request \Magento\Framework\App\RequestInterface
         */
        $request = $observer->getEvent()->getData('request');
        $updateJsParamValue = $request->getParam('giftd-update-js');
        if (!$updateJsParamValue) {
            return;
        }

        $store = $this->_storeManager->getStore();
        $storeId = $store->getId();

        $apiKey = $this->_giftdHelper->getGiftdApiKey($storeId);
        $userId = $this->_giftdHelper->getGiftdUserId($storeId);

        if (!$apiKey || !$userId) {
            return;
        }

        if ($updateJsParamValue != $apiKey) {
            return;
        }

        try {
            $client = new Giftd_Client($userId, $apiKey);

            $result = $client->query('partner/getJs');

            $code = isset($result['data']['js']) ? trim($result['data']['js']) : null;

            $this->_resourceConfig->saveConfig(
                \Giftd\Platform\Helper\Data::XML_PATH_JS_CODE,
                $code,
                \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES,
                (int) $store->getWebsiteId()
            );
        } catch (Giftd_Exception $e) {

        } catch (LocalizedException $e) {

        } catch (\Exception $e) {
            $this->_logger->critical($e);
        }
    }
}