<?php

declare(strict_types=1);

namespace Hyva\ShippingDhlDe\Magewire;

use Dhl\Paket\Model\Config\ModuleConfig;
use Dhl\Paket\Model\ShippingSettings\ShippingOption\Codes;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\Selection\SelectionInterface;

class GoGreenPlus extends ShippingOptions
{
    /**
     * @var bool
     */
    public bool $goGreenPlusEnabled = false;

    /**
     * @var float
     */
    public float $fee = 0.0;

    /**
     * @var bool
     */
    public bool $disabled = false;

    /**
     * @var string[]
     */
    protected $listeners = [
        
    ];

    /**
     * Lädt den initialen Status der Komponente.
     */
    public function mount(): void
    {
        /** @var SelectionInterface[] $quoteSelections */
        $quoteSelections = $this->loadFromDb(Codes::SERVICE_OPTION_GOGREEN_PLUS);

        if ($quoteSelections && isset($quoteSelections['enabled'])) {
            $this->goGreenPlusEnabled = (bool) $quoteSelections['enabled']->getInputValue();
        }

        $this->fee = (float) $this->scopeConfig->getValue(ModuleConfig::CONFIG_PATH_GOGREEN_PLUS_CHARGE);
    }

    /**
     * Kann aufgerufen werden, um den Status an andere Komponenten zu senden.
     */
    public function init(): void
    {
        $this->dispatchEmit();
    }

    /**
     * Sendet den aktuellen Status dieser Komponente als Event.
     */
    protected function dispatchEmit(): void
    {
        // Aktuell benötigt keine andere Komponente diese Information.
        // Die Struktur ist aber für zukünftige Erweiterungen vorbereitet.
        $this->emit('updated_gogreen_plus', [
            'goGreenPlusEnabled' => $this->goGreenPlusEnabled
        ]);
    }

    /**
     * Wird aufgerufen, wenn die Checkbox im Frontend geändert wird.
     *
     * @param bool $value
     * @return mixed
     */
    public function updatedGoGreenPlusEnabled(bool $value): mixed
    {
        // Sendet den neuen Status an andere (potenzielle) Listener
        $this->dispatchEmit();
        
        // Speichert den neuen Wert in der Datenbank
        return $this->persistFieldUpdate(
            'enabled',
            $value,
            Codes::SERVICE_OPTION_GOGREEN_PLUS
        );
    }
}