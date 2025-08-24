<?php

declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\Config\ModuleConfig;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

class ParcelAnnouncement extends ShippingOptions
{
    /**
     * @var bool
     */
    public bool $parcelAnnouncement = false;

    /**
     * Initializes the component by loading existing parcel announcement selection from the database.
     */
    public function mount(): void
    {
        /** @var $quoteSelection SelectionInterface */
        $quoteSelections = $this->loadFromDb(Codes::SERVICE_OPTION_PARCEL_ANNOUNCEMENT);

        if ($quoteSelections) {
            if (isset($quoteSelections['enabled'])) {
                $this->parcelAnnouncement = (bool) $quoteSelections['enabled']->getInputValue();
            }
        }
    }

    /**
     * Updates the parcel announcement state.
     * 
     * @param bool $value
     * @return bool
     */
    public function updatedParcelAnnouncement(bool $value): mixed
    {
        return $this->persistFieldUpdate(
            'enabled', 
            $value, 
            Codes::SERVICE_OPTION_PARCEL_ANNOUNCEMENT
        );
    }
}
