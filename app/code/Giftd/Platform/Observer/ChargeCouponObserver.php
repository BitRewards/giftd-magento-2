<?php
namespace Giftd\Platform\Observer;

use Giftd\Platform\lib\Client as Giftd_Client;
use Giftd\Platform\lib\Card as Giftd_Card;
use Giftd\Platform\lib\Exception as Giftd_Exception;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

class ChargeCouponObserver implements ObserverInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var \Giftd\Platform\Helper\Data
     */
    protected $_giftdHelper;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Giftd\Platform\Helper\Data $giftdHelper
    ) {
        $this->_logger = $logger;
        $this->_giftdHelper = $giftdHelper;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Exception
     * @SuppressWarnings(PHPMD.ExitExpression)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(EventObserver $observer)
    {
        /**
         * @var $order \Magento\Sales\Model\Order
         */
        $order = $observer->getEvent()->getData('order');
        $couponCode = $order->getCouponCode();
        if (!$couponCode) {
            return;
        }

        $storeId = $order->getStoreId();

        $apiKey = $this->_giftdHelper->getGiftdApiKey($storeId);
        $userId = $this->_giftdHelper->getGiftdUserId($storeId);
        $couponPrefix = $this->_giftdHelper->getGiftdCouponPrefix($storeId);

        if (!$apiKey || !$userId || !$couponPrefix) {
            return;
        }

        if (strpos($couponCode, $couponPrefix) !== 0) {
            return;
        }

        try {
            $client = new Giftd_Client($userId, $apiKey);
            $card = $client->checkByToken($couponCode, $order->getBaseSubtotal());

            if (!$card) {
                throw new LocalizedException(__('Coupon code "%1" is no longer active.', $couponCode));
            }

            if ($card->token_status != Giftd_Card::TOKEN_STATUS_OK) {
                throw new LocalizedException(__('Coupon code "%1" is no longer active.', $couponCode));
            }

            $client->charge($couponCode, $card->amount_available, $order->getBaseSubtotal(), microtime(true) . '_' . $order->getIncrementId());
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new LocalizedException(__('Could not use coupon code "%1" at the moment. Please, try again later.', $couponCode));
        }
    }
}