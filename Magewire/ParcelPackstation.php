<?php

declare(strict_types=1);

namespace Hyva\HyvaShippingDhl\Magewire;

use Magewirephp\Magewire\Component;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Codes as Codes;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;

class ParcelPackstation extends ShippingOptions
{
    /**
     * @var bool Controls the display of the location finder modal.
     */
    public bool $modalOpened = false;

    /**
     * @var array Stores the shipping address details.
     */
    public array $shippingAddress = [];
    
    /**
     * @var array Stores the delivery location data.
     */
    public array $deliveryLocation = [
        'enabled' => false,
        'customerPostnumber' => '',
        'type' => '',
        'id' => '',
        'number' => '',
        'displayName' => '',
        'company' => '',
        'countryCode' => '',
        'postalCode' => '',
        'city' => '',
        'street' => ''
    ];

    /**
     * @var array Listeners for various shipping address events.
     */
    protected $listeners = [
        'shipping_address_saved' => 'checkAndSetShippingAddress',
        'guest_shipping_address_saved' => 'checkAndSetShippingAddress',
        'parcel_packstation_saved' => 'setPackstation',
        'parcel_packstation_removed' => 'clearPackstation',
    ];

    /**
     * Initializes the component and loads the quote selections from the database.
     */
    public function mount(): void
    {
        /** @var SelectionInterface $quoteSelections */
        $quoteSelections = $this->loadFromDb(Codes::SERVICE_OPTION_DELIVERY_LOCATION);

        if ($quoteSelections) {
            foreach ($this->deliveryLocation as $key => $value) {
                if (isset($quoteSelections[$key])) {
                    $this->deliveryLocation[$key] = $quoteSelections[$key]->getInputValue();
                }
            }
        }
        
        if (!empty($this->deliveryLocation['id'])) {
            $this->deliveryLocation['enabled'] = true;
        }
    }

    /**
     * Aktualisiert ein spezifisches Feld in den Lieferort-Daten und speichert es.
     *
     * @param string $field
     * @param mixed $value
     */
    public function updateDeliveryLocationField(string $field, $value): void
    {
        if ($field === 'customerPostnumber') {
            $this->updatedDeliveryLocationCustomerPostnumber($value);
        } else {
            if (array_key_exists($field, $this->deliveryLocation)) {
                $this->deliveryLocation[$field] = $value;
                $this->persistFieldUpdate($field, $value, Codes::SERVICE_OPTION_DELIVERY_LOCATION);
            }
        }
    }

    /**
     * Aktualisiert die DHL-Kundennummer (Postnummer) und speichert sie.
     *
     * @param string $value
     * @return mixed
     */
    public function updatedDeliveryLocationCustomerPostnumber(string $value): mixed
    {
        return $this->persistFieldUpdate(
            DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER, 
            $value, 
            Codes::SERVICE_OPTION_DELIVERY_LOCATION
        );
    }

    /**
     * Clears the packstation data and updates the selections in the database.
     */
    public function clearPackstation(): void
    {
        foreach ($this->deliveryLocation as $key => $value) {
            $this->updateDeliveryLocationField($key, '');
        }
        $this->updateDeliveryLocationField('enabled', false);

        $this->emitToRefresh('checkout.shipping.method.dhlpaket_bestway_packstation');
    }

    /**
     * Retrieves packstation information based on the given data.
     *
     * @param array $data Data to identify the packstation.
     */
    public function setPackstation(array $data): void
    {
        if (isset($data['deliveryLocation'])) {
            $data = $data['deliveryLocation'];
        }
        
        $this->closeModal();
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $this->deliveryLocation)) {
                $this->deliveryLocation[$key] = $value;
                $this->persistFieldUpdate($key, $value, Codes::SERVICE_OPTION_DELIVERY_LOCATION);
            }
        }

        $this->deliveryLocation['enabled'] = true;
        $this->persistFieldUpdate('enabled', true, Codes::SERVICE_OPTION_DELIVERY_LOCATION);        
    }
    
    /**
     * Validates if the shipping address is already set based on the checkout data.
     * 
     * @return bool
     */
    public function checkAndSetShippingAddress(): bool
    {
        $address = $this->getShippingAddress();

        if (!$address) {
            return false;
        }
        
        $street = $address->getStreetLine(1);
        $postalCode = $address->getPostcode();
        $city = $address->getCity();
        $countryCode = $address->getCountryId();

        if (empty($street) || empty($postalCode) || empty($city) || empty($countryCode)) {
            return false;
        }
        
        $this->shippingAddress = [
            'street' => $address->getStreetLine(1),
            'city' => $address->getCity(),
            'postalCode' => $address->getPostcode(),
            'countryCode' => $address->getCountryId(),
        ];

        return true;
    }
    
    /**
     * Opens the modal for delivery location.
     * 
     * @return bool True if the modal is opened.
     */
    public function openModal(): bool
    {
        $this->modalOpened = true;
        return true;
    }

    /**
     * Closes the modal for delivery location.
     * 
     * @return bool False if the modal is closed.
     */
    public function closeModal(): bool
    {
        $this->modalOpened = false;
        return false;
    }
}
