<?php
namespace Giftd\Platform\Observer\Config;

use Giftd\Platform\lib\Client as Giftd_Client;
use Giftd\Platform\lib\Exception as Giftd_Exception;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

class GiftdPlatformSectionChangedObserver implements ObserverInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;

    /**
     * @var \Magento\Backend\Model\Auth\Session
     */
    protected $_authSession;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $_messageManager;

    /**
     * Application config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_appConfig;

    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    protected $_resourceConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * GiftdPlatformSectionChangedObserver constructor.
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Magento\Backend\Model\Auth\Session $authSession
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Config\Model\ResourceModel\Config $resourceConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Config\Model\ResourceModel\Config $resourceConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_logger = $logger;
        $this->_coreRegistry = $coreRegistry;
        $this->_authSession = $authSession;
        $this->_messageManager = $messageManager;
        $this->_appConfig = $config;
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
        $websiteId = $observer->getEvent()->getData('website');
        $website = $this->_storeManager->getWebsite($websiteId);
        $scope = \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES;
        $scopeId = (int) $websiteId;

        $oldSettingValues = $this->_coreRegistry->registry('giftd_platform_section_old_setting_values');

        $oldUserId = isset($oldSettingValues[\Giftd\Platform\Helper\Data::XML_PATH_USER_ID]) ? $oldSettingValues[\Giftd\Platform\Helper\Data::XML_PATH_USER_ID] : null;
        $oldApiKey = isset($oldSettingValues[\Giftd\Platform\Helper\Data::XML_PATH_API_KEY]) ? $oldSettingValues[\Giftd\Platform\Helper\Data::XML_PATH_API_KEY] : null;

        $oldCouponPrefix = $this->_appConfig->getValue(\Giftd\Platform\Helper\Data::XML_PATH_COUPON_PREFIX, $scope, $scopeId);

        $newUserId = $this->_appConfig->getValue(\Giftd\Platform\Helper\Data::XML_PATH_USER_ID, $scope, $scopeId);
        $newApiKey = $this->_appConfig->getValue(\Giftd\Platform\Helper\Data::XML_PATH_API_KEY, $scope, $scopeId);

        $wasInstalled = $oldUserId && $oldApiKey && $oldCouponPrefix;
        $shouldBeInstalled = $newUserId && $newApiKey;
        $settingsWasChanged = ($oldUserId != $newUserId) || ($oldApiKey != $newApiKey);

        $uninstall = $wasInstalled && $settingsWasChanged;
//        $install = $shouldBeInstalled && (!$wasInstalled || $settingsWasChanged);
        $install = $shouldBeInstalled;

        if ($uninstall) {
            $this->_uninstall($oldUserId, $oldApiKey, $website);
            if (!$install) {
                $this->_messageManager->addSuccess(__('You have successfully disabled Giftd module!'));
            }
        }

        if ($install) {
            $this->_install($newUserId, $newApiKey, $website);
        }
    }

    protected function _install($userId, $apiKey, \Magento\Store\Api\Data\WebsiteInterface $website)
    {
        try {
            /**
             * @var $website \Magento\Store\Model\Website
             */
            $user = $this->_authSession->getUser();

            $data = [
                'email' => $user->getEmail(),
                'phone' => $this->_appConfig->getValue(\Magento\Store\Model\Information::XML_PATH_STORE_INFO_PHONE),
                'name'  => $user->getName(),
                'url'   => $this->_appConfig->getValue(\Magento\Store\Model\Store::XML_PATH_UNSECURE_BASE_URL),
                'title' => $website->getName(),
                'magento_version' => \Magento\Framework\AppInterface::VERSION
            ];

            $client = new Giftd_Client($userId, $apiKey);

            $client->query('magento/install', $data);

            $result = $client->query('partner/get');

            $couponPrefix = isset($result['data']['token_prefix']) ? $result['data']['token_prefix'] : null;

            $this->_resourceConfig->saveConfig(
                \Giftd\Platform\Helper\Data::XML_PATH_COUPON_PREFIX,
                $couponPrefix,
                \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES,
                (int) $website->getId()
            );

            $result = $client->query('partner/getJs');

            $code = isset($result['data']['js']) ? trim($result['data']['js']) : null;

            $this->_resourceConfig->saveConfig(
                \Giftd\Platform\Helper\Data::XML_PATH_JS_CODE,
                $code,
                \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES,
                (int) $website->getId()
            );

            $this->_messageManager->addSuccess(__('You have successfully enabled Giftd module!'));

        } catch (Giftd_Exception $e) {
            $this->_messageManager->addError(__('An error occurred while synchronization with Giftd: %1', $e->getMessage()));
        } catch (LocalizedException $e) {
            $this->_messageManager->addError($e->getMessage());
        } catch (\Exception $e) {
            $this->_messageManager->addError(__('An error occurred while synchronization with Giftd. Please, try again later.'));
            $this->_logger->critical($e);
        }
    }

    protected function _uninstall($userId, $apiKey, \Magento\Store\Api\Data\WebsiteInterface $website)
    {
        try {
            $client = new Giftd_Client($userId, $apiKey);
            $result = $client->query('magento/uninstall');
        } catch (Giftd_Exception $e) {

        } catch (LocalizedException $e) {

        } catch (\Exception $e) {
            $this->_logger->critical($e);
        }

        try {
            $this->_resourceConfig->deleteConfig(
                \Giftd\Platform\Helper\Data::XML_PATH_JS_CODE,
                \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES,
                (int) $website->getId()
            );
        } catch (LocalizedException $e) {

        } catch (\Exception $e) {
            $this->_logger->critical($e);
        }

        try {
            $this->_resourceConfig->deleteConfig(
                \Giftd\Platform\Helper\Data::XML_PATH_COUPON_PREFIX,
                \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES,
                (int) $website->getId()
            );
        } catch (LocalizedException $e) {

        } catch (\Exception $e) {
            $this->_logger->critical($e);
        }
    }
}