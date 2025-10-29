#!/bin/bash
# ============================================================================
# Script: Aplicar migraciones de base de datos en VPS
# ============================================================================

echo "🔄 Aplicando migración 006: credential_public_key..."
mysql -u sphoria_user -p'f1Ga8g4W4s8yM8D' sphoria_db <<'EOF'
ALTER TABLE `user_devices`
ADD COLUMN `credential_public_key` TEXT NULL COMMENT 'Clave pública del credential WebAuthn (base64)' AFTER `credential_id`,
ADD COLUMN `credential_counter` INT UNSIGNED DEFAULT 0 COMMENT 'Contador de firmas del authenticator' AFTER `credential_public_key`,
ADD COLUMN `transports` JSON NULL COMMENT 'Transports soportados por el authenticator' AFTER `credential_counter`,
ADD INDEX `idx_credential_id` (`credential_id`);
EOF

if [ $? -eq 0 ]; then
    echo "✅ Migración 006 aplicada exitosamente"
else
    echo "❌ Error aplicando migración 006"
    exit 1
fi

echo ""
echo "🔄 Aplicando migración 007: gasless_actions..."
mysql -u sphoria_user -p'f1Ga8g4W4s8yM8D' sphoria_db <<'EOF'
CREATE TABLE IF NOT EXISTS `gasless_actions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Usuario que ejecuta la acción',
    `smart_account_address` VARCHAR(42) NOT NULL COMMENT 'Smart account que ejecuta',
    `recipient` VARCHAR(42) NOT NULL COMMENT 'Dirección que recibe los tokens',
    `action_type` ENUM('TIP','PAYMENT','UNLOCK','VOTE','DONATION','BOUNTY_CLAIM') NOT NULL,
    `amount_wei` VARCHAR(78) NOT NULL COMMENT 'Cantidad en Wei',
    `metadata` TEXT NULL COMMENT 'Metadata JSON adicional',
    `relay_task_id` VARCHAR(66) NULL COMMENT 'Task ID de Gelato Relay',
    `status` ENUM('pending','executed','failed','cancelled') DEFAULT 'pending',
    `tx_hash` VARCHAR(66) NULL COMMENT 'Hash de la transacción ejecutada',
    `fail_reason` TEXT NULL COMMENT 'Razón del fallo si status=failed',
    `executed_at` DATETIME NULL COMMENT 'Cuándo se ejecutó la acción',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_actions` (`user_id`, `created_at` DESC),
    INDEX `idx_recipient_actions` (`recipient`, `created_at` DESC),
    INDEX `idx_relay_task` (`relay_task_id`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_gasless_actions_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOF

if [ $? -eq 0 ]; then
    echo "✅ Migración 007 aplicada exitosamente"
else
    echo "❌ Error aplicando migración 007"
    exit 1
fi

echo ""
echo "✅ Todas las migraciones aplicadas correctamente"
echo ""
echo "📊 Verificando tablas..."
mysql -u sphoria_user -p'f1Ga8g4W4s8yM8D' sphoria_db -e "DESCRIBE user_devices;" | grep credential
mysql -u sphoria_user -p'f1Ga8g4W4s8yM8D' sphoria_db -e "SHOW TABLES LIKE 'gasless_actions';"
