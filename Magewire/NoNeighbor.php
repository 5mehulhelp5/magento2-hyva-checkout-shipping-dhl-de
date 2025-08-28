<?php

declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\Config\ModuleConfig;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for DHL "No Neighbor" delivery option.
 */
class NoNeighbor extends ShippingOptions
{
    /**
     * @var bool Whether the "No Neighbor" option is enabled.
     */
    public bool $noNeighbor = false;

    /**
     * @var float Additional fee for the "No Neighbor" service.
     */
    public float $fee = 0.0;

    /**
     * @var bool If true, disables the input in the UI (controlled by parent).
     */
    public bool $disabled = false; 

    /**
     * @var string[] Listens for reset event from parent component.
     */
    protected $listeners = [
        'resetYourself' => 'clearValue'
    ];

    /**
     * Loads the initial value and fee from the quote/config.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(Codes::SERVICE_OPTION_NO_NEIGHBOR_DELIVERY);

        if ($quoteSelections && isset($quoteSelections['enabled'])) {
            $this->noNeighbor = (bool) $quoteSelections['enabled']->getInputValue();
        }

        $this->fee = (float) $this->scopeConfig->getValue(ModuleConfig::CONFIG_PATH_NO_NEIGHBOR_DELIVERY_CHARGE);
    }
    
    /**
     * Resets the state and persists the change.
     *
     * @return void
     */
    public function clearValue(): void
    {
        $this->noNeighbor = false;
        $this->persistFieldUpdate('enabled', false, Codes::SERVICE_OPTION_NO_NEIGHBOR_DELIVERY);
    }

    /**
     * Handles changes to the "No Neighbor" checkbox.
     * Persists the value, notifies the parent, and refreshes totals.
     *
     * @param bool $value
     * @return mixed
     */
    public function updatedNoNeighbor(bool $value): mixed
    {
        $result = $this->persistFieldUpdate(
            'enabled',
            $value,
            Codes::SERVICE_OPTION_NO_NEIGHBOR_DELIVERY
        );

        $this->emitUp('exclusiveServiceUpdated', 'noNeighbor', $value);
        $this->emitToRefresh('price-summary.total-segments');

        return $result;
    }
}
