<?php
namespace Giftd\Platform\Plugin\Model\SalesRule\Quote;

use Giftd\Platform\lib\Client as Giftd_Client;
use Giftd\Platform\lib\Card as Giftd_Card;
use Giftd\Platform\lib\Exception as Giftd_Exception;

use Magento\Framework\Exception\LocalizedException;

class Discount
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
     * @var \Magento\SalesRule\Model\CouponFactory
     */
    protected $_couponFactory;

    /**
     * @var \Magento\SalesRule\Model\RuleFactory
     */
    protected $_salesRuleFactory;

    /**
     * @var \Magento\Customer\Model\ResourceModel\Customer\CollectionFactory
     */
    protected $_customerGroupCollectionFactory;

    /**
     * @var \Magento\Framework\Pricing\Helper\Data
     */
    protected $_pricingHelper;

    protected $_cachedCustomerGroupIds = null;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Giftd\Platform\Helper\Data $giftdHelper,
        \Magento\SalesRule\Model\CouponFactory $couponFactory,
        \Magento\SalesRule\Model\RuleFactory $salesRuleFactory,
        \Magento\Customer\Model\ResourceModel\Group\CollectionFactory $customerGroupCollectionFactory,
        \Magento\Framework\Pricing\Helper\Data $pricingHelper
    ) {
        $this->_logger = $logger;
        $this->_giftdHelper = $giftdHelper;
        $this->_couponFactory = $couponFactory;
        $this->_salesRuleFactory = $salesRuleFactory;
        $this->_customerGroupCollectionFactory = $customerGroupCollectionFactory;
        $this->_pricingHelper = $pricingHelper;
    }

    public function beforeCollect(
        \Magento\SalesRule\Model\Quote\Discount $discount,
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment,
        \Magento\Quote\Model\Quote\Address\Total $total
    ) {
        $couponCode = $quote->getCouponCode();
        if (!$couponCode) {
            return;
        }

        /**
         * @var $address \Magento\Quote\Model\Quote\Address
         */
        $address = $shippingAssignment->getShipping()->getAddress();

        if (!$quote->isVirtual() && $address->getAddressType() == 'billing') {
            return;
        }

        $subtotal = $total->getBaseSubtotal();

        //do not sync twice
        if ($address->hasData('__giftd_processed_with_total') && $address->getData('__giftd_processed_with_total') == $subtotal) {
            return;
        }

        $store = $quote->getStore();
        $storeId = $store->getId();

        $apiKey = $this->_giftdHelper->getGiftdApiKey($storeId);
        $userId = $this->_giftdHelper->getGiftdUserId($storeId);
        $couponPrefix = $this->_giftdHelper->getGiftdCouponPrefix($storeId);

        if (!$apiKey || !$userId || !$couponPrefix) {
            return;
        }

        if (strpos($couponCode, $couponPrefix) !== 0) {
            return;
        }

        $couponApplied = $quote->dataHasChangedFor('coupon_code');
        $throwErrorPhrase = false;

        try {
            $client = new Giftd_Client($userId, $apiKey);
            $card = $client->checkByToken($couponCode, $subtotal);
            if ($card) {
                if ($card->token_status == Giftd_Card::TOKEN_STATUS_USED) {
                    //remove rule if coupon wasn't used
                    $coupon = $this->_couponFactory->create();
                    $coupon->load($couponCode, 'code');
                    if ($coupon->getTimesUsed() <= 0) {
                        $salesRule = $this->_salesRuleFactory->create();
                        $salesRule->load($coupon->getRuleId());
                        $salesRule->delete();
                    }
                    $quote->setCouponCode('');
                    $address->setCouponCode('');
                } elseif ($card->token_status == Giftd_Card::TOKEN_STATUS_OK) {
                    //sync rule
                    try {
                        $this->saveSalesRule($couponCode, $store->getWebsiteId(), $card);
                        $address->setData('__giftd_processed_with_total', $subtotal);
                    } catch (\Exception $e) {

                    }

                    if ($couponApplied && $subtotal < $card->min_amount_total) {
                        $minTotal = $card->min_amount_total * $quote->getBaseToQuoteRate();
                        $minTotal = $this->_pricingHelper->currencyByStore($minTotal, $store);
                        $throwErrorPhrase = __('To use this gift card the subtotal should be at least %1', $minTotal);
                    }
                }
            }
        } catch (Giftd_Exception $e) {

        } catch (LocalizedException $e) {

        } catch (\Exception $e) {
            $this->_logger->critical($e);
        }

        if ($throwErrorPhrase) {
            throw new LocalizedException($throwErrorPhrase);
        }
    }

    /**
     * @return array
     */
    protected function _getCustomerGroupIds()
    {
        if ($this->_cachedCustomerGroupIds === null) {
            $collection = $this->_customerGroupCollectionFactory->create();
            $this->_cachedCustomerGroupIds = $collection->getAllIds();
        }

        return $this->_cachedCustomerGroupIds;
    }

    public function saveSalesRule($couponCode, $websiteId, Giftd_Card $card)
    {
        $discount = $card->amount_available;
        $minAmount = $card->min_amount_total;

        $name = 'Giftd card';
        $customerGroupIds = $this->_getCustomerGroupIds();
        $websiteIds = [$websiteId];

        $data = [
            'product_ids' => null,
            'name' => $name,
            'description' => null,
            'is_active' => 1,
            'website_ids' => $websiteIds,
            'customer_group_ids' => $customerGroupIds,
            'coupon_type' => 2,
            'coupon_code' => $couponCode,
            'uses_per_coupon' => 1,
            'uses_per_customer' => 1,
            'from_date' => null,
            'to_date' => null,
            'sort_order' => null,
            'is_rss' => 0,
            'conditions' => [
                '1' => [
                    'type' => 'Magento\SalesRule\Model\Rule\Condition\Combine',
                    'aggregator' => 'all',
                    'value' => '1',
                    'new_child' => null
                ],
                '1--1' => [
                    'type' => 'Magento\SalesRule\Model\Rule\Condition\Address',
                    'attribute' => 'base_subtotal',
                    'operator' => '>=',
                    'value' => $minAmount
                ]
            ],
            'simple_action' => 'cart_fixed',
            'discount_amount' => $discount,
            'discount_qty' => 0,
            'discount_step' => null,
            'apply_to_shipping' => 0,
            'simple_free_shipping' => 0,
            'stop_rules_processing' => 0,
            'store_labels' => [$name],
        ];

        $coupon = $this->_couponFactory->create();
        $coupon->load($couponCode, 'code');

        $salesRule = $this->_salesRuleFactory->create();
        if ($coupon->getRuleId()) {
            $salesRule->load($coupon->getRuleId());
        }

        /**
         * @var $salesRule \Magento\SalesRule\Model\Rule
         */
        $dataObject = new \Magento\Framework\DataObject($data);
        $validateResult = $salesRule->validateData($dataObject);
        if ($validateResult === true) {
            try {
                $salesRule->loadPost($data);
                $salesRule->save();
            } catch (LocalizedException $e) {

            } catch (\Exception $e) {
                $this->_logger->critical($e);
            }
        }

    }
}