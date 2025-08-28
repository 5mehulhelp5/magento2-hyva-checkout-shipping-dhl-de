<?php

declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Codes as CoreCodes;
use Hyva\ShippingDhlDe\Magewire\NoNeighbor;
use Hyva\ShippingDhlDe\Magewire\ParcelPackstation;
use Hyva\ShippingDhlDe\Magewire\PreferredDay;
use Hyva\ShippingDhlDe\Magewire\PreferredLocation;
use Hyva\ShippingDhlDe\Magewire\PreferredNeighbor;

/**
 * Central Magewire component managing DHL delivery options during checkout.
 */
class DeliveryOptions extends ShippingOptions
{
    /**
     * @var bool Indicates if the shipping address is within Germany.
     */
    public bool $isShippingAddressValid = false;

    /**
     * @var string|null The currently active exclusive DHL service (e.g. 'preferredLocation').
     */
    public ?string $activeService = null;

    /**
     * @var string|null Preferred location value for dropoff delivery.
     */
    public ?string $preferredLocation = null;

    /**
     * @var string|null Neighbor's name for neighbor delivery.
     */
    public ?string $preferredNeighborName = null;

    /**
     * @var string|null Neighbor's address for neighbor delivery.
     */
    public ?string $preferredNeighborAddress = null;

    /**
     * @var string|null Preferred day for delivery.
     */
    public ?string $preferredDay = null;

    /**
     * @var bool Indicates if 'No Neighbor' delivery option is active.
     */
    public bool $noNeighbor = false;

    /**
     * @var array Stores Packstation data if available.
     */
    public array $packstationData = [];

    /**
     * @var bool GoGreen Plus service state.
     */
    public bool $goGreenPlus = false;

    /**
     * @var bool Parcel Announcement service state.
     */
    public bool $parcelAnnouncement = false;

    /**
     * @var array Listens to events from children and checkout.
     */
    protected $listeners = [
        'shipping_address_saved'      => 'checkCountryValidity',
        'guest_shipping_address_saved'=> 'checkCountryValidity',
        'childStateUpdated'           => 'handleChildStateUpdate',
        'exclusiveServiceUpdated'     => 'handleExclusiveServiceUpdate'
    ];

    /**
     * Component mount: Checks address and loads initial quote data.
     *
     * @return void
     */
    public function mount(): void
    {
        $this->checkCountryValidity();
        // Load all initial values from the current quote (DB)
        $this->loadAllValuesFromQuote();
    }

    /**
     * Activates an exclusive service and resets all others.
     *
     * @param string $serviceName
     * @param bool $isActive
     * @return void
     */
    private function setActiveService(string $serviceName, bool $isActive): void
    {
        if ($isActive) {
            $this->activeService = $serviceName;

            // Reset all other exclusive child components
            if ($serviceName !== 'preferredLocation') { $this->emitTo(PreferredLocation::class, 'resetYourself'); }
            if ($serviceName !== 'preferredNeighbor') { $this->emitTo(PreferredNeighbor::class, 'resetYourself'); }
            if ($serviceName !== 'noNeighbor') { $this->emitTo(NoNeighbor::class, 'resetYourself'); }
            if ($serviceName !== 'preferredDay') { $this->emitTo(PreferredDay::class, 'resetYourself'); }
            if ($serviceName !== 'parcelPackstation') { $this->emitTo(ParcelPackstation::class, 'resetYourself'); }
        } else {
            if ($this->activeService === $serviceName) {
                $this->activeService = null;
            }
        }
    }

    /**
     * Handles updates from non-exclusive children (e.g. GoGreen Plus).
     *
     * @param string $serviceName
     * @param mixed $value
     * @return void
     */
    public function handleChildStateUpdate(string $serviceName, $value): void
    {
        if (property_exists($this, $serviceName)) {
            $this->{$serviceName} = $value;
        }
    }

    /**
     * Handles updates from exclusive children (e.g. preferred location, neighbor).
     *
     * @param string $serviceName
     * @param bool $isActive
     * @return void
     */
    public function handleExclusiveServiceUpdate(string $serviceName, bool $isActive): void
    {
        if ($serviceName === 'noNeighbor') {
            $this->noNeighbor = $isActive;
        }
        // Optionally handle additional payload from child here.
        $this->setActiveService($serviceName, $isActive);
    }

    /**
     * Loads all option values from the current quote for initial rendering.
     *
     * @return void
     */
    private function loadAllValuesFromQuote(): void
    {
        // Preferred Location
        $locationSelections = $this->loadFromDb(Codes::SERVICE_OPTION_DROPOFF_DELIVERY);
        if ($locationSelections && !empty($locationSelections['details']->getInputValue())) {
            $this->preferredLocation = $locationSelections['details']->getInputValue();
            $this->activeService = 'preferredLocation';
        }

        // Preferred Neighbor
        $neighborSelections = $this->loadFromDb(Codes::SERVICE_OPTION_NEIGHBOR_DELIVERY);
        if ($neighborSelections && (!empty($neighborSelections['name']->getInputValue()) || !empty($neighborSelections['address']->getInputValue()))) {
             $this->neighborName = $neighborSelections['name']->getInputValue() ?? null;
             $this->neighborAddress = $neighborSelections['address']->getInputValue() ?? null;
             $this->activeService = 'preferredNeighbor';
        }

        // Packstation
        $packstationSelections = $this->loadFromDb(CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION);
        if ($packstationSelections && !empty($packstationSelections['id']->getInputValue())) {
            // TODO: Assign full packstationData here if required
            $this->activeService = 'parcelPackstation';
        }

        // No Neighbor
        $noNeighborSelections = $this->loadFromDb(Codes::SERVICE_OPTION_NO_NEIGHBOR_DELIVERY);
        if ($noNeighborSelections && !empty($noNeighborSelections['enabled']->getInputValue()) && (bool)$noNeighborSelections['enabled']->getInputValue()) {
            $this->noNeighbor = true;
            $this->activeService = 'noNeighbor';
        }

        // GoGreen Plus (optional extra service)
        $goGreenSelections = $this->loadFromDb(Codes::SERVICE_OPTION_GOGREEN_PLUS);
        if ($goGreenSelections && isset($goGreenSelections['enabled'])) {
            $this->goGreenPlus = (bool)$goGreenSelections['enabled']->getInputValue();
        }

        // Parcel Announcement (optional extra service)
        $announcementSelections = $this->loadFromDb(Codes::SERVICE_OPTION_PARCEL_ANNOUNCEMENT);
        if ($announcementSelections && !empty($announcementSelections['enabled']->getInputValue())) {
            $this->parcelAnnouncement = (bool)$announcementSelections['enabled']->getInputValue();
        }
    }

    /**
     * Checks if the shipping address is within Germany.
     *
     * @return void
     */
    public function checkCountryValidity(): void
    {
        try {
            $shippingAddress = $this->checkoutSession->getQuote()->getShippingAddress();
            $this->isShippingAddressValid = ($shippingAddress && $shippingAddress->getCountryId() === 'DE');
        } catch (\Exception) {
            $this->isShippingAddressValid = false;
        }
    }
}
