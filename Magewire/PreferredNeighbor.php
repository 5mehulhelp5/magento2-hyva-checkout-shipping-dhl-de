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
     * @return void
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
     * @return mixed
     */
    public function updatedPreferredNeighborName(string $value): mixed
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
     * @return mixed
     */
    public function updatedPreferredNeighborAddress(string $value): mixed
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
