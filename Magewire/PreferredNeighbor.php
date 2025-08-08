<?php

declare(strict_types=1);

namespace Hyva\HyvaShippingDhl\Magewire;

use Magewirephp\Magewire\Component;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

class PreferredNeighbor extends ShippingOptions
{
    /**
     * @var string
     */
    public string $preferredNeighborName = '';

    /**
     * @var string
     */
    public string $preferredNeighborAddress = '';

    /**
     * @var bool
     */
    public bool $disabled = false;

    /**
     * @var string[]
     */
    protected $listeners = [
        'updated_preferred_location' => 'listenPreferredLocation',
        'updated_no_neighbor' => 'listenNoNeighbor',
    ];

    /**
     * Initializes the component by loading existing preferred neighbor details from the database.
     */
    public function mount(): void
    {
        /** @var $quoteSelection SelectionInterface */
        $quoteSelections = $this->loadFromDb(Codes::SERVICE_OPTION_NEIGHBOR_DELIVERY);

        if ($quoteSelections) {
            if (isset($quoteSelections['name'])) {
                $this->preferredNeighborName = $quoteSelections['name']->getInputValue();
            }

            if (isset($quoteSelections['address'])) {
                $this->preferredNeighborAddress = $quoteSelections['address']->getInputValue();
            }
        }
    }

    /**
     * Dispatches an event to notify other components about the current preferred neighbor details.
     *
     * @return void
     */
    protected function dispatchEmit(): void
    {
        $this->emit('updated_preferred_neighbor', [
            'preferredNeighborName' => $this->preferredNeighborName,
            'preferredNeighborAddress' => $this->preferredNeighborAddress
        ]);
    }

    /**
     * Initializes the component's state and dispatches the initial preferred neighbor details.
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
    public function listenPreferredLocation(array $value): void
    {
        $this->disabled = !empty($value['preferredLocation']);
    }

    /**
     * @param array $value
     * @return void
     */
    public function listenNoNeighbor(array $value): void
    {
        $this->disabled = $value['noNeighbor'];
    }

    /**
     * Updates the preferred neighbor's name.
     *
     * @param string $value
     * @return string // Changed from mixed to string for more precision
     */
    public function updatedPreferredNeighborName(string $value): string
    {
        // Emit the event
        $this->dispatchEmit();

        return $this->persistFieldUpdate(
            'name',
            $value,
            Codes::SERVICE_OPTION_NEIGHBOR_DELIVERY
        );
    }

    /**
     * Updates the preferred neighbor's address.
     *
     * @param string $value
     * @return string // Changed from mixed to string for more precision
     */
    public function updatedPreferredNeighborAddress(string $value): string
    {
        // Emit the event
        $this->dispatchEmit();

        return $this->persistFieldUpdate(
            'address',
            $value,
            Codes::SERVICE_OPTION_NEIGHBOR_DELIVERY
        );
    }
}
