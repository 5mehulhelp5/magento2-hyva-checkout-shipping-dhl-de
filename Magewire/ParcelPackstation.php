<?php

declare(strict_types=1);

namespace Hyva\HyvaShippingDhl\Magewire;

use Magewirephp\Magewire\Component;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Codes as Codes;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

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
    public function updateDeliveryLocationField(string $field, mixed $value): void // <-- mixed als Typ hinzugefügt
    {
        if (array_key_exists($field, $this->deliveryLocation)) {
            $this->deliveryLocation[$field] = $value;

            $inputCode = $field;
            // Spezialfall für die Postnummer, da sie einen anderen Codes-Konstanten verwendet
            if ($field === 'customerPostnumber') {
                $inputCode = DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER;
            }

            $this->persistFieldUpdate($inputCode, $value, Codes::SERVICE_OPTION_DELIVERY_LOCATION);
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
            $this->deliveryLocation[$key] = '';
        }
        $this->deliveryLocation['enabled'] = false; 

        $selectionsToClear = [];
        foreach ($this->deliveryLocation as $key => $value) {
            $quoteSelection = $this->quoteSelectionFactory->create();

            $inputCode = ($key === 'customerPostnumber') ? DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER : $key;
            $quoteSelection->setData([
                SelectionInterface::SHIPPING_OPTION_CODE => Codes::SERVICE_OPTION_DELIVERY_LOCATION,
                SelectionInterface::INPUT_CODE => $inputCode,
                SelectionInterface::INPUT_VALUE => (string)$value
            ]);
            $selectionsToClear[] = $quoteSelection;
        }

        try {
            // Hole die aktuelle Quote ID (kann hier ggf. fehlschlagen)
            $quoteId = (int) $this->checkoutSession->getQuote()->getId();
            // Rufe die updateShippingOptionSelections-Methode in der Basisklasse auf.
            // Diese Methode wird dann die Netresearch\ShippingCore\Model\ShippingSettings\CheckoutManagement::updateShippingOptionSelections aufrufen,
            // die wiederum den QuoteSelectionManager::save() aufruft.
            // Der QuoteSelectionManager::save() löscht alle alten Selections und speichert die neuen.
            $this->checkoutManagement->updateShippingOptionSelections($quoteId, $selectionsToClear);

            // Optional: Wenn du willst, dass nach dem Leeren die Versandmethoden neu geladen werden,
            // ist dieser Emit sinnvoll. Aber es sollte automatisch passieren, wenn sich die Lieferadresse ändert.
            // Beachte, dass 'checkout.shipping.method.dhlpaket_bestway_packstation' sehr spezifisch ist.
            // Ein generischeres Event wie 'shipping_method_updated' wäre vielleicht besser,
            // wenn deine Versandmethoden auf so etwas reagieren.
            $this->emitToRefresh('checkout.shipping.method.dhlpaket_bestway_packstation');

        } catch (\Exception $e) {
            $this->dispatchErrorMessage(__('Failed to clear Packstation data: %1', $e->getMessage()));
        }
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

        $selectionsToSave = [];
        foreach ($this->deliveryLocation as $key => $defaultValue) { // Iteriere über alle erwarteten Felder
            $value = $data[$key] ?? $defaultValue; // Nutze den übergebenen Wert oder den Standardwert
            $this->deliveryLocation[$key] = $value; // Aktualisiere die Magewire Property

            $inputCode = $key;
            // Spezialfall für die Postnummer, da sie einen anderen Codes-Konstanten verwendet
            if ($key === 'customerPostnumber') {
                $inputCode = DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER;
            }

            $quoteSelection = $this->quoteSelectionFactory->create();
            $quoteSelection->setData([
                SelectionInterface::SHIPPING_OPTION_CODE => Codes::SERVICE_OPTION_DELIVERY_LOCATION,
                SelectionInterface::INPUT_CODE => $inputCode,
                SelectionInterface::INPUT_VALUE => (string)$value // Sicherstellen, dass es ein String ist
            ]);
            $selectionsToSave[] = $quoteSelection;
        }

        $this->deliveryLocation['enabled'] = true; // Setze enabled explizit

        // Füge auch die 'enabled' Selection hinzu
        $enabledSelection = $this->quoteSelectionFactory->create();
        $enabledSelection->setData([
            SelectionInterface::SHIPPING_OPTION_CODE => Codes::SERVICE_OPTION_DELIVERY_LOCATION,
            SelectionInterface::INPUT_CODE => 'enabled',
            SelectionInterface::INPUT_VALUE => '1' // 'true' als String speichern
        ]);
        $selectionsToSave[] = $enabledSelection;

        try {
            $quoteId = (int) $this->checkoutSession->getQuote()->getId();
            // Ein einziger Aufruf, der alle alten löscht und die neuen speichert
            $this->checkoutManagement->updateShippingOptionSelections($quoteId, $selectionsToSave);
        } catch (\Exception $e) {
            $this->dispatchErrorMessage(__('Failed to set Packstation data: %1', $e->getMessage()));
        }

        // Optional: Emit to refresh if necessary, as for clearPackstation
        // $this->emitToRefresh('checkout.shipping.method.dhlpaket_bestway_packstation');
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
