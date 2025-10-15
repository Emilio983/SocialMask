<?php
/**
 * PRICING HELPER
 * Funciones para obtener precios dinámicos desde platform_settings
 */

require_once __DIR__ . '/../../config/connection.php';

class PricingHelper {
    private $pdo;
    private static $cache = [];

    public function __construct($pdo_instance = null) {
        global $pdo;
        $this->pdo = $pdo_instance ?? $pdo;
    }

    /**
     * Obtiene un valor de configuración
     */
    public function getSetting($key, $default = null) {
        // Check cache first
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        try {
            $stmt = $this->pdo->prepare("SELECT setting_value, setting_type FROM platform_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return $default;
            }

            $value = $result['setting_value'];

            // Convert based on type
            switch ($result['setting_type']) {
                case 'number':
                    $value = floatval($value);
                    break;
                case 'boolean':
                    $value = ($value === 'true' || $value === '1');
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }

            // Cache it
            self::$cache[$key] = $value;

            return $value;
        } catch (PDOException $e) {
            error_log("Error getting setting {$key}: " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Obtiene precio de membresía
     */
    public function getMembershipPrice($plan) {
        $plan = strtolower($plan);

        if ($plan === 'free') {
            return 0;
        }

        $key = "membership_{$plan}_price";
        return $this->getSetting($key, $this->getDefaultMembershipPrice($plan));
    }

    /**
     * Precios por defecto (fallback)
     */
    private function getDefaultMembershipPrice($plan) {
        $defaults = [
            'platinum' => 100,
            'gold' => 250,
            'diamond' => 500,
            'creator' => 750
        ];
        return $defaults[$plan] ?? 0;
    }

    /**
     * Obtiene todos los precios de membresías
     */
    public function getAllMembershipPrices() {
        return [
            'free' => 0,
            'platinum' => $this->getMembershipPrice('platinum'),
            'gold' => $this->getMembershipPrice('gold'),
            'diamond' => $this->getMembershipPrice('diamond'),
            'creator' => $this->getMembershipPrice('creator')
        ];
    }

    /**
     * Obtiene configuración de survey
     */
    public function getSurveySettings() {
        return [
            'min_entry_price' => $this->getSetting('survey_min_entry_price', 1),
            'creator_deposit' => $this->getSetting('survey_creator_deposit', 1000),
            'platform_commission' => $this->getSetting('survey_platform_commission', 10),
            'creator_commission' => $this->getSetting('survey_creator_commission', 10),
            'max_duration_hours' => $this->getSetting('survey_max_duration_hours', 168)
        ];
    }

    /**
     * Obtiene cooldowns de mensajes según plan
     */
    public function getMessageCooldown($membership_plan) {
        $plan = strtolower($membership_plan);
        $key = "rapid_message_cooldown_{$plan}";
        return $this->getSetting($key, 300); // 5 min default
    }

    /**
     * Obtiene fee de transferencia
     */
    public function getTransferFee() {
        return $this->getSetting('transfer_fee_percentage', 1);
    }

    /**
     * Obtiene configuración de blockchain
     */
    public function getBlockchainSettings() {
        return [
            'chain_id' => $this->getSetting('polygon_chain_id', 137),
            'sphe_contract' => $this->getSetting('sphe_contract_address', '0x059cf53146E980321e7E1EEF43bb5Fe51BB6565b'),
            'treasury_wallet' => $this->getSetting('treasury_wallet', '0xa1052872c755B5B2192b54ABD5F08546eeE6aa20'),
            'min_confirmations' => $this->getSetting('min_confirmations', 1)
        ];
    }

    /**
     * Limpiar cache (útil después de actualizar configuración)
     */
    public static function clearCache() {
        self::$cache = [];
    }
}

// Crear instancia global
$pricing = new PricingHelper();
?>
