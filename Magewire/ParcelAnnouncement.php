<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for managing the DHL "Parcel Announcement" shipping option.
 *
 * Handles enabling/disabling the option and persisting its state.
 */
class ParcelAnnouncement extends ShippingOptions
{
    /**
     * Indicates whether the "Parcel Announcement" service is enabled.
     *
     * @var bool
     */
    public bool $parcelAnnouncement = false;

    /**
     * Lifecycle method called when the component is mounted.
     * Loads the selection state for the "Parcel Announcement" option.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(DhlCodes::SERVICE_OPTION_PARCEL_ANNOUNCEMENT);

        if ($quoteSelections && isset($quoteSelections['enabled'])) {
            $this->parcelAnnouncement = (bool)$quoteSelections['enabled']->getInputValue();
        }
    }

    /**
     * Handler for when the parcelAnnouncement property is updated.
     * Persists the new value for the "Parcel Announcement" shipping option.
     *
     * @param bool $value The new enabled/disabled state.
     * @return mixed Result of the field persistence operation.
     */
    public function updatedParcelAnnouncement(bool $value): mixed
    {
        return $this->persistFieldUpdate('enabled', $value, DhlCodes::SERVICE_OPTION_PARCEL_ANNOUNCEMENT);
    }
}
