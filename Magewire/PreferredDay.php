<?php

declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes;
use Dhl\Paket\Model\Config\ModuleConfig; // Hinzugefügt

class PreferredDay extends ShippingOptions
{
    /**
     * @var string|null
     */
    public ?string $preferredDay = null;

    /**
     * @var float
     */
    public float $fee = 0.0;

    /**
     * Mount the component.
     */
    public function mount() {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(Codes::SERVICE_OPTION_PREFERRED_DAY);

        if ($quoteSelections && isset($quoteSelections['enabled'])) {
            $this->preferredDay = $quoteSelections['enabled']->getInputValue();
        }

        $this->fee = (float) $this->scopeConfig->getValue(ModuleConfig::CONFIG_PATH_PREFERRED_DAY_CHARGE);
    }

    /**
     * @return void
     */
    public function init(): void
    {
        $this->dispatchEmit();
    }

    /**
     * @return void
     */
    protected function dispatchEmit(): void
    {
        
    }
    
    /**
     * Updates the preferred day for delivery.
     *
     * @param ?string $value
     * @return mixed
     */
    public function updatedPreferredDay(?string $value): mixed
    {
        $this->dispatchEmit();

        $result = $this->persistFieldUpdate(
            'enabled',
            $value,
            Codes::SERVICE_OPTION_PREFERRED_DAY
        );

        $this->emit('shipping_address_saved');

        return $result;
    }
}
