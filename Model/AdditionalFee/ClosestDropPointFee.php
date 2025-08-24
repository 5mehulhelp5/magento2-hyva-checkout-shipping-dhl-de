<?php

declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Model\AdditionalFee;

use Dhl\Paket\Model\AdditionalFee\AdditionalFeeConfiguration;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use Netresearch\ShippingCore\Api\AdditionalFee\AdditionalFeeProviderInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

class ClosestDropPointFee implements AdditionalFeeProviderInterface
{
    public function __construct(
        private readonly \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        private readonly AdditionalFeeConfiguration $additionalFeeConfiguration,
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    public function getFee(Quote $quote): float
    {
        $selections = $quote->getShippingAddress()->getExtensionAttributes()->getShippingOptionSelections();
        if (!$selections) {
            return 0.0;
        }

        /** @var SelectionInterface $selection */
        foreach ($selections as $selection) {
            if ($selection->getShippingOptionCode() === Codes::SERVICE_OPTION_CDP && (bool)$selection->getInputValue()) {
                $feeConfigPath = 'carriers/dhlpaket/service_closestdroppoint_charge';
                return (float) $this->scopeConfig->getValue($feeConfigPath, ScopeInterface::SCOPE_STORE);
            }
        }

        return 0.0;
    }

    public function getAmounts(int $storeId): array
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            $fee = $this->getFee($quote);
            if ($fee > 0) {
                return [$this->additionalFeeConfiguration->getDisplayObject($fee, $storeId)];
            }
        } catch (\Exception) {
            return [];
        }
        return [];
    }
}