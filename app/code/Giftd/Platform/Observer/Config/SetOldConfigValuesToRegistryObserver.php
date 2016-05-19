<?php
namespace Giftd\Platform\Observer\Config;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;

class SetOldConfigValuesToRegistryObserver implements ObserverInterface
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
     * Application config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_appConfig;

    /**
     * GiftdPlatformSectionChangedObserver constructor.
     *
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config
    ) {
        $this->_logger = $logger;
        $this->_coreRegistry = $coreRegistry;
        $this->_appConfig = $config;
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

        if ($request->getParam('section') != 'giftd_platform') {
            return;
        }

        $scope = \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES;
        $scopeId = (int) $request->getParam('website');

        $oldSettingPaths = [
            \Giftd\Platform\Helper\Data::XML_PATH_USER_ID,
            \Giftd\Platform\Helper\Data::XML_PATH_API_KEY,
            \Giftd\Platform\Helper\Data::XML_PATH_JS_CODE,
        ];

        $oldSettingValues = [];
        foreach ($oldSettingPaths as $path) {
            $oldSettingValues[$path] = $this->_appConfig->getValue($path, $scope, $scopeId);
        }

        $this->_coreRegistry->register('giftd_platform_section_old_setting_values', $oldSettingValues);
    }
}