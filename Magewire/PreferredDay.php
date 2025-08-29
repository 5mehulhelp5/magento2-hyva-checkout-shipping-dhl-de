<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Dhl\Paket\Model\Config\ModuleConfig;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for managing the DHL "Preferred Day" delivery option.
 *
 * Handles the selection, validation, and exclusive state management for choosing a preferred delivery date.
 */
class PreferredDay extends ShippingOptions
{
    /**
     * Selected preferred delivery day (Y-m-d format) or null if not set.
     *
     * @var string|null
     */
    public ?string $preferredDay = null;

    /**
     * The additional fee for the Preferred Day service.
     *
     * @var float
     */
    public float $fee = 0.0;

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
     * Loads the preferred day selection and fee, and requests exclusivity if set.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(DhlCodes::SERVICE_OPTION_PREFERRED_DAY);

        if ($quoteSelections && isset($quoteSelections['date'])) {
            $val = (string)$quoteSelections['date']->getInputValue();
            $this->preferredDay = ($val !== '') ? $val : null;
            if ($this->preferredDay) {
                // Request exclusive activation for this service.
                $this->emitUp('requestExclusive', 'preferredDay');
            }
        }

        $this->fee = (float)$this->scopeConfig->getValue(ModuleConfig::CONFIG_PATH_PREFERRED_DAY_CHARGE);
    }

    /**
     * Handles changes to the active exclusive service.
     * Clears this component's value if another exclusive service becomes active.
     *
     * @param string|null $activeService The currently active exclusive service, or null.
     * @return void
     */
    public function onActiveServiceChanged(?string $activeService = null): void
    {
        if ($activeService !== 'preferredDay' && $this->preferredDay !== null) {
            $this->clearValuesAndPersist();
        }
    }

    /**
     * Clears the preferred day value and releases exclusive access.
     *
     * @return void
     */
    public function clearValuesAndPersist(): void
    {
        $this->preferredDay = null;
        $this->persistFieldUpdate('date', '', DhlCodes::SERVICE_OPTION_PREFERRED_DAY);
        $this->emitUp('releaseExclusive', 'preferredDay');
    }

    /**
     * Handler for when the preferredDay property is updated.
     * Persists the new value, updates exclusivity, and refreshes the price summary.
     *
     * @param string|null $value The new preferred day (Y-m-d format) or null.
     * @return mixed Result of the field persistence operation.
     */
    public function updatedPreferredDay(?string $value): mixed
    {
        $res = $this->persistFieldUpdate('date', $value ?? '', DhlCodes::SERVICE_OPTION_PREFERRED_DAY);
        $this->emitUp(!empty($value) ? 'requestExclusive' : 'releaseExclusive', 'preferredDay');
        $this->emitToRefresh('price-summary.total-segments');
        return $res;
    }
}
