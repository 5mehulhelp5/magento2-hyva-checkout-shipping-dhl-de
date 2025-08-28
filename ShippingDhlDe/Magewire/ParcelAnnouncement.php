<?php

declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for DHL Parcel Announcement service.
 */
class ParcelAnnouncement extends ShippingOptions
{
    /**
     * @var bool Whether parcel announcement is enabled.
     */
    public bool $parcelAnnouncement = false;

    /**
     * Loads the initial value from the quote on mount.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(Codes::SERVICE_OPTION_PARCEL_ANNOUNCEMENT);

        if ($quoteSelections && isset($quoteSelections['enabled'])) {
            $this->parcelAnnouncement = (bool) $quoteSelections['enabled']->getInputValue();
        }
    }

    /**
     * Handles changes to the parcel announcement state.
     * Persists the value and notifies the parent component.
     *
     * @param bool $value
     * @return mixed
     */
    public function updatedParcelAnnouncement(bool $value): mixed
    {
        $result = $this->persistFieldUpdate('enabled', $value, Codes::SERVICE_OPTION_PARCEL_ANNOUNCEMENT);
        $this->emitUp('childStateUpdated', 'parcelAnnouncement', $value);
        return $result;
    }
}
