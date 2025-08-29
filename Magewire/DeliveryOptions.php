<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Codes as CoreCodes;

/**
 * Central Magewire component managing DHL delivery options.
 *
 * Acts as a "State Announcer": It manages which single exclusive service is currently active
 * and announces changes to all child components. It does not store or control the state of its children.
 */
class DeliveryOptions extends ShippingOptions
{
    /**
     * Indicates if the shipping address is within Germany.
     *
     * @var bool
     */
    public bool $isShippingAddressValid = false;

    /**
     * The name of the currently active exclusive service.
     *
     * @var string|null
     */
    public ?string $activeService = null;

    /**
     * Event listeners for checkout and child component events.
     *
     * @var array<string, string>
     */
    protected $listeners = [
        'shipping_address_saved'       => 'checkCountryValidity',
        'guest_shipping_address_saved' => 'checkCountryValidity',
        'requestExclusive'             => 'grantExclusiveAccess',
        'releaseExclusive'             => 'releaseExclusiveAccess',
    ];

    /**
     * Component mount lifecycle method.
     * Checks address validity and determines the initial active service.
     *
     * @return void
     */
    public function mount(): void
    {
        $this->checkCountryValidity();
        $this->activeService = $this->detectActiveExclusiveService();

        // Announce the initial state to all listening child components.
        $this->emit('activeServiceChanged', $this->activeService);
    }

    /**
     * Grants exclusive access to a child component and notifies listeners.
     *
     * @param string $serviceName The name of the service requesting exclusivity.
     * @return void
     */
    public function grantExclusiveAccess(string $serviceName): void
    {
        if ($this->activeService === $serviceName) {
            return; // No change needed.
        }
        $this->activeService = $serviceName;
        $this->emit('activeServiceChanged', $this->activeService);
    }

    /**
     * Releases exclusive access for a service and notifies listeners.
     *
     * @param string $serviceName The name of the service releasing exclusivity.
     * @return void
     */
    public function releaseExclusiveAccess(string $serviceName): void
    {
        if ($this->activeService !== $serviceName) {
            return; // Only release if this service is currently active.
        }
        $this->activeService = null;
        $this->emit('activeServiceChanged', null);
    }

    /**
     * Checks if the current shipping address country is Germany ("DE").
     *
     * @return void
     */
    public function checkCountryValidity(): void
    {
        try {
            /** @var \Magento\Quote\Model\Quote\Address|null $shippingAddress */
            $shippingAddress = $this->checkoutSession->getQuote()->getShippingAddress();
            $this->isShippingAddressValid = ($shippingAddress && $shippingAddress->getCountryId() === 'DE');
        } catch (\Exception $e) {
            $this->isShippingAddressValid = false;
        }
    }

    /**
     * Detects which exclusive service is currently active based on saved quote data.
     * Only called during initial component mount.
     *
     * @return string|null The key of the active service, or null if none is active.
     */
    private function detectActiveExclusiveService(): ?string
    {
        // Priority order: preferredDay > preferredLocation > preferredNeighbor > noNeighbor > parcelPackstation

        if ($selections = $this->loadFromDb(DhlCodes::SERVICE_OPTION_PREFERRED_DAY)) {
            if (!empty($selections['date']?->getInputValue())) {
                return 'preferredDay';
            }
        }
        if ($selections = $this->loadFromDb(DhlCodes::SERVICE_OPTION_DROPOFF_DELIVERY)) {
            if (!empty($selections['details']?->getInputValue())) {
                return 'preferredLocation';
            }
        }
        if ($selections = $this->loadFromDb(DhlCodes::SERVICE_OPTION_NEIGHBOR_DELIVERY)) {
            if (
                !empty($selections['name']?->getInputValue()) ||
                !empty($selections['address']?->getInputValue())
            ) {
                return 'preferredNeighbor';
            }
        }
        if ($selections = $this->loadFromDb(DhlCodes::SERVICE_OPTION_NO_NEIGHBOR_DELIVERY)) {
            if (!empty($selections['enabled']?->getInputValue())) {
                return 'noNeighbor';
            }
        }
        if ($selections = $this->loadFromDb(CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION)) {
            if (!empty($selections['id']?->getInputValue())) {
                return 'parcelPackstation';
            }
        }

        return null;
    }
}
