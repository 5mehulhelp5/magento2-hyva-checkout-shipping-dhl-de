<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Codes as CoreCodes;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes as DhlCodes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

/**
 * Magewire component for managing DHL "Parcel Packstation" (Packstation/Locker) delivery option.
 *
 * Handles delivery location selection, DHL post number validation,
 * modal dialog state, and exclusive option management.
 */
class ParcelPackstation extends ShippingOptions implements EvaluationInterface
{
    /**
     * Indicates if the modal dialog for Packstation selection is open.
     *
     * @var bool
     */
    public bool $modalOpened = false;

    /**
     * Stores the shipping address for validation and Packstation modal pre-filling.
     *
     * @var array<string, string>
     */
    public array $shippingAddress = [];

    /**
     * Contains error message for invalid DHL post number.
     *
     * @var string
     */
    public string $postnumberError = '';

    /**
     * Holds the current delivery location values (Packstation/Locker data).
     *
     * @var array{
     *   enabled: bool,
     *   customerPostnumber: string,
     *   type: string,
     *   id: string,
     *   number: string,
     *   displayName: string,
     *   company: string,
     *   countryCode: string,
     *   postalCode: string,
     *   city: string,
     *   street: string
     * }
     */
    public array $deliveryLocation = [
        'enabled'            => false,
        'customerPostnumber' => '',
        'type'               => '',
        'id'                 => '',
        'number'             => '',
        'displayName'        => '',
        'company'            => '',
        'countryCode'        => '',
        'postalCode'         => '',
        'city'               => '',
        'street'             => '',
    ];

    /**
     * Event listeners for this component.
     *
     * @var array<string, string>
     */
    protected $listeners = [
        'shipping_address_saved'       => 'checkAndSetShippingAddress',
        'guest_shipping_address_saved' => 'checkAndSetShippingAddress',
        'parcel_packstation_saved'     => 'setPackstation',
        'parcel_packstation_removed'   => 'clearPackstation',
        'dhlPostnumberUpdated'         => 'updatedDeliveryLocationCustomerPostnumber',
        'activeServiceChanged'         => 'onActiveServiceChanged',
    ];

    /**
     * Lifecycle method called on component mount.
     * Loads the current Packstation data and validates the post number.
     *
     * @return void
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION);

        if ($quoteSelections) {
            $map = [
                'customerPostnumber' => DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER,
                'type'               => 'type',
                'id'                 => 'id',
                'number'             => 'number',
                'displayName'        => 'displayName',
                'company'            => 'company',
                'countryCode'        => 'countryCode',
                'postalCode'         => 'postalCode',
                'city'               => 'city',
                'street'             => 'street',
                'enabled'            => 'enabled',
            ];
            foreach ($map as $key => $inputCode) {
                if (isset($quoteSelections[$inputCode])) {
                    $this->deliveryLocation[$key] = (string)$quoteSelections[$inputCode]->getInputValue();
                }
            }
        }

        if (!empty($this->deliveryLocation['id'])) {
            $this->deliveryLocation['enabled'] = true;
            $this->validatePostnumber();
            $this->emitUp('requestExclusive', 'parcelPackstation');
        }
    }

    /**
     * Evaluate the completion state for the checkout step.
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
     * Handler for when the active exclusive service is changed.
     * If another exclusive service is selected, this resets the Packstation data.
     *
     * @param string|null $activeService
     * @return void
     */
    public function onActiveServiceChanged(?string $activeService = null): void
    {
        if ($activeService !== 'parcelPackstation'
            && ($this->deliveryLocation['enabled'] || $this->deliveryLocation['id'] !== '')
        ) {
            $this->resetData();
        }
    }

    /**
     * Resets all delivery location values and releases exclusive access.
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
            $this->persistFieldUpdate($inputCode, $value, CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION);
        }
        $this->emitUp('releaseExclusive', 'parcelPackstation');
    }

    /**
     * Clears the Packstation selection.
     *
     * @return void
     */
    public function clearPackstation(): void
    {
        $this->resetData();
    }

    /**
     * Sets the Packstation delivery location from given data and persists it.
     *
     * @param array $data The delivery location data to set.
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

            $this->persistFieldUpdate($inputCode, (string)$value, CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION);
        }

        $this->deliveryLocation['enabled'] = true;
        $this->persistFieldUpdate('enabled', '1', CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION);

        $this->validatePostnumber();

        $this->emitUp('requestExclusive', 'parcelPackstation');
    }

    /**
     * Updates and validates the DHL post number when changed.
     *
     * @param string $value The new DHL post number.
     * @return void
     */
    public function updatedDeliveryLocationCustomerPostnumber(string $value): void
    {
        $this->deliveryLocation['customerPostnumber'] = $value;
        $this->validatePostnumber();

        $this->persistFieldUpdate(
            DhlCodes::SERVICE_INPUT_DELIVERY_LOCATION_ACCOUNT_NUMBER,
            $this->deliveryLocation['customerPostnumber'],
            CoreCodes::SERVICE_OPTION_DELIVERY_LOCATION
        );
    }

    /**
     * Validates the DHL post number for Packstation/locker type.
     *
     * @return void
     */
    private function validatePostnumber(): void
    {
        $type    = (string)($this->deliveryLocation['type'] ?? '');
        $isLocker = (strtolower($type) === 'locker');
        $account = mb_substr(trim($this->deliveryLocation['customerPostnumber']), 0, 10);
        $len     = mb_strlen($account);
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
     * Checks the shipping address and stores its values in $shippingAddress.
     *
     * @return bool True if address is set, false otherwise.
     */
    public function checkAndSetShippingAddress(): bool
    {
        $address = $this->getShippingAddress();
        if (!$address) { return false; }

        $street      = $address->getStreetLine(1);
        $postalCode  = $address->getPostcode();
        $city        = $address->getCity();
        $countryCode = $address->getCountryId();

        if (empty($street) || empty($postalCode) || empty($city) || empty($countryCode)) {
            return false;
        }
        $this->shippingAddress = [
            'street'      => $street,
            'city'        => $city,
            'postalCode'  => $postalCode,
            'countryCode' => $countryCode,
        ];
        return true;
    }

    /**
     * Opens the modal dialog for Packstation selection.
     *
     * @return bool True if modal opened, false otherwise.
     */
    public function openModal(): bool
    {
        if (!$this->checkAndSetShippingAddress()) { return false; }
        $this->modalOpened = true;
        return true;
    }

    /**
     * Closes the modal dialog for Packstation selection.
     *
     * @return bool Always returns false.
     */
    public function closeModal(): bool
    {
        $this->modalOpened = false;
        return false;
    }
}
