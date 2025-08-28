<?php

declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for DHL Preferred Location (drop-off delivery).
 */
class PreferredLocation extends ShippingOptions
{
    /**
     * @var string The preferred drop-off location.
     */
    public string $preferredLocation = '';

    /**
     * @var bool If true, disables the input.
     */
    public bool $disabled = false;

    /**
     * @var array Event listeners for this component.
     */
    protected $listeners = [
        'resetYourself' => 'clearValue'
    ];

    /**
     * Loads the initial value from the quote on mount.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(Codes::SERVICE_OPTION_DROPOFF_DELIVERY);

        if ($quoteSelections && isset($quoteSelections['details'])) {
            $this->preferredLocation = (string) $quoteSelections['details']->getInputValue();
        }
    }

    /**
     * Clears the value and persists an empty state.
     *
     * @return void
     */
    public function clearValue(): void
    {
        $this->preferredLocation = '';
        $this->persistFieldUpdate('details', '', Codes::SERVICE_OPTION_DROPOFF_DELIVERY);
    }

    /**
     * Called when the location input is updated.
     * Emits state to parent and persists the value.
     *
     * @param string $value
     * @return mixed
     */
    public function updatedPreferredLocation(string $value): mixed
    {
        $result = $this->persistFieldUpdate(
            'details',
            $value,
            Codes::SERVICE_OPTION_DROPOFF_DELIVERY
        );
        $isActive = !empty($value);

        // Notify parent about active state and value.
        $this->emitUp('exclusiveServiceUpdated', 'preferredLocation', $isActive, [
            'location' => $value,
        ]);
        return $result;
    }
}
