<?php

declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\Config\ModuleConfig;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

class GoGreenPlus extends ShippingOptions
{
    /**
     * Holds the state of the GoGreen Plus checkbox.
     *
     * @var bool
     */
    public bool $goGreenPlusEnabled = false;

    /**
     * Holds the additional charge for the GoGreen Plus service.
     *
     * @var float
     */
    public float $fee = 0.0;

    /**
     * Determines if the component's inputs should be disabled.
     *
     * @var bool
     */
    public bool $disabled = false;

    /**
     * Event listeners for this component.
     *
     * @var string[]
     */
    protected $listeners = [
        // Currently, there are no events to listen for.
        // This structure is ready for future enhancements.
    ];

    /**
     * Loads the initial state of the component.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(Codes::SERVICE_OPTION_GOGREEN_PLUS);

        if ($quoteSelections && isset($quoteSelections['enabled'])) {
            $this->goGreenPlusEnabled = (bool) $quoteSelections['enabled']->getInputValue();
        }

        $this->fee = (float) $this->scopeConfig->getValue(ModuleConfig::CONFIG_PATH_GOGREEN_PLUS_CHARGE);
    }

    /**
     * Can be called to dispatch the component's state to other components.
     *
     * @return void
     */
    public function init(): void
    {
        $this->dispatchEmit();
    }

    /**
     * Emits the current state of this component as an event.
     *
     * @return void
     */
    protected function dispatchEmit(): void
    {
        $this->emit('updated_gogreen_plus', [
            'goGreenPlusEnabled' => $this->goGreenPlusEnabled
        ]);
    }

    /**
     * Called when the checkbox is changed in the frontend.
     *
     * @param bool $value
     * @return mixed
     */
    public function updatedGoGreenPlusEnabled(bool $value): mixed
    {
        $this->dispatchEmit();

        $result = $this->persistFieldUpdate(
            'enabled',
            $value,
            Codes::SERVICE_OPTION_GOGREEN_PLUS
        );

        $this->emit('shipping_address_saved');

        return $result;
    }
}
