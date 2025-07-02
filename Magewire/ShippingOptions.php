<?php

declare(strict_types=1);

namespace Hyva\HyvaShippingDhl\Magewire;

use Dhl\Paket\Model\Config\ModuleConfig;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
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

class ShippingOptions extends Component
{
    /**
     * @var ModuleConfig
     */
    protected ModuleConfig $moduleConfig;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var QuoteSelectionFactory
     */
    protected QuoteSelectionFactory $quoteSelectionFactory;

    /**
     * @var CheckoutManagementInterface
     */
    protected CheckoutManagementInterface $checkoutManagement;

    /**
     * @var QuoteSelectionManager
     */
    protected QuoteSelectionManager $quoteSelectionManager;

    /**
     * @var ShippingOptionInterface
     */
    protected ShippingOptionInterface $shippingOption;

    /**
     * @var CheckoutSession
     */
    protected CheckoutSession $checkoutSession;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var QuoteSelectionRepository
     */
    protected QuoteSelectionRepository $quoteSelectionRepository;
    
    /**
     * @var MapBoxConfig Holds the Mapbox configuration.
     */
    private MapBoxConfig $mapBoxConfig;

    /**
     * @param ModuleConfig $moduleConfig
     * @param StoreManagerInterface $storeManager
     * @param CheckoutManagementInterface $checkoutManagement
     * @param QuoteSelectionFactory $quoteSelectionFactory
     * @param QuoteSelectionManager $quoteSelectionManager
     * @param ShippingOptionInterface $shippingOption
     * @param CheckoutSession $checkoutSession
     * @param ScopeConfigInterface $scopeConfig
     * @param QuoteSelectionRepository $quoteSelectionRepository
     * @param MapBoxConfig $mapBoxConfig
     */
    public function __construct(
        ModuleConfig                $moduleConfig,
        StoreManagerInterface       $storeManager,
        CheckoutManagementInterface $checkoutManagement,
        QuoteSelectionFactory       $quoteSelectionFactory,
        QuoteSelectionManager       $quoteSelectionManager,
        ShippingOptionInterface     $shippingOption,
        CheckoutSession             $checkoutSession,
        ScopeConfigInterface        $scopeConfig,
        QuoteSelectionRepository    $quoteSelectionRepository,
        MapBoxConfig                $mapBoxConfig
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
    }

    /**
     * @param string $code
     * @return array
     */
    protected function loadFromDb(string $code): array
    {
        $result = [];
        $quoteSelections = $this->getExistingQuoteSelections();

        foreach ($quoteSelections as $quoteSelection) {
            if ($quoteSelection->getShippingOptionCode() == $code) {
                $result[$quoteSelection->getInputCode()] = $quoteSelection;
            }
        }
        return $result;
    }

    /**
     * @return false|\Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote
     * @throws LocalizedException
     */
    protected function getQuote(): ?\Magento\Quote\Api\Data\CartInterface
    {
        $quote = false;

        try {
            $quote = $this->checkoutSession->getQuote();
        } catch (NoSuchEntityException $e) {
            $this->dispatchErrorMessage($e->getMessage());
        }

        return $quote;
    }

    /**
     * @return false|\Magento\Quote\Model\Quote\Address
     * @throws LocalizedException
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
     * @return int|null
     * @throws LocalizedException
     */
    protected function getAddressId(): ?int
    {
        $id = null;

        if ($address = $this->getShippingAddress()) {
            $id = (int) $address->getId();
        }

        return $id;
    }

        
    public function getMapboxApiToken(): string
    {
        return $this->mapBoxConfig->getApiToken();
    }
    
    /**
     * @return array
     * @throws LocalizedException
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
     * @param QuoteSelection $quoteSelection
     * @return mixed
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function updateShippingOptionSelections(QuoteSelection $quoteSelection): array
    {
        $quoteId = (int) $this->checkoutSession->getQuote()->getId();
        $quoteSelections = $this->getExistingQuoteSelections();

        foreach ($quoteSelections as $key => $selection) {
            if (
                $selection->getShippingOptionCode() == $quoteSelection->getShippingOptionCode() &&
                $selection->getInputCode() == $quoteSelection->getInputCode()
            ) {
                unset($quoteSelections[$key]);
                break;
            }
        }

        if (!empty($quoteSelection->getInputValue())) {
            $quoteSelections[] = $quoteSelection;
        }

        $this->checkoutManagement->updateShippingOptionSelections($quoteId, $quoteSelections);

        // Rückgabe der aktualisierten Selections
        return $quoteSelections;
    }
    
    /**
     * Generic function to persist the updated field.
     * 
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
}
