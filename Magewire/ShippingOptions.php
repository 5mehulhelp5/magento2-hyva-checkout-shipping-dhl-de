<?php
declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\Config\ModuleConfig;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magewirephp\Magewire\Component;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;
use Netresearch\ShippingCore\Api\ShippingSettings\CheckoutManagementInterface;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Selection\QuoteSelectionFactory;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Selection\QuoteSelectionManager;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Selection\QuoteSelection;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOptionInterface;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Selection\QuoteSelectionRepository;
use Netresearch\ShippingCore\Model\Config\MapBoxConfig;
use Magento\Framework\Exception\LocalizedException;

/**
 * Abstract base component for DHL Magewire shipping options.
 * Provides shared persistence and utility methods for all option components.
 */
abstract class ShippingOptions extends Component
{
    protected ModuleConfig $moduleConfig;
    protected StoreManagerInterface $storeManager;
    protected QuoteSelectionFactory $quoteSelectionFactory;
    protected CheckoutManagementInterface $checkoutManagement;
    protected QuoteSelectionManager $quoteSelectionManager;
    protected ShippingOptionInterface $shippingOption;
    protected CheckoutSession $checkoutSession;
    protected ScopeConfigInterface $scopeConfig;
    protected QuoteSelectionRepository $quoteSelectionRepository;
    protected MapBoxConfig $mapBoxConfig;

    /**
     * Holds the show/hide (active/inactive) state for all dynamic service options.
     * This is filled by the child class using the $services array keys.
     * @var array<string, bool>
     */
    public array $optionStates = [];

    /**
     * ShippingOptions constructor.
     * Fills $optionStates dynamically based on child class's $services config array.
     */
    public function __construct(
        ModuleConfig $moduleConfig,
        StoreManagerInterface $storeManager,
        CheckoutManagementInterface $checkoutManagement,
        QuoteSelectionFactory $quoteSelectionFactory,
        QuoteSelectionManager $quoteSelectionManager,
        ShippingOptionInterface $shippingOption,
        CheckoutSession $checkoutSession,
        ScopeConfigInterface $scopeConfig,
        QuoteSelectionRepository $quoteSelectionRepository,
        MapBoxConfig $mapBoxConfig
    ) {
        $this->moduleConfig = $moduleConfig;
        $this->storeManager = $storeManager;
        $this->checkoutManagement = $checkoutManagement;
        $this->quoteSelectionFactory = $quoteSelectionFactory;
        $this->quoteSelectionManager = $quoteSelectionManager;
        $this->shippingOption = $shippingOption;
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->quoteSelectionRepository = $quoteSelectionRepository;
        $this->mapBoxConfig = $mapBoxConfig;

        $this->initOptionStates();
    }

    /**
     * Dynamically fills $optionStates using keys from $services config in child class.
     * Every option starts as TRUE (visible/active).
     */
    protected function initOptionStates(): void
    {
        // Only initialize if the child class defines $services
        if (property_exists($this, 'services') && is_array($this->services)) {
            $this->optionStates = array_fill_keys(array_keys($this->services), true);
        }
    }

    /**
     * Load all persisted quote selections for a given DHL shipping option code.
     * @param string $code
     * @return SelectionInterface[]
     */
    protected function loadFromDb(string $code): array
    {
        $result = [];
        $quoteSelections = $this->getExistingQuoteSelections();

        foreach ($quoteSelections as $quoteSelection) {
            if ($quoteSelection->getShippingOptionCode() === $code) {
                $result[$quoteSelection->getInputCode()] = $quoteSelection;
            }
        }
        return $result;
    }

    /**
     * Get the current quote from the checkout session.
     * @return \Magento\Quote\Api\Data\CartInterface|null
     */
    protected function getQuote(): ?\Magento\Quote\Api\Data\CartInterface
    {
        try {
            return $this->checkoutSession->getQuote();
        } catch (NoSuchEntityException $e) {
            $this->dispatchErrorMessage($e->getMessage());
            return null;
        }
    }

    /**
     * Get the shipping address from the current quote.
     * @return \Magento\Quote\Model\Quote\Address|null
     */
    protected function getShippingAddress(): ?\Magento\Quote\Model\Quote\Address
    {
        $quote = $this->getQuote();
        if (!$quote) {
            return null;
        }

        $address = $quote->getShippingAddress();
        if (!$address || !$address->getId()) {
            $this->dispatchErrorMessage(__('Shipping address not found'));
            return null;
        }

        return $address;
    }

    /**
     * Get the ID of the shipping address.
     * @return int|null
     */
    protected function getAddressId(): ?int
    {
        $address = $this->getShippingAddress();
        return $address ? (int)$address->getId() : null;
    }

    /**
     * Get the Mapbox API token (for Packstation map).
     * @return string
     */
    public function getMapboxApiToken(): string
    {
        return $this->mapBoxConfig->getApiToken();
    }

    /**
     * Load all persisted quote selections for the current shipping address.
     * @return SelectionInterface[]
     */
    protected function getExistingQuoteSelections(): array
    {
        $addressId = $this->getAddressId();

        if ($addressId === null) {
            return [];
        }

        return $this->quoteSelectionManager->load($addressId);
    }

    /**
     * Update (save) a shipping option selection to the current quote.
     * @param QuoteSelection $quoteSelection
     * @return array
     */
    protected function updateShippingOptionSelections(QuoteSelection $quoteSelection): array
    {
        $quoteId = (int) $this->checkoutSession->getQuote()->getId();
        $quoteSelections = $this->getExistingQuoteSelections();

        // Remove old entry for this code/input if present
        foreach ($quoteSelections as $key => $selection) {
            if (
                $selection->getShippingOptionCode() === $quoteSelection->getShippingOptionCode() &&
                $selection->getInputCode() === $quoteSelection->getInputCode()
            ) {
                unset($quoteSelections[$key]);
                break;
            }
        }

        // Only save if value is not empty
        if (!empty($quoteSelection->getInputValue())) {
            $quoteSelections[] = $quoteSelection;
        }

        $this->checkoutManagement->updateShippingOptionSelections($quoteId, $quoteSelections);
        return $quoteSelections;
    }

    /**
     * Helper to persist a single field/value to quote.
     * @param string $field
     * @param mixed $value
     * @param string $shippingOptionCode
     * @return mixed
     */
    protected function persistFieldUpdate(string $field, mixed $value, string $shippingOptionCode): mixed
    {
        /** @var SelectionInterface $quoteSelection */
        $quoteSelection = $this->quoteSelectionFactory->create();
        $quoteSelection->setData([
            SelectionInterface::SHIPPING_OPTION_CODE => $shippingOptionCode,
            SelectionInterface::INPUT_CODE => $field,
            SelectionInterface::INPUT_VALUE => $value
        ]);

        $this->updateShippingOptionSelections($quoteSelection);
        return $value;
    }

    /**
     * Optionally allow dynamic show/hide of options by key (activated by child components).
     * @param string $key
     */
    public function activateOption(string $key): void
    {
        foreach ($this->optionStates as $k => $_) {
            $this->optionStates[$k] = ($k === $key);
        }

        foreach ($this->optionStates as $k => $isActive) {
            $this->emitTo('checkout.shipping.method.dhlpaket_bestway_' . $k, 'setShowState', $isActive);
        }
    }
}
