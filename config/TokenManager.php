<?php

/**
 * TokenManager - Gestión dinámica de tokens para el sistema de donaciones
 * 
 * Esta clase proporciona métodos para trabajar con múltiples tokens de forma flexible.
 * Permite cambiar entre SPHE, Polygon MATIC, o tokens personalizados configurados en .env
 * 
 * Uso:
 * ```php
 * $tokenManager = new TokenManager();
 * $activeToken = $tokenManager->getActiveToken(); // Obtiene configuración del token activo
 * $isValid = $tokenManager->validateDonation(10.5); // Valida monto de donación
 * $formatted = $tokenManager->formatAmount(10.5); // "10.50 SPHE"
 * ```
 * 
 * @package Config
 * @version 1.0.0
 * @since FASE 3.1
 */

require_once __DIR__ . '/env.php';

use TheSocialMask\Config\Env;

class TokenManager
{
    private array $activeToken;
    private array $donationSettings;

    /**
     * Constructor - Carga la configuración del token activo
     */
    public function __construct()
    {
        Env::load();
        $this->activeToken = Env::activeToken();
        $this->donationSettings = Env::donations();
    }

    /**
     * Obtiene la configuración completa del token activo
     *
     * @return array{address: string, symbol: string, decimals: int, name: string, minDonation: float, blockchain: string, chainId: int, rpcUrl: string|null, explorerUrl: string|null}
     */
    public function getActiveToken(): array
    {
        return $this->activeToken;
    }

    /**
     * Obtiene el símbolo del token activo (ej: "SPHE", "MATIC")
     *
     * @return string
     */
    public function getSymbol(): string
    {
        return $this->activeToken['symbol'];
    }

    /**
     * Obtiene la dirección del contrato del token activo
     *
     * @return string
     */
    public function getAddress(): string
    {
        return $this->activeToken['address'];
    }

    /**
     * Obtiene la cantidad de decimales del token activo
     *
     * @return int
     */
    public function getDecimals(): int
    {
        return $this->activeToken['decimals'];
    }

    /**
     * Obtiene la donación mínima permitida
     *
     * @return float
     */
    public function getMinDonation(): float
    {
        return $this->activeToken['minDonation'];
    }

    /**
     * Obtiene el nombre completo del token
     *
     * @return string
     */
    public function getTokenName(): string
    {
        return $this->activeToken['name'];
    }

    /**
     * Obtiene el ID de la blockchain (137 para Polygon, 1 para Ethereum, etc.)
     *
     * @return int
     */
    public function getChainId(): int
    {
        return $this->activeToken['chainId'];
    }

    /**
     * Obtiene la URL del RPC de la blockchain
     *
     * @return string|null
     */
    public function getRpcUrl(): ?string
    {
        return $this->activeToken['rpcUrl'];
    }

    /**
     * Obtiene la URL del explorador de blockchain (PolygonScan, Etherscan, etc.)
     *
     * @return string|null
     */
    public function getExplorerUrl(): ?string
    {
        return $this->activeToken['explorerUrl'];
    }

    /**
     * Valida si un monto de donación es válido
     *
     * @param float $amount Monto a validar
     * @return bool True si es válido, False si es menor al mínimo
     */
    public function validateDonation(float $amount): bool
    {
        return $amount >= $this->activeToken['minDonation'];
    }

    /**
     * Calcula la comisión de plataforma para una donación
     *
     * @param float $amount Monto de la donación
     * @return float Monto de la comisión
     */
    public function calculateFee(float $amount): float
    {
        return $amount * ($this->donationSettings['feePercentage'] / 100);
    }

    /**
     * Calcula el monto neto que recibirá el destinatario (monto - comisión)
     *
     * @param float $amount Monto bruto de la donación
     * @return float Monto neto después de comisiones
     */
    public function calculateNetAmount(float $amount): float
    {
        return $amount - $this->calculateFee($amount);
    }

    /**
     * Formatea un monto con el símbolo del token y decimales correctos
     *
     * @param float $amount Monto a formatear
     * @param bool $includeSymbol Si incluir o no el símbolo del token
     * @return string Monto formateado (ej: "10.50 SPHE" o "10.50")
     */
    public function formatAmount(float $amount, bool $includeSymbol = true): string
    {
        $formatted = number_format($amount, 2, '.', ',');
        return $includeSymbol ? "{$formatted} {$this->activeToken['symbol']}" : $formatted;
    }

    /**
     * Convierte un monto de la unidad base (wei para ETH/tokens ERC20) a la unidad legible
     * Ejemplo: 1000000000000000000 wei = 1.0 SPHE (18 decimales)
     *
     * @param string $weiAmount Monto en unidad base (wei)
     * @return float Monto en unidad legible
     */
    public function fromWei(string $weiAmount): float
    {
        $divisor = bcpow('10', (string) $this->activeToken['decimals']);
        return (float) bcdiv($weiAmount, $divisor, $this->activeToken['decimals']);
    }

    /**
     * Convierte un monto de la unidad legible a la unidad base (wei)
     * Ejemplo: 1.0 SPHE = 1000000000000000000 wei (18 decimales)
     *
     * @param float $amount Monto en unidad legible
     * @return string Monto en unidad base (wei)
     */
    public function toWei(float $amount): string
    {
        $multiplier = bcpow('10', (string) $this->activeToken['decimals']);
        return bcmul((string) $amount, $multiplier, 0);
    }

    /**
     * Obtiene información completa de una donación para frontend
     *
     * @param float $amount Monto de la donación
     * @return array{amount: float, amountFormatted: string, fee: float, feeFormatted: string, netAmount: float, netAmountFormatted: string, token: array, isValid: bool, errorMessage: string|null}
     */
    public function getDonationInfo(float $amount): array
    {
        $isValid = $this->validateDonation($amount);
        $fee = $this->calculateFee($amount);
        $netAmount = $this->calculateNetAmount($amount);

        return [
            'amount' => $amount,
            'amountFormatted' => $this->formatAmount($amount),
            'fee' => $fee,
            'feeFormatted' => $this->formatAmount($fee),
            'netAmount' => $netAmount,
            'netAmountFormatted' => $this->formatAmount($netAmount),
            'token' => $this->activeToken,
            'isValid' => $isValid,
            'errorMessage' => $isValid ? null : "Monto mínimo de donación: {$this->formatAmount($this->activeToken['minDonation'])}",
        ];
    }

    /**
     * Genera el enlace al explorador de blockchain para una transacción
     *
     * @param string $txHash Hash de la transacción
     * @return string|null URL al explorador o null si no hay explorador configurado
     */
    public function getTransactionLink(string $txHash): ?string
    {
        $explorerUrl = $this->activeToken['explorerUrl'];
        if (!$explorerUrl) {
            return null;
        }

        return rtrim($explorerUrl, '/') . '/tx/' . $txHash;
    }

    /**
     * Genera el enlace al explorador de blockchain para una dirección
     *
     * @param string $address Dirección de wallet
     * @return string|null URL al explorador o null si no hay explorador configurado
     */
    public function getAddressLink(string $address): ?string
    {
        $explorerUrl = $this->activeToken['explorerUrl'];
        if (!$explorerUrl) {
            return null;
        }

        return rtrim($explorerUrl, '/') . '/address/' . $address;
    }

    /**
     * Obtiene la lista de todos los tokens soportados
     *
     * @return array<string>
     */
    public function getSupportedTokens(): array
    {
        return Env::supportedTokens();
    }

    /**
     * Obtiene la configuración de un token específico
     *
     * @param string $tokenName Nombre del token ('sphe', 'polygon', 'custom')
     * @return array Token configuration
     */
    public function getTokenConfig(string $tokenName): array
    {
        return Env::tokenConfig($tokenName);
    }

    /**
     * Verifica si un token está configurado y listo para usar
     *
     * @param string $tokenName Nombre del token
     * @return bool True si está configurado
     */
    public function isTokenConfigured(string $tokenName): bool
    {
        return Env::isTokenConfigured($tokenName);
    }

    /**
     * Obtiene estadísticas de la configuración de tokens
     *
     * @return array{activeToken: string, totalConfigured: int, configured: array, notConfigured: array}
     */
    public function getTokenStats(): array
    {
        $supported = $this->getSupportedTokens();
        $configured = [];
        $notConfigured = [];

        foreach ($supported as $token) {
            if ($this->isTokenConfigured($token)) {
                $configured[] = $token;
            } else {
                $notConfigured[] = $token;
            }
        }

        return [
            'activeToken' => $this->donationSettings['activeToken'],
            'totalConfigured' => count($configured),
            'configured' => $configured,
            'notConfigured' => $notConfigured,
        ];
    }

    /**
     * Valida la configuración del token activo
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public function validateActiveToken(): array
    {
        $errors = [];

        if (empty($this->activeToken['address'])) {
            $errors[] = "Token address is missing";
        }

        if (empty($this->activeToken['symbol'])) {
            $errors[] = "Token symbol is missing";
        }

        if ($this->activeToken['decimals'] < 0 || $this->activeToken['decimals'] > 18) {
            $errors[] = "Token decimals must be between 0 and 18";
        }

        if ($this->activeToken['minDonation'] <= 0) {
            $errors[] = "Minimum donation must be greater than 0";
        }

        if ($this->activeToken['chainId'] <= 0) {
            $errors[] = "Invalid chain ID";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
