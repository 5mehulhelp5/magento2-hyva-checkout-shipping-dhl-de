<?php

declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\Config\ModuleConfig;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

class PreferredLocation extends ShippingOptions
{
    /**
     * @var string
     */
    public string $preferredLocation = '';

    /**
     * @var bool
     */
    public bool $disabled = false;

    /**
     * @var string[]
     */
    protected $listeners = [
        'updated_preferred_neighbor' => 'listenPreferredNeighbor'
    ];

    /**
     * Initializes the component by loading existing preferred drop-off location selection from the database.
     */
    public function mount(): void
    {
        /** @var $quoteSelection SelectionInterface */
        $quoteSelections = $this->loadFromDb(Codes::SERVICE_OPTION_DROPOFF_DELIVERY);

        if ($quoteSelections) {
            if (isset($quoteSelections['details'])) {
                $this->preferredLocation = $quoteSelections['details']->getInputValue();
            }
        }
    }

    /**
     * Dispatches an event to notify other components about the current preferred location state.
     *
     * @return void
     */
    protected function dispatchEmit(): void
    {
        $this->emit('updated_preferred_location', ['preferredLocation' => $this->preferredLocation]);
    }

    /**
     * Initializes the component's state and dispatches the initial preferred location.
     * This method is called after mount, for client-side hydration.
     *
     * @return void
     */
    public function init(): void
    {
        $this->dispatchEmit();
    }

    /**
     * @param array $value
     * @return void
     */
    public function listenPreferredNeighbor(array $value): void
    {
        $hasNeighborName = !empty($value['preferredNeighborName']);
        $hasNeighborAddress = !empty($value['preferredNeighborAddress']);

        $this->disabled = ($hasNeighborName || $hasNeighborAddress);
    }

    /**
     * Updates the preferred location for drop-off delivery.
     *
     * @param string $value
     * @return string // Changed from mixed to string for more precision
     */
    public function updatedPreferredLocation(string $value): string
    {
        // Emit the event
        $this->dispatchEmit();

        // Use the persistFieldUpdate method to update the field
        return $this->persistFieldUpdate(
            'details', 
            $value, 
            Codes::SERVICE_OPTION_DROPOFF_DELIVERY
        );
    }
}
