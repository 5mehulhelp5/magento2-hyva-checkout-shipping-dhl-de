<?php

declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes;
use Dhl\Paket\Model\Config\ModuleConfig;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for DHL "Preferred Day" delivery option.
 */
class PreferredDay extends ShippingOptions
{
    /**
     * @var string|null Selected preferred delivery day (Y-m-d) or null if not set.
     */
    public ?string $preferredDay = null;

    /**
     * @var float Fee for selecting a preferred delivery day.
     */
    public float $fee = 0.0;

    /**
     * @var string[] Listens for the parent's reset event.
     */
    protected $listeners = [
        'resetYourself' => 'clearValuesAndPersist',
    ];

    /**
     * Loads the current value and fee from the quote/config.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(Codes::SERVICE_OPTION_PREFERRED_DAY);

        if ($quoteSelections && isset($quoteSelections['enabled'])) {
            $this->preferredDay = $quoteSelections['enabled']->getInputValue();
        }

        $this->fee = (float) $this->scopeConfig->getValue(ModuleConfig::CONFIG_PATH_PREFERRED_DAY_CHARGE);
    }

    /**
     * Resets the value and persists the change.
     * Called from parent via event.
     *
     * @return void
     */
    public function clearValuesAndPersist(): void
    {
        $this->preferredDay = null;
        $this->persistFieldUpdate('date', '', Codes::SERVICE_OPTION_PREFERRED_DAY);
    }

    /**
     * Handles changes to the preferred day selection.
     * Persists the value, notifies the parent, and refreshes totals.
     *
     * @param string|null $value
     * @return mixed
     */
    public function updatedPreferredDay(?string $value): mixed
    {
        // Save the selected date (or empty string if null)
        $result = $this->persistFieldUpdate(
            'date',
            $value ?? '',
            Codes::SERVICE_OPTION_PREFERRED_DAY
        );

        // Notify parent if the option is now active
        $this->emitUp('exclusiveServiceUpdated', 'preferredDay', !empty($value));

        // Refresh price summary segment
        $this->emitToRefresh('price-summary.total-segments');

        return $result;
    }
}
