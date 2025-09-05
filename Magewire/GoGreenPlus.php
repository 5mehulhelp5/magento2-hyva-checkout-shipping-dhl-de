<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\Config\ModuleConfig;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for managing DHL GoGreen Plus option.
 *
 * Handles the enabling, fee calculation, and state persistence for the GoGreen Plus shipping service.
 */
class GoGreenPlus extends ShippingOptions
{
    /**
     * Indicates if GoGreen Plus is enabled.
     *
     * @var bool
     */
    public bool $goGreenPlusEnabled = false;

    /**
     * The additional fee for GoGreen Plus service.
     *
     * @var float
     */
    public float $fee = 0.0;

    /**
     * Indicates if the option is disabled (e.g. due to configuration or address).
     *
     * @var bool
     */
    public bool $disabled = false;

    /**
     * Lifecycle method called on component mount.
     * Loads current selection and fetches the configured fee.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(DhlCodes::SERVICE_OPTION_GOGREEN_PLUS);

        if ($quoteSelections && isset($quoteSelections['enabled'])) {
            $this->goGreenPlusEnabled = (bool) $quoteSelections['enabled']->getInputValue();
        }

        $this->fee = (float) $this->scopeConfig->getValue(ModuleConfig::CONFIG_PATH_GOGREEN_PLUS_CHARGE);
    }

    /**
     * Handler called when the GoGreen Plus state is updated.
     * Triggers a refresh of the price summary and persists the change.
     *
     * @param bool $value The new enabled/disabled state.
     * @return mixed Result of field persistence operation.
     */
    public function updatedGoGreenPlusEnabled(bool $value): mixed
    {
        $this->emitToRefresh('price-summary.total-segments');
        return $this->persistFieldUpdate('enabled', $value, DhlCodes::SERVICE_OPTION_GOGREEN_PLUS);
    }
}
