<?php

declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Codes as Codes;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

class ParcelPackstation extends ShippingOptions implements EvaluationInterface
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
    public string $postnumberError = '';

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
     * Evaluates the component's state for completion before placing the order.
     * This is the final server-side validation gate.
     *
     * @param EvaluationResultFactory $resultFactory
     * @return EvaluationResultInterface
     */
    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface {
        if (empty($this->deliveryLocation['enabled']) || empty($this->deliveryLocation['id'])) {
            return $resultFactory->createSuccess();
        }

        $type  = (string)($this->deliveryLocation['type'] ?? '');
        $value = (string)($this->deliveryLocation['customerPostnumber'] ?? '');
        $error = $this->validatePostnumber($value, $type);

        if ($error !== null) {
            $this->postnumberError = $error;

            return $resultFactory->createErrorMessage()
                ->withMessage($error)
                ->withVisibilityDuration(3000);
        }

        $this->postnumberError = '';
        return $resultFactory->createSuccess();
    }

    private function validatePostnumber(?string $raw, string $type): ?string
    {
        $postnumber = preg_replace('/\D+/', '', (string)$raw);
        $isLocker   = (strtolower($type) === 'locker');

        $isValid = $postnumber !== '' && preg_match('/^\d{6,10}$/', $postnumber);

        if ($isLocker) {
            return $isValid ? null : (string)__('The DHL postcode provided is not valid.');
        }

        if ($postnumber === '') {
            return null;
        }
        return preg_match('/^\d{6,10}$/', $postnumber)
            ? null
            : (string)__('The DHL postcode provided is not valid.');
    }


    /**
     * Initializes the component and loads the quote selections from the database.
     */
    public function mount(): void
    {
        /** @var SelectionInterface $quoteSelections */
        $quoteSelections = $this->loadFromDb(Codes::SERVICE_OPTION_DELIVERY_LOCATION);

        if ($quoteSelections) {
            foreach ($this->deliveryLocation as $key => $value) {
                $inputCode = $key === 'customerPostnumber'
                    ? DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER
                    : $key;

                if (isset($quoteSelections[$inputCode])) {
                    $this->deliveryLocation[$key] = (string)$quoteSelections[$inputCode]->getInputValue();
                }
            }
        }

        if (!empty($this->deliveryLocation['id'])) {
            $this->deliveryLocation['enabled'] = true;
        }
    }

    /**
     * Updates a specific field in the delivery location data and saves it.
     *
     * @param string $field
     * @param mixed $value
     */
    public function updateDeliveryLocationField(string $field, mixed $value): void
    {
        if (!array_key_exists($field, $this->deliveryLocation)) {
            return;
        }

        $this->deliveryLocation[$field] = $value;

        $inputCode = $field;
        if ($field === 'customerPostnumber') {
            $inputCode = DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER;
        }

        $this->persistFieldUpdate($inputCode, $value, Codes::SERVICE_OPTION_DELIVERY_LOCATION);

        if ($field === 'type') {
            $this->updatedDeliveryLocationCustomerPostnumber(
                (string) ($this->deliveryLocation['customerPostnumber'] ?? '')
            );
        }
    }
    
    /**
     * Updates and saves the DHL customer number (post number).
     *
     * @param string $value
     * @return mixed
     */
    public function updatedDeliveryLocationCustomerPostnumber(string $value): void
    {
        $type  = (string)($this->deliveryLocation['type'] ?? '');
        $error = $this->validatePostnumber($value, $type);

        // Diese if/else-Logik setzt die postnumberError-Nachricht
        if ($error !== null) {
            $this->postnumberError = (string)__('Die nummer ist nicht gültig sollte zwischen 6 und 10 zeichen haben');
        } else {
            $this->postnumberError = '';
        }

        // immer nur Ziffern speichern (max. 10)
        $digits = substr(preg_replace('/\D+/', '', $value), 0, 10);

        $this->persistFieldUpdate(
            DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER,
            $digits,
            Codes::SERVICE_OPTION_DELIVERY_LOCATION
        );

        // Spiegel auch in State halten (damit UI nach Persist direkt konsistent ist)
        $this->deliveryLocation['customerPostnumber'] = $digits;
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
            $inputCode = $key === 'customerPostnumber'
                ? DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER
                : $key;

            $quoteSelection = $this->quoteSelectionFactory->create();
            $quoteSelection->setData([
                SelectionInterface::SHIPPING_OPTION_CODE => Codes::SERVICE_OPTION_DELIVERY_LOCATION,
                SelectionInterface::INPUT_CODE => $inputCode,
                SelectionInterface::INPUT_VALUE => $key === 'enabled' ? '0' : ''
            ]);
            $selectionsToClear[] = $quoteSelection;
        }

        try {
            $quoteId = (int)$this->checkoutSession->getQuote()->getId();
            $this->checkoutManagement->updateShippingOptionSelections($quoteId, $selectionsToClear);
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
        foreach ($this->deliveryLocation as $key => $defaultValue) {
            $value = $data[$key] ?? $defaultValue;
            $this->deliveryLocation[$key] = $value;

            $inputCode = $key;
            // Special case for the post number, as it uses a different codes constant
            if ($key === 'customerPostnumber') {
                $inputCode = DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER;
            }

            $quoteSelection = $this->quoteSelectionFactory->create();
            $quoteSelection->setData([
                SelectionInterface::SHIPPING_OPTION_CODE => Codes::SERVICE_OPTION_DELIVERY_LOCATION,
                SelectionInterface::INPUT_CODE => $inputCode,
                SelectionInterface::INPUT_VALUE => (string)$value
            ]);
            $selectionsToSave[] = $quoteSelection;
        }

        $this->deliveryLocation['enabled'] = true;

        $enabledSelection = $this->quoteSelectionFactory->create();
        $enabledSelection->setData([
            SelectionInterface::SHIPPING_OPTION_CODE => Codes::SERVICE_OPTION_DELIVERY_LOCATION,
            SelectionInterface::INPUT_CODE => 'enabled',
            SelectionInterface::INPUT_VALUE => '1'
        ]);
        $selectionsToSave[] = $enabledSelection;

        try {
            $quoteId = (int) $this->checkoutSession->getQuote()->getId();
            $this->checkoutManagement->updateShippingOptionSelections($quoteId, $selectionsToSave);
        } catch (\Exception $e) {
            $this->dispatchErrorMessage(__('Failed to set Packstation data: %1', $e->getMessage()));
        }
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
        if (!$this->checkAndSetShippingAddress()) {
            return false;
        }
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