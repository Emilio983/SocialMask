-- Zcash Wallet Database Schema
-- Version: 1.0.0
-- Description: Initial schema for seedless, non-custodial Zcash wallet

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Users table
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_login TIMESTAMPTZ
);

-- Device credentials table (WebAuthn/Passkeys)
CREATE TABLE device_credentials (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    credential_id TEXT NOT NULL UNIQUE,
    public_key TEXT NOT NULL,
    counter BIGINT NOT NULL DEFAULT 0,
    device_name TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_used TIMESTAMPTZ
);

-- Wallets table
CREATE TABLE wallets (
    user_id UUID PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    ua TEXT NOT NULL, -- Unified Address
    ivk_enc BYTEA, -- Encrypted incoming viewing key (optional backup)
    ovk_enc BYTEA, -- Encrypted outgoing viewing key (optional backup)
    spending_key_enc BYTEA, -- Encrypted spending key backup
    backup_uploaded BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Transaction log table
CREATE TABLE tx_log (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    txid TEXT NOT NULL,
    direction TEXT NOT NULL CHECK (direction IN ('incoming', 'outgoing')),
    amount_zats BIGINT NOT NULL,
    to_addr TEXT,
    from_addr TEXT,
    memo TEXT,
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'confirmed', 'failed')),
    block_height BIGINT,
    confirmations INT DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Sync state table (tracks wallet sync progress)
CREATE TABLE sync_state (
    user_id UUID PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    last_synced_height BIGINT NOT NULL DEFAULT 0,
    last_synced_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    is_syncing BOOLEAN DEFAULT FALSE
);

-- Indexes for performance
CREATE INDEX idx_device_credentials_user_id ON device_credentials(user_id);
CREATE INDEX idx_device_credentials_credential_id ON device_credentials(credential_id);
CREATE INDEX idx_tx_log_user_id ON tx_log(user_id);
CREATE INDEX idx_tx_log_txid ON tx_log(txid);
CREATE INDEX idx_tx_log_status ON tx_log(status);
CREATE INDEX idx_tx_log_created_at ON tx_log(created_at DESC);

-- Updated_at trigger function
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Apply updated_at triggers
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_wallets_updated_at BEFORE UPDATE ON wallets
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_tx_log_updated_at BEFORE UPDATE ON tx_log
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Comments
COMMENT ON TABLE users IS 'User accounts (no PII stored)';
COMMENT ON TABLE device_credentials IS 'WebAuthn/Passkey credentials per device';
COMMENT ON TABLE wallets IS 'Wallet data including UA and encrypted key backups';
COMMENT ON TABLE tx_log IS 'Transaction history and status tracking';
COMMENT ON TABLE sync_state IS 'Blockchain sync progress per wallet';
COMMENT ON COLUMN wallets.ivk_enc IS 'Encrypted incoming viewing key for backup/recovery';
COMMENT ON COLUMN wallets.spending_key_enc IS 'Encrypted spending key, never decrypted on server';
