<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for managing the DHL "Preferred Neighbor" delivery option.
 *
 * Handles enabling/disabling the option, storing neighbor name/address,
 * exclusive state management, and persistence of input values.
 */
class PreferredNeighbor extends ShippingOptions
{
    /**
     * The name of the preferred neighbor for delivery (or empty string if unset).
     *
     * @var string
     */
    public string $preferredNeighborName = '';

    /**
     * The address of the preferred neighbor for delivery (or empty string if unset).
     *
     * @var string
     */
    public string $preferredNeighborAddress = '';

    /**
     * Whether the preferred neighbor option is currently disabled (because another exclusive service is active).
     *
     * @var bool
     */
    public bool $disabled = false;

    /**
     * Event listeners for this component.
     *
     * @var array<string, string>
     */
    protected $listeners = [
        'activeServiceChanged' => 'onActiveServiceChanged',
    ];

    /**
     * Lifecycle method called on component mount.
     * Loads the neighbor name and address, and requests exclusivity if set.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(DhlCodes::SERVICE_OPTION_NEIGHBOR_DELIVERY);

        if ($quoteSelections) {
            $this->preferredNeighborName    = (string)($quoteSelections['name']->getInputValue()    ?? '');
            $this->preferredNeighborAddress = (string)($quoteSelections['address']->getInputValue() ?? '');

            if ($this->preferredNeighborName !== '' || $this->preferredNeighborAddress !== '') {
                // Request exclusive activation for this service.
                $this->emitUp('requestExclusive', 'preferredNeighbor');
            }
        }
        
        if (!$this->preferredNeighborName && !$this->preferredNeighborAddress) {
            $this->checkForOtherActiveServices();
        }
    }

    /**
     * Handles changes to the active exclusive service.
     * Disables this option and resets its values if another exclusive service becomes active.
     *
     * @param string|null $activeService The currently active exclusive service, or null.
     * @return void
     */
    public function onActiveServiceChanged(?string $activeService = null): void
    {
        $shouldReset = $activeService !== 'preferredNeighbor'
            && ($this->preferredNeighborName !== '' || $this->preferredNeighborAddress !== '');
        if ($shouldReset) {
            $this->resetFields();
        }
        $this->disabled = ($activeService !== null && $activeService !== 'preferredNeighbor');
    }

    /**
     * Resets the neighbor name and address fields, persists the changes,
     * and releases exclusive access.
     *
     * @return void
     */
    public function resetFields(): void
    {
        $this->preferredNeighborName = '';
        $this->preferredNeighborAddress = '';
        $this->persistFieldUpdate('name', '', DhlCodes::SERVICE_OPTION_NEIGHBOR_DELIVERY);
        $this->persistFieldUpdate('address', '', DhlCodes::SERVICE_OPTION_NEIGHBOR_DELIVERY);
        $this->emitUp('releaseExclusive', 'preferredNeighbor');
    }

    /**
     * Handler for when the preferredNeighborName property is updated.
     * Persists the value and updates exclusive state.
     *
     * @param string $value The new neighbor name.
     * @return string The persisted neighbor name.
     */
    public function updatedPreferredNeighborName(string $value): string
    {
        $active = ($value !== '') || ($this->preferredNeighborAddress !== '');
        $this->persistFieldUpdate('name', $value, DhlCodes::SERVICE_OPTION_NEIGHBOR_DELIVERY);
        $this->emitUp($active ? 'requestExclusive' : 'releaseExclusive', 'preferredNeighbor');
        return $value;
    }

    /**
     * Handler for when the preferredNeighborAddress property is updated.
     * Persists the value and updates exclusive state.
     *
     * @param string $value The new neighbor address.
     * @return string The persisted neighbor address.
     */
    public function updatedPreferredNeighborAddress(string $value): string
    {
        $active = ($this->preferredNeighborName !== '') || ($value !== '');
        $this->persistFieldUpdate('address', $value, DhlCodes::SERVICE_OPTION_NEIGHBOR_DELIVERY);
        $this->emitUp($active ? 'requestExclusive' : 'releaseExclusive', 'preferredNeighbor');
        return $value;
    }
    
    /**
     * Checks on page load if another exclusive service is already active in the quote.
     * If so, this component disables itself immediately without waiting for events.
     */
    private function checkForOtherActiveServices(): void
    {
        $otherExclusiveServices = [
            DhlCodes::SERVICE_OPTION_DROPOFF_DELIVERY,
            DhlCodes::SERVICE_OPTION_NO_NEIGHBOR_DELIVERY,
        ];
        
        foreach ($otherExclusiveServices as $serviceCode) {
            $selections = $this->loadFromDb($serviceCode);
            if ($this->selectionsHaveValue($selections)) {
                $this->disabled = true;
                return;
            }
        }
    }
}
