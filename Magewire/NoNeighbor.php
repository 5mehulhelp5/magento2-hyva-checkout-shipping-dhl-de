<?php

declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\Config\ModuleConfig;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

class NoNeighbor extends ShippingOptions
{
    /**
     * @var bool
     */
    public bool $noNeighbor = false;

    /**
     * @var bool
     */
    public bool $disabled = false;

    /**
     * @var float
     */
    public float $fee = 0.0;

    /**
     * @var string[]
     */
    protected $listeners = [
        'updated_preferred_neighbor' => 'listenPreferredNeighbor'
    ];

    /**
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
     * @return void
     */
    protected function dispatchEmit(): void
    {
        $this->emit('updated_no_neighbor', [
            'noNeighbor' => $this->noNeighbor
        ]);
    }

    /**
     * @param $value
     * @return mixed
     */
    public function updatedNoNeighbor($value): mixed
    {
        $this->dispatchEmit();
        
        // KORREKTUR: Aufräumen von redundantem Code
        return $this->persistFieldUpdate(
            'enabled',
            $value,
            Codes::SERVICE_OPTION_NO_NEIGHBOR_DELIVERY
        );
    }
}