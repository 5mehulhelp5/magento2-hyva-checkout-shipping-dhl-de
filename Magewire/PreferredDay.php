<?php

declare(strict_types=1);

namespace Hyva\HyvaShippingDhl\Magewire;

use Magewirephp\Magewire\Component;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes;

class PreferredDay extends ShippingOptions
{
    /**
     * @var string
     */
    public string $preferredDay = '';

    public $fee;

    /**
     * Mount the component.
     */
    public function mount() {
        /** @var $quoteSelection SelectionInterface */
        $quoteSelections = $this->loadFromDb(Codes::SERVICE_OPTION_PREFERRED_DAY);

        if ($quoteSelections) {
            if (isset($quoteSelections['enabled'])) {
                $this->preferredDay = $quoteSelections['enabled']->getInputValue();
            }
        }

        $this->fee = $this->moduleConfig->getPreferredDayAdditionalCharge($this->storeManager->getStore()->getId());
    }

    /**
     * Updates the preferred day for delivery.
     * 
     * @param $value
     * @return mixed
     */
    public function updatedPreferredDay($value): mixed
    {
        return $this->persistFieldUpdate(
            'date', 
            $value, 
            Codes::SERVICE_OPTION_PREFERRED_DAY
        );
    }
}
