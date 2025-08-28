<?php

declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Codes as Codes;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for handling DHL Packstation selection and input in the checkout.
 */
class ParcelPackstation extends ShippingOptions implements EvaluationInterface
{
    /**
     * @var bool Indicates if the Packstation modal is open.
     */
    public bool $modalOpened = false;

    /**
     * @var array Stores the current shipping address.
     */
    public array $shippingAddress = [];

    /**
     * @var string Holds any validation error for the DHL postnumber.
     */
    public string $postnumberError = '';

    /**
     * @var array Main delivery location data for the Packstation service.
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
     * @var array Listens for events from the frontend and other components.
     */
    protected $listeners = [
        'parcel_packstation_saved'   => 'setPackstation',
        'parcel_packstation_removed' => 'clearPackstation',
        'dhlPostnumberUpdated'       => 'updatedDeliveryLocationCustomerPostnumber',
        'resetYourself'              => 'resetData'
    ];

    /**
     * Initializes the component with data from the current quote (DB).
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
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
            $this->validatePostnumber();
        }
    }

    /**
     * Validates if all required fields are set (for checkout validation).
     *
     * @param EvaluationResultFactory $resultFactory
     * @return EvaluationResultInterface
     */
    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        if (empty($this->deliveryLocation['enabled']) || empty($this->deliveryLocation['id'])) {
            return $resultFactory->createSuccess();
        }
        return $resultFactory->createValidation('validateDhlPostnumber');
    }

    /**
     * Resets all fields to their default values and persists them.
     *
     * @return void
     */
    public function resetData(): void
    {
        foreach ($this->deliveryLocation as $key => &$value) {
            $value = ($key === 'enabled') ? false : '';
            $inputCode = $key === 'customerPostnumber'
                ? DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER
                : $key;
            $this->persistFieldUpdate($inputCode, $value, Codes::SERVICE_OPTION_DELIVERY_LOCATION);
        }
        // Notify parent that the Packstation service is now inactive (and sync empty array)
        $this->emitUp('exclusiveServiceUpdated', 'parcelPackstation', false, [
            'deliveryLocation' => $this->deliveryLocation,
        ]);
    }

    /**
     * Called from the modal or UI to clear the Packstation selection.
     *
     * @return void
     */
    public function clearPackstation(): void
    {
        $this->resetData();
    }

    /**
     * Called after the user selects a Packstation (via modal/map).
     *
     * @param array $data
     * @return void
     */
    public function setPackstation(array $data): void
    {
        if (isset($data['deliveryLocation'])) {
            $data = $data['deliveryLocation'];
        }
        $this->closeModal();
        foreach ($this->deliveryLocation as $key => $defaultValue) {
            $value = $data[$key] ?? $defaultValue;
            $this->deliveryLocation[$key] = $value;
            $inputCode = $key === 'customerPostnumber'
                ? DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER
                : $key;
            $this->persistFieldUpdate($inputCode, (string)$value, Codes::SERVICE_OPTION_DELIVERY_LOCATION);
        }
        $this->deliveryLocation['enabled'] = true;
        $this->persistFieldUpdate('enabled', '1', Codes::SERVICE_OPTION_DELIVERY_LOCATION);
        
        $this->validatePostnumber();
        
        // Notify parent that Packstation is now active (with current array)
        $this->emitUp('exclusiveServiceUpdated', 'parcelPackstation', true, [
            'deliveryLocation' => $this->deliveryLocation,
        ]);
    }

    /**
     * Called when the postnumber field changes.
     *
     * @param string $value
     * @return void
     */
    public function updatedDeliveryLocationCustomerPostnumber(string $value): void
    {
        $this->deliveryLocation['customerPostnumber'] = $value;
        $this->validatePostnumber();

        $this->persistFieldUpdate(
            DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER,
            $this->deliveryLocation['customerPostnumber'],
            Codes::SERVICE_OPTION_DELIVERY_LOCATION
        );

        $this->emitUp('exclusiveServiceUpdated', 'parcelPackstation', true);
    }
    
    /**
     * Validates the DHL customer postnumber for Packstation/locker delivery.
     *
     * Sets a validation error message if the postnumber is missing or invalid.
     *
     * @return void
     */
    private function validatePostnumber(): void
    {
        /** @var string $type The delivery location type (e.g. 'locker'). */
        $type = (string)($this->deliveryLocation['type'] ?? '');

        /** @var bool $isLocker True if the delivery type is a DHL locker. */
        $isLocker = (strtolower($type) === 'locker');

        /** @var string $account The trimmed DHL customer postnumber (max 10 chars). */
        $account = mb_substr(trim($this->deliveryLocation['customerPostnumber']), 0, 10);

        /** @var int $len The length of the account string. */
        $len = mb_strlen($account);

        /** @var bool $isValid True if the postnumber matches 6–10 alphanumeric chars. */
        $isValid = ($len >= 6 && $len <= 10) && (bool)preg_match('/^[A-Za-z0-9]{6,10}$/u', $account);

        if ($isLocker && $account === '') {
            $this->postnumberError = (string)__('DHL post number is required for lockers.');
        } elseif ($account !== '' && !$isValid) {
            $this->postnumberError = (string)__('Please enter a valid DHL post number (6–10 characters).');
        } else {
            $this->postnumberError = '';
        }
    }

    /**
     * Ensures that a valid shipping address is present before opening modal.
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
            'street'      => $address->getStreetLine(1),
            'city'        => $address->getCity(),
            'postalCode'  => $address->getPostcode(),
            'countryCode' => $address->getCountryId(),
        ];
        return true;
    }

    /**
     * Opens the modal for Packstation search/selection.
     *
     * @return bool
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
     * Closes the Packstation modal.
     *
     * @return bool
     */
    public function closeModal(): bool
    {
        $this->modalOpened = false;
        return false;
    }
}
