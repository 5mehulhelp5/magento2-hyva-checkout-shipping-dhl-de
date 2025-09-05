<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Dhl\Paket\Model\Config\ModuleConfig;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for managing the DHL "No Neighbor Delivery" option.
 *
 * Handles enabling/disabling, fee calculation, state synchronization, and
 * exclusive option management for the "No Neighbor" shipping service.
 */
class NoNeighbor extends ShippingOptions
{
    /**
     * Indicates if "No Neighbor Delivery" is enabled.
     *
     * @var bool
     */
    public bool $noNeighbor = false;

    /**
     * The additional fee for the "No Neighbor Delivery" service.
     *
     * @var float
     */
    public float $fee = 0.0;

    /**
     * Whether the option is currently disabled (because another exclusive option is active).
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
     * Lifecycle method called when the component is mounted.
     * Loads current selection state and fee, and manages exclusivity if needed.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(DhlCodes::SERVICE_OPTION_NO_NEIGHBOR_DELIVERY);

        if ($quoteSelections && isset($quoteSelections['enabled'])) {
            $this->noNeighbor = (bool)$quoteSelections['enabled']->getInputValue();
            if ($this->noNeighbor) {
                // Request exclusivity for this option if already enabled.
                $this->emitUp('requestExclusive', 'noNeighbor');
            }
        }

        $this->fee = (float)$this->scopeConfig->getValue(ModuleConfig::CONFIG_PATH_NO_NEIGHBOR_DELIVERY_CHARGE);
        
        if (!$this->noNeighbor) {
            $this->checkForOtherActiveServices();
        }
    }

    /**
     * Handler for active service change events.
     * Disables this option and clears its value if another exclusive option is active.
     *
     * @param string|null $activeService The name of the currently active exclusive service.
     * @return void
     */
    public function onActiveServiceChanged(?string $activeService = null): void
    {
        if ($activeService !== 'noNeighbor' && $this->noNeighbor) {
            $this->clearValue();
        }
        $this->disabled = ($activeService !== null && $activeService !== 'noNeighbor');
    }

    /**
     * Clears the value of this service and releases exclusivity.
     *
     * @return void
     */
    public function clearValue(): void
    {
        $this->noNeighbor = false;
        $this->persistFieldUpdate('enabled', false, DhlCodes::SERVICE_OPTION_NO_NEIGHBOR_DELIVERY);
        $this->emitUp('releaseExclusive', 'noNeighbor');
    }

    /**
     * Handler for when the noNeighbor property is updated.
     * Persists the value, updates exclusivity, and triggers a refresh of the price summary.
     *
     * @param bool $value The new state for "No Neighbor Delivery".
     * @return mixed Result of the field persistence operation.
     */
    public function updatedNoNeighbor(bool $value): mixed
    {
        $res = $this->persistFieldUpdate('enabled', $value, DhlCodes::SERVICE_OPTION_NO_NEIGHBOR_DELIVERY);
        $this->emitUp($value ? 'requestExclusive' : 'releaseExclusive', 'noNeighbor');
        $this->emitToRefresh('price-summary.total-segments');
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
