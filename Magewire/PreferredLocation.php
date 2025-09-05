<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for managing the DHL "Preferred Location" (Drop-off Delivery) option.
 *
 * Handles enabling/disabling the option, exclusive service state, and persisting the location value.
 */
class PreferredLocation extends ShippingOptions
{
    /**
     * The customer-selected preferred drop-off location (or empty string if unset).
     *
     * @var string
     */
    public string $preferredLocation = '';

    /**
     * Whether the preferred location option is currently disabled (because another exclusive service is active).
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
     * Loads the current preferred location value and requests exclusivity if set.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(DhlCodes::SERVICE_OPTION_DROPOFF_DELIVERY);

        if ($quoteSelections && isset($quoteSelections['details'])) {
            $this->preferredLocation = (string)$quoteSelections['details']->getInputValue();
            if ($this->preferredLocation !== '') {
                // Request exclusive activation for this service.
                $this->emitUp('requestExclusive', 'preferredLocation');
            }
        }
        
        if ($this->preferredLocation === '') {
            $this->checkForOtherActiveServices();
        }
    }

    /**
     * Handles changes to the active exclusive service.
     * Disables this option and clears its value if another exclusive service becomes active.
     *
     * @param string|null $activeService The currently active exclusive service, or null.
     * @return void
     */
    public function onActiveServiceChanged(?string $activeService = null): void
    {
        if ($activeService !== 'preferredLocation' && $this->preferredLocation !== '') {
            $this->clearValue();
        }
        $this->disabled = ($activeService !== null && $activeService !== 'preferredLocation');
    }

    /**
     * Clears the preferred location value and releases exclusive access.
     *
     * @return void
     */
    public function clearValue(): void
    {
        $this->preferredLocation = '';
        $this->persistFieldUpdate('details', '', DhlCodes::SERVICE_OPTION_DROPOFF_DELIVERY);
        $this->emitUp('releaseExclusive', 'preferredLocation');
    }

    /**
     * Handler for when the preferredLocation property is updated.
     * Persists the new value and updates exclusive state.
     *
     * @param string $value The new preferred location value.
     * @return mixed Result of the field persistence operation.
     */
    public function updatedPreferredLocation(string $value): mixed
    {
        $res = $this->persistFieldUpdate('details', $value, DhlCodes::SERVICE_OPTION_DROPOFF_DELIVERY);
        $this->emitUp($value !== '' ? 'requestExclusive' : 'releaseExclusive', 'preferredLocation');
        return $res;
    }
    
    /**
     * Checks on page load if another exclusive service is already active in the quote.
     * If so, this component disables itself immediately without waiting for events.
     */
    private function checkForOtherActiveServices(): void
    {
        $otherExclusiveServices = [
            DhlCodes::SERVICE_OPTION_NEIGHBOR_DELIVERY,
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
