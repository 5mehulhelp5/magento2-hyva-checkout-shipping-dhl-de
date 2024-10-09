<?php

declare(strict_types=1);

namespace Hyva\HyvaShippingDhl\Magewire;

use Magewirephp\Magewire\Component;
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
     * Mount the component.
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
     * @return void
     */
    protected function dispatchEmit(): void
    {
        $this->emit('updated_preferred_location', ['preferredLocation' => $this->preferredLocation]);
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
    public function listenPreferredNeighbor(array $value): void
    {
        $this->disabled = (!empty($value['preferredNeighborName'] || !empty($value['preferredNeighborAddress'])));
    }

    /**
     * Updates the preferred location for drop-off delivery.
     * 
     * @param string $value
     * @return mixed
     */
    public function updatedPreferredLocation(string $value): mixed
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
