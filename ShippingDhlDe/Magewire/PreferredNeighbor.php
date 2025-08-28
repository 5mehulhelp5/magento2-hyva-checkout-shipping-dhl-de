<?php

declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for DHL Preferred Neighbor option.
 */
class PreferredNeighbor extends ShippingOptions
{
    /**
     * @var string Name of the preferred neighbor.
     */
    public string $preferredNeighborName = '';

    /**
     * @var string Address of the preferred neighbor.
     */
    public string $preferredNeighborAddress = '';

    /**
     * @var bool If true, disables the component inputs.
     */
    public bool $disabled = false;

    /**
     * @var array Listens for reset events from the parent component.
     */
    protected $listeners = [
        'resetYourself' => 'resetFields',
    ];

    /**
     * Loads initial values from the quote on mount.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(Codes::SERVICE_OPTION_NEIGHBOR_DELIVERY);

        if ($quoteSelections) {
            if (isset($quoteSelections['name'])) {
                $this->preferredNeighborName = (string) $quoteSelections['name']->getInputValue();
            }
            if (isset($quoteSelections['address'])) {
                $this->preferredNeighborAddress = (string) $quoteSelections['address']->getInputValue();
            }
        }
    }

    /**
     * Resets all neighbor fields and persists the empty state.
     *
     * @return void
     */
    public function resetFields(): void
    {
        $this->preferredNeighborName = '';
        $this->preferredNeighborAddress = '';
        $this->persistFieldUpdate('name', '', Codes::SERVICE_OPTION_NEIGHBOR_DELIVERY);
        $this->persistFieldUpdate('address', '', Codes::SERVICE_OPTION_NEIGHBOR_DELIVERY);
    }

    /**
     * Called when the neighbor name is updated.
     *
     * @param string $value
     * @return string
     */
    public function updatedPreferredNeighborName(string $value): string
    {
        $isActive = !empty($value) || !empty($this->preferredNeighborAddress);
        // Inform the parent that this exclusive service has changed.
        $this->emitUp('exclusiveServiceUpdated', 'preferredNeighbor', $isActive, [
            'name'    => $value,
            'address' => $this->preferredNeighborAddress,
        ]);
        // Persist the new value.
        return $this->persistFieldUpdate('name', $value, Codes::SERVICE_OPTION_NEIGHBOR_DELIVERY);
    }

    /**
     * Called when the neighbor address is updated.
     *
     * @param string $value
     * @return string
     */
    public function updatedPreferredNeighborAddress(string $value): string
    {
        $isActive = !empty($this->preferredNeighborName) || !empty($value);
        // Inform the parent that this exclusive service has changed.
        $this->emitUp('exclusiveServiceUpdated', 'preferredNeighbor', $isActive, [
            'name'    => $this->preferredNeighborName,
            'address' => $value,
        ]);
        // Persist the new value.
        return $this->persistFieldUpdate('address', $value, Codes::SERVICE_OPTION_NEIGHBOR_DELIVERY);
    }
}
