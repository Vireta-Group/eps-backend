-- ============================================================
--  MODULE 01 : SaaS Platform & System Settings
--  Database   : PostgreSQL 15+
--  Version    : 2.0.0  (PostgreSQL rewrite)
--  Tables     : 43
--  ENUMs      : 16
--  Views      : 10
--  Indexes    : 75+
-- ============================================================
--  Conventions
--  ─────────────────────────────────────────────────────────
--  • PK          : UUID  gen_random_uuid()
--  • Timestamps  : TIMESTAMPTZ  (always timezone-aware)
--  • Soft Delete : deleted_at  TIMESTAMPTZ  (NULL = alive)
--  • Status      : record_status ENUM on every table
--  • Demo data   : is_demo BOOLEAN  (isolated, auto-cleanup)
--  • Cross-module FK : soft reference (comment only, no constraint)
--  • Intra-module FK : hard FOREIGN KEY constraint
--  • Settings    : JSONB  for flexible key-value pairs
-- ============================================================

BEGIN;

-- ============================================================
-- 0.  EXTENSIONS
-- ============================================================
CREATE EXTENSION IF NOT EXISTS "pgcrypto";      -- gen_random_uuid()
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";     -- uuid_generate_v4()  fallback
CREATE EXTENSION IF NOT EXISTS "pg_trgm";       -- fast ILIKE / fuzzy search
CREATE EXTENSION IF NOT EXISTS "btree_gin";     -- GIN index on scalar cols

-- ============================================================
-- 0.  ARCHIVE SCHEMA
--     Yearly data snapshots live here.  Main schema stays lean.
-- ============================================================
CREATE SCHEMA IF NOT EXISTS archive;

-- ============================================================
-- 1.  ENUMS
--     All ENUMs are created inside DO blocks so re-running
--     this script is safe (idempotent).
-- ============================================================

DO $$ BEGIN CREATE TYPE record_status AS ENUM (
    'active','inactive','suspended','deleted','archived'
); EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN CREATE TYPE billing_cycle AS ENUM (
    'monthly','quarterly','yearly','lifetime'
); EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN CREATE TYPE tenant_status AS ENUM (
    'active','trial','setup','suspended','cancelled','deleted'
); EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN CREATE TYPE subscription_status AS ENUM (
    'active','trial','past_due','paused','cancelled','expired'
); EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN CREATE TYPE invoice_status AS ENUM (
    'draft','sent','paid','partial','overdue','cancelled','void'
); EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN CREATE TYPE payment_method_type AS ENUM (
    'bkash','nagad','rocket','card','bank_transfer','cash','cheque','other'
); EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN CREATE TYPE payment_status_type AS ENUM (
    'pending','completed','failed','refunded','partial'
); EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN CREATE TYPE user_type AS ENUM (
    'super_admin','tenant_admin','principal','vice_principal',
    'teacher','student','parent','staff','accountant',
    'librarian','driver','canteen_manager','custom'
); EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN CREATE TYPE user_status_type AS ENUM (
    'active','inactive','suspended','pending_verification','deleted'
); EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN CREATE TYPE notif_channel AS ENUM (
    'sms','email','push','whatsapp','in_app'
); EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN CREATE TYPE notif_status AS ENUM (
    'pending','sent','delivered','failed','read'
); EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN CREATE TYPE media_file_type AS ENUM (
    'image','video','document','audio','archive','other'
); EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN CREATE TYPE ticket_status_type AS ENUM (
    'open','in_progress','pending_customer','resolved','closed','cancelled'
); EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN CREATE TYPE ticket_priority AS ENUM (
    'low','medium','high','critical'
); EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN CREATE TYPE dunning_action_type AS ENUM (
    'warning_1','warning_2','final_warning',
    'soft_suspension','hard_suspension','cancellation'
); EXCEPTION WHEN duplicate_object THEN null; END $$;

DO $$ BEGIN CREATE TYPE announcement_type AS ENUM (
    'info','warning','critical','success','maintenance'
); EXCEPTION WHEN duplicate_object THEN null; END $$;

-- ============================================================
-- 2.  HELPER — auto-update updated_at
-- ============================================================
CREATE OR REPLACE FUNCTION fn_set_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$;

-- ============================================================
-- GROUP 1 :  SaaS Core
-- ============================================================

-- ------------------------------------------------------------
-- 1.  plans
--     Subscription tier catalogue (Basic / Standard / Premium…)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS plans (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name                VARCHAR(100)    NOT NULL,
    slug                VARCHAR(100)    NOT NULL UNIQUE,   -- 'basic','standard','premium'
    description         TEXT,
    price_monthly       NUMERIC(10,2)   NOT NULL DEFAULT 0,
    price_yearly        NUMERIC(10,2)   NOT NULL DEFAULT 0,
    price_quarterly     NUMERIC(10,2)   NOT NULL DEFAULT 0,
    setup_fee           NUMERIC(10,2)   NOT NULL DEFAULT 0,
    currency            CHAR(3)         NOT NULL DEFAULT 'BDT',
    trial_days          SMALLINT        NOT NULL DEFAULT 14,
    max_students        INTEGER         NOT NULL DEFAULT 500,
    max_teachers        INTEGER         NOT NULL DEFAULT 50,
    max_staff           INTEGER         NOT NULL DEFAULT 100,
    max_branches        SMALLINT        NOT NULL DEFAULT 1,
    storage_gb          NUMERIC(6,2)    NOT NULL DEFAULT 5,
    sms_per_month       INTEGER         NOT NULL DEFAULT 1000,
    api_calls_per_day   INTEGER         NOT NULL DEFAULT 1000,
    features            JSONB           NOT NULL DEFAULT '{}',  -- {custom_domain,whatsapp,biometric,ai,...}
    is_custom           BOOLEAN         NOT NULL DEFAULT FALSE,  -- negotiated plan
    display_order       SMALLINT        NOT NULL DEFAULT 0,
    -- soft delete & audit
    status              record_status   NOT NULL DEFAULT 'active',
    is_demo             BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at          TIMESTAMPTZ,
    deleted_by          UUID,           -- soft-ref → users.id
    delete_reason       TEXT,
    created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by          UUID,
    updated_by          UUID
);

CREATE TRIGGER trg_plans_updated_at
    BEFORE UPDATE ON plans
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_plans_active   ON plans (display_order) WHERE deleted_at IS NULL AND status = 'active';
CREATE INDEX idx_plans_is_demo  ON plans (is_demo)       WHERE is_demo = TRUE;

-- ------------------------------------------------------------
-- 2.  plan_features
--     Key-value feature list per plan
--     (allows UI to render feature comparison table)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS plan_features (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    plan_id         UUID            NOT NULL
                        REFERENCES plans (id) ON DELETE CASCADE,
    feature_key     VARCHAR(100)    NOT NULL,   -- 'mobile_app','biometric','hostel'
    feature_label   VARCHAR(200)    NOT NULL,
    feature_value   VARCHAR(200)    NOT NULL DEFAULT 'true',  -- 'true','500','unlimited'
    is_highlighted  BOOLEAN         NOT NULL DEFAULT FALSE,
    display_order   SMALLINT        NOT NULL DEFAULT 0,
    -- audit
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID,
    UNIQUE (plan_id, feature_key)
);

CREATE TRIGGER trg_plan_features_updated_at
    BEFORE UPDATE ON plan_features
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_plan_features_plan ON plan_features (plan_id);

-- ------------------------------------------------------------
-- 3.  addon_catalog
--     Extra purchasable modules / packs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS addon_catalog (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name            VARCHAR(100)    NOT NULL,
    slug            VARCHAR(100)    NOT NULL UNIQUE,
    description     TEXT,
    price_monthly   NUMERIC(10,2)   NOT NULL DEFAULT 0,
    price_yearly    NUMERIC(10,2)   NOT NULL DEFAULT 0,
    unit            VARCHAR(50)     NOT NULL DEFAULT 'fixed',  -- 'fixed','per_1000_sms','per_gb'
    metadata        JSONB           NOT NULL DEFAULT '{}',
    -- soft delete & audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID
);

CREATE TRIGGER trg_addon_catalog_updated_at
    BEFORE UPDATE ON addon_catalog
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

-- ------------------------------------------------------------
-- 4.  tenants
--     One row per school / institution
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tenants (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    -- Identity
    name_bn             VARCHAR(200)    NOT NULL,
    name_en             VARCHAR(200)    NOT NULL,
    name_short          VARCHAR(50),
    slug                VARCHAR(100)    NOT NULL UNIQUE,   -- subdomain part
    subdomain           VARCHAR(100)    NOT NULL UNIQUE,   -- full: slug.saas.com
    custom_domain       VARCHAR(200)    UNIQUE,
    logo_url            TEXT,
    -- School details
    school_type         VARCHAR(50)     NOT NULL DEFAULT 'high_school',
                        -- 'primary','high_school','college','madrasa','kindergarten','combined'
    eiin_number         VARCHAR(20)     UNIQUE,
    board_affiliation   VARCHAR(100),
    mpo_status          BOOLEAN         NOT NULL DEFAULT FALSE,
    mpo_index           VARCHAR(50),
    established_year    SMALLINT,
    tin_number          VARCHAR(30),
    -- Contact
    email               VARCHAR(200),
    phone_primary       VARCHAR(20),
    phone_secondary     VARCHAR(20),
    whatsapp_number     VARCHAR(20),
    website_url         TEXT,
    -- Plan & Billing
    current_plan_id     UUID
                            REFERENCES plans (id) ON DELETE RESTRICT,
    tenant_status       tenant_status   NOT NULL DEFAULT 'setup',
    trial_ends_at       TIMESTAMPTZ,
    -- Preferences (overrideable per tenant)
    timezone            VARCHAR(60)     NOT NULL DEFAULT 'Asia/Dhaka',
    default_language    VARCHAR(10)     NOT NULL DEFAULT 'bn',
    currency            CHAR(3)         NOT NULL DEFAULT 'BDT',
    academic_year_start SMALLINT        NOT NULL DEFAULT 1,   -- month: 1=Jan
    date_format         VARCHAR(30)     NOT NULL DEFAULT 'DD/MM/YYYY',
    -- Limits (can override plan limits)
    max_students        INTEGER,        -- NULL = use plan default
    max_teachers        INTEGER,
    max_staff           INTEGER,
    max_branches        SMALLINT,
    storage_gb          NUMERIC(6,2),
    sms_per_month       INTEGER,
    -- Metadata
    onboarding_step     SMALLINT        NOT NULL DEFAULT 1,
    onboarding_done     BOOLEAN         NOT NULL DEFAULT FALSE,
    settings            JSONB           NOT NULL DEFAULT '{}',
    -- Soft delete & audit
    status              record_status   NOT NULL DEFAULT 'active',
    is_demo             BOOLEAN         NOT NULL DEFAULT FALSE,
    demo_expires_at     TIMESTAMPTZ,
    deleted_at          TIMESTAMPTZ,
    deleted_by          UUID,
    delete_reason       TEXT,
    created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by          UUID,
    updated_by          UUID
);

CREATE TRIGGER trg_tenants_updated_at
    BEFORE UPDATE ON tenants
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_tenants_active    ON tenants (tenant_status) WHERE deleted_at IS NULL;
CREATE INDEX idx_tenants_plan      ON tenants (current_plan_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_tenants_demo      ON tenants (demo_expires_at) WHERE is_demo = TRUE;
CREATE INDEX idx_tenants_slug      ON tenants (slug);
CREATE INDEX idx_tenants_eiin      ON tenants (eiin_number) WHERE eiin_number IS NOT NULL;

-- ------------------------------------------------------------
-- 5.  tenant_contacts
--     Key contacts per tenant (Principal, Admin, Finance…)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tenant_contacts (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID            NOT NULL
                        REFERENCES tenants (id) ON DELETE CASCADE,
    contact_type    VARCHAR(50)     NOT NULL,  -- 'principal','admin','finance','support'
    name_bn         VARCHAR(200)    NOT NULL,
    name_en         VARCHAR(200),
    designation     VARCHAR(100),
    mobile          VARCHAR(20),
    email           VARCHAR(200),
    is_primary      BOOLEAN         NOT NULL DEFAULT FALSE,
    signature_url   TEXT,
    photo_url       TEXT,
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID
);

CREATE TRIGGER trg_tenant_contacts_updated_at
    BEFORE UPDATE ON tenant_contacts
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_tenant_contacts_tenant ON tenant_contacts (tenant_id) WHERE deleted_at IS NULL;

-- ------------------------------------------------------------
-- 6.  tenant_addresses
--     Physical addresses for a tenant (can have multiple branches)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tenant_addresses (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID            NOT NULL
                        REFERENCES tenants (id) ON DELETE CASCADE,
    address_type    VARCHAR(30)     NOT NULL DEFAULT 'main', -- 'main','branch','mailing'
    division        VARCHAR(100),
    district        VARCHAR(100),
    upazila         VARCHAR(100),
    post_office     VARCHAR(100),
    post_code       VARCHAR(10),
    village_area    TEXT,
    full_address    TEXT,
    latitude        NUMERIC(10,7),
    longitude       NUMERIC(10,7),
    google_map_url  TEXT,
    is_primary      BOOLEAN         NOT NULL DEFAULT FALSE,
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID
);

CREATE TRIGGER trg_tenant_addresses_updated_at
    BEFORE UPDATE ON tenant_addresses
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_tenant_addresses_tenant ON tenant_addresses (tenant_id) WHERE deleted_at IS NULL;

-- ============================================================
-- GROUP 2 : Billing
-- ============================================================

-- ------------------------------------------------------------
-- 7.  coupons
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS coupons (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code                VARCHAR(50)     NOT NULL UNIQUE,
    description         TEXT,
    discount_type       VARCHAR(20)     NOT NULL DEFAULT 'percentage', -- 'percentage','fixed_amount'
    discount_value      NUMERIC(10,2)   NOT NULL,
    max_uses            INTEGER,        -- NULL = unlimited
    used_count          INTEGER         NOT NULL DEFAULT 0,
    max_uses_per_tenant INTEGER         NOT NULL DEFAULT 1,
    applicable_plans    JSONB           NOT NULL DEFAULT '[]', -- [] = all plans
    min_order_amount    NUMERIC(10,2),
    valid_from          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    valid_until         TIMESTAMPTZ,
    -- audit
    status              record_status   NOT NULL DEFAULT 'active',
    is_demo             BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at          TIMESTAMPTZ,
    deleted_by          UUID,
    delete_reason       TEXT,
    created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by          UUID,
    updated_by          UUID
);

CREATE TRIGGER trg_coupons_updated_at
    BEFORE UPDATE ON coupons
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_coupons_code   ON coupons (code) WHERE deleted_at IS NULL;
CREATE INDEX idx_coupons_active ON coupons (valid_until) WHERE deleted_at IS NULL AND status = 'active';

-- ------------------------------------------------------------
-- 8.  subscriptions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subscriptions (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id           UUID            NOT NULL
                            REFERENCES tenants (id) ON DELETE RESTRICT,
    plan_id             UUID            NOT NULL
                            REFERENCES plans (id) ON DELETE RESTRICT,
    billing_cycle       billing_cycle   NOT NULL DEFAULT 'monthly',
    sub_status          subscription_status NOT NULL DEFAULT 'trial',
    -- Pricing (at time of subscription, may differ from plan price)
    price               NUMERIC(10,2)   NOT NULL,
    discount_amount     NUMERIC(10,2)   NOT NULL DEFAULT 0,
    final_price         NUMERIC(10,2)   NOT NULL,
    coupon_id           UUID            REFERENCES coupons (id) ON DELETE SET NULL,
    -- Dates
    trial_start         TIMESTAMPTZ,
    trial_end           TIMESTAMPTZ,
    current_period_start TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    current_period_end  TIMESTAMPTZ    NOT NULL,
    cancelled_at        TIMESTAMPTZ,
    cancellation_reason TEXT,
    -- Renewal
    auto_renew          BOOLEAN         NOT NULL DEFAULT TRUE,
    renewal_reminder_sent BOOLEAN       NOT NULL DEFAULT FALSE,
    -- Metadata
    notes               TEXT,
    metadata            JSONB           NOT NULL DEFAULT '{}',
    -- audit
    status              record_status   NOT NULL DEFAULT 'active',
    is_demo             BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at          TIMESTAMPTZ,
    deleted_by          UUID,
    delete_reason       TEXT,
    created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by          UUID,
    updated_by          UUID
);

CREATE TRIGGER trg_subscriptions_updated_at
    BEFORE UPDATE ON subscriptions
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_subscriptions_tenant ON subscriptions (tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_subscriptions_period ON subscriptions (current_period_end) WHERE sub_status = 'active';
CREATE INDEX idx_subscriptions_trial  ON subscriptions (trial_end) WHERE sub_status = 'trial';

-- ------------------------------------------------------------
-- 9.  subscription_overrides
--     Custom pricing or limit adjustments for specific tenants
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subscription_overrides (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID            NOT NULL
                        REFERENCES tenants (id) ON DELETE CASCADE,
    override_key    VARCHAR(100)    NOT NULL,  -- 'max_students','price_monthly','storage_gb'
    override_value  TEXT            NOT NULL,
    reason          TEXT,
    expires_at      TIMESTAMPTZ,
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID,
    UNIQUE (tenant_id, override_key)
);

CREATE TRIGGER trg_subscription_overrides_updated_at
    BEFORE UPDATE ON subscription_overrides
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_sub_overrides_tenant ON subscription_overrides (tenant_id) WHERE deleted_at IS NULL;

-- ------------------------------------------------------------
-- 10.  subscription_addons
--      Addons active for a tenant subscription
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subscription_addons (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    subscription_id UUID            NOT NULL
                        REFERENCES subscriptions (id) ON DELETE CASCADE,
    addon_id        UUID            NOT NULL
                        REFERENCES addon_catalog (id) ON DELETE RESTRICT,
    quantity        INTEGER         NOT NULL DEFAULT 1,
    unit_price      NUMERIC(10,2)   NOT NULL,
    total_price     NUMERIC(10,2)   NOT NULL,
    starts_at       TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    ends_at         TIMESTAMPTZ,
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID
);

CREATE TRIGGER trg_subscription_addons_updated_at
    BEFORE UPDATE ON subscription_addons
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_sub_addons_sub ON subscription_addons (subscription_id) WHERE deleted_at IS NULL;

-- ------------------------------------------------------------
-- 11.  coupon_redemptions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS coupon_redemptions (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    coupon_id       UUID            NOT NULL
                        REFERENCES coupons (id) ON DELETE RESTRICT,
    tenant_id       UUID            NOT NULL
                        REFERENCES tenants (id) ON DELETE RESTRICT,
    subscription_id UUID            REFERENCES subscriptions (id) ON DELETE SET NULL,
    discount_applied NUMERIC(10,2)  NOT NULL,
    redeemed_at     TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    -- audit
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    UNIQUE (coupon_id, tenant_id)   -- one coupon per tenant
);

CREATE INDEX idx_coupon_redemptions_coupon ON coupon_redemptions (coupon_id);
CREATE INDEX idx_coupon_redemptions_tenant ON coupon_redemptions (tenant_id);

-- ------------------------------------------------------------
-- 12.  invoices
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoices (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_number  VARCHAR(50)     NOT NULL UNIQUE,  -- INV-2025-0001
    tenant_id       UUID            NOT NULL
                        REFERENCES tenants (id) ON DELETE RESTRICT,
    subscription_id UUID            REFERENCES subscriptions (id) ON DELETE SET NULL,
    inv_status      invoice_status  NOT NULL DEFAULT 'draft',
    currency        CHAR(3)         NOT NULL DEFAULT 'BDT',
    subtotal        NUMERIC(12,2)   NOT NULL DEFAULT 0,
    discount_amount NUMERIC(12,2)   NOT NULL DEFAULT 0,
    tax_amount      NUMERIC(12,2)   NOT NULL DEFAULT 0,
    total_amount    NUMERIC(12,2)   NOT NULL DEFAULT 0,
    paid_amount     NUMERIC(12,2)   NOT NULL DEFAULT 0,
    due_amount      NUMERIC(12,2)   GENERATED ALWAYS AS (total_amount - paid_amount) STORED,
    due_date        DATE            NOT NULL,
    issued_at       TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    paid_at         TIMESTAMPTZ,
    notes           TEXT,
    metadata        JSONB           NOT NULL DEFAULT '{}',
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID
);

CREATE TRIGGER trg_invoices_updated_at
    BEFORE UPDATE ON invoices
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_invoices_tenant  ON invoices (tenant_id, inv_status) WHERE deleted_at IS NULL;
CREATE INDEX idx_invoices_due     ON invoices (due_date) WHERE inv_status IN ('sent','partial','overdue');
CREATE INDEX idx_invoices_number  ON invoices (invoice_number);

-- ------------------------------------------------------------
-- 13.  invoice_items
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoice_items (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_id      UUID            NOT NULL
                        REFERENCES invoices (id) ON DELETE CASCADE,
    description     VARCHAR(300)    NOT NULL,
    item_type       VARCHAR(50)     NOT NULL DEFAULT 'plan',  -- 'plan','addon','adjustment','setup_fee'
    quantity        NUMERIC(8,2)    NOT NULL DEFAULT 1,
    unit_price      NUMERIC(12,2)   NOT NULL,
    discount        NUMERIC(12,2)   NOT NULL DEFAULT 0,
    tax_rate        NUMERIC(5,2)    NOT NULL DEFAULT 0,
    line_total      NUMERIC(12,2)   NOT NULL,
    display_order   SMALLINT        NOT NULL DEFAULT 0,
    -- audit
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE TRIGGER trg_invoice_items_updated_at
    BEFORE UPDATE ON invoice_items
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_invoice_items_invoice ON invoice_items (invoice_id);

-- ------------------------------------------------------------
-- 14.  payments
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    payment_ref         VARCHAR(100)    NOT NULL UNIQUE,  -- PAY-2025-0001
    tenant_id           UUID            NOT NULL
                            REFERENCES tenants (id) ON DELETE RESTRICT,
    invoice_id          UUID            REFERENCES invoices (id) ON DELETE SET NULL,
    pay_status          payment_status_type NOT NULL DEFAULT 'pending',
    pay_method          payment_method_type NOT NULL DEFAULT 'bkash',
    amount              NUMERIC(12,2)   NOT NULL,
    currency            CHAR(3)         NOT NULL DEFAULT 'BDT',
    gateway_txn_id      VARCHAR(200),   -- bKash TrxID / bank ref
    gateway_response    JSONB           NOT NULL DEFAULT '{}',
    paid_at             TIMESTAMPTZ,
    refunded_amount     NUMERIC(12,2)   NOT NULL DEFAULT 0,
    refunded_at         TIMESTAMPTZ,
    refund_reason       TEXT,
    notes               TEXT,
    -- audit
    status              record_status   NOT NULL DEFAULT 'active',
    is_demo             BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at          TIMESTAMPTZ,
    deleted_by          UUID,
    delete_reason       TEXT,
    created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by          UUID,
    updated_by          UUID
);

CREATE TRIGGER trg_payments_updated_at
    BEFORE UPDATE ON payments
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_payments_tenant   ON payments (tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_payments_invoice  ON payments (invoice_id) WHERE invoice_id IS NOT NULL;
CREATE INDEX idx_payments_gateway  ON payments (gateway_txn_id) WHERE gateway_txn_id IS NOT NULL;
CREATE INDEX idx_payments_status   ON payments (pay_status) WHERE deleted_at IS NULL;

-- ------------------------------------------------------------
-- 15.  dunning_logs
--      Payment follow-up automation history
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dunning_logs (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID            NOT NULL
                        REFERENCES tenants (id) ON DELETE CASCADE,
    invoice_id      UUID            REFERENCES invoices (id) ON DELETE SET NULL,
    action          dunning_action_type NOT NULL,
    channel         notif_channel   NOT NULL DEFAULT 'email',
    message         TEXT,
    sent_at         TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    response        TEXT,           -- delivery receipt
    -- audit
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID
);

CREATE INDEX idx_dunning_tenant  ON dunning_logs (tenant_id);
CREATE INDEX idx_dunning_invoice ON dunning_logs (invoice_id) WHERE invoice_id IS NOT NULL;

-- ============================================================
-- GROUP 3 : Users / Auth / Permissions
-- ============================================================

-- ------------------------------------------------------------
-- 16.  users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id           UUID            REFERENCES tenants (id) ON DELETE RESTRICT,
                        -- NULL for super_admins (platform level)
    user_type           user_type       NOT NULL DEFAULT 'custom',
    -- Identity
    name_bn             VARCHAR(200),
    name_en             VARCHAR(200)    NOT NULL,
    email               VARCHAR(254)    UNIQUE,
    phone               VARCHAR(20),
    username            VARCHAR(100),
    -- Auth
    password_hash       TEXT,           -- bcrypt
    email_verified_at   TIMESTAMPTZ,
    phone_verified_at   TIMESTAMPTZ,
    -- Profile
    profile_photo_url   TEXT,
    language            VARCHAR(10)     NOT NULL DEFAULT 'bn',
    timezone            VARCHAR(60)     NOT NULL DEFAULT 'Asia/Dhaka',
    -- Security
    last_login_at       TIMESTAMPTZ,
    last_login_ip       INET,
    failed_login_count  SMALLINT        NOT NULL DEFAULT 0,
    locked_until        TIMESTAMPTZ,
    force_password_change BOOLEAN       NOT NULL DEFAULT FALSE,
    -- Metadata
    metadata            JSONB           NOT NULL DEFAULT '{}',
    -- Soft delete & audit
    user_status         user_status_type NOT NULL DEFAULT 'pending_verification',
    status              record_status   NOT NULL DEFAULT 'active',
    is_demo             BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at          TIMESTAMPTZ,
    deleted_by          UUID,           -- soft-ref → users.id
    delete_reason       TEXT,
    created_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by          UUID,
    updated_by          UUID,
    -- Constraints
    CONSTRAINT chk_users_email_or_phone CHECK (email IS NOT NULL OR phone IS NOT NULL)
);

CREATE TRIGGER trg_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_users_tenant       ON users (tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_users_email        ON users (email) WHERE email IS NOT NULL AND deleted_at IS NULL;
CREATE INDEX idx_users_phone        ON users (phone) WHERE phone IS NOT NULL AND deleted_at IS NULL;
CREATE INDEX idx_users_type         ON users (tenant_id, user_type) WHERE deleted_at IS NULL;
CREATE INDEX idx_users_demo         ON users (tenant_id) WHERE is_demo = TRUE;
CREATE INDEX idx_users_locked       ON users (locked_until) WHERE locked_until IS NOT NULL;
-- Trigram for fast name search
CREATE INDEX idx_users_name_trgm    ON users USING GIN (name_en gin_trgm_ops);

-- ------------------------------------------------------------
-- 17.  roles
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID            REFERENCES tenants (id) ON DELETE CASCADE,
                    -- NULL = platform-level system role
    name            VARCHAR(100)    NOT NULL,
    slug            VARCHAR(100)    NOT NULL,
    description     TEXT,
    is_system       BOOLEAN         NOT NULL DEFAULT FALSE,  -- cannot be deleted
    parent_role_id  UUID            REFERENCES roles (id) ON DELETE SET NULL,
    display_order   SMALLINT        NOT NULL DEFAULT 0,
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID,
    UNIQUE (tenant_id, slug)
);

CREATE TRIGGER trg_roles_updated_at
    BEFORE UPDATE ON roles
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_roles_tenant  ON roles (tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_roles_system  ON roles (is_system)  WHERE is_system = TRUE;

-- ------------------------------------------------------------
-- 18.  permissions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS permissions (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    module          VARCHAR(100)    NOT NULL,  -- 'students','fees','exams'
    action          VARCHAR(100)    NOT NULL,  -- 'view','create','edit','delete','approve','export'
    scope           VARCHAR(50)     NOT NULL DEFAULT 'all', -- 'all','own','class','section'
    key             VARCHAR(200)    NOT NULL UNIQUE,  -- 'students.view.all'
    label           VARCHAR(200)    NOT NULL,
    description     TEXT,
    group_name      VARCHAR(100),
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE TRIGGER trg_permissions_updated_at
    BEFORE UPDATE ON permissions
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_permissions_module ON permissions (module);
CREATE INDEX idx_permissions_key    ON permissions (key);

-- ------------------------------------------------------------
-- 19.  role_permissions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS role_permissions (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    role_id         UUID            NOT NULL
                        REFERENCES roles (id) ON DELETE CASCADE,
    permission_id   UUID            NOT NULL
                        REFERENCES permissions (id) ON DELETE CASCADE,
    granted         BOOLEAN         NOT NULL DEFAULT TRUE,   -- FALSE = explicit deny
    -- audit
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    UNIQUE (role_id, permission_id)
);

CREATE INDEX idx_role_permissions_role ON role_permissions (role_id);

-- ------------------------------------------------------------
-- 20.  user_roles
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_roles (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         UUID            NOT NULL
                        REFERENCES users (id) ON DELETE CASCADE,
    role_id         UUID            NOT NULL
                        REFERENCES roles (id) ON DELETE CASCADE,
    scope_type      VARCHAR(50),    -- NULL,'class','section','department'
    scope_id        UUID,           -- soft-ref → classes.id / sections.id
    expires_at      TIMESTAMPTZ,
    assigned_at     TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    assigned_by     UUID,           -- soft-ref → users.id
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, role_id, scope_type, scope_id)
);

CREATE TRIGGER trg_user_roles_updated_at
    BEFORE UPDATE ON user_roles
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_user_roles_user ON user_roles (user_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_user_roles_role ON user_roles (role_id) WHERE deleted_at IS NULL;

-- ------------------------------------------------------------
-- 21.  user_permissions
--      Direct per-user permission grants or denials
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_permissions (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         UUID            NOT NULL
                        REFERENCES users (id) ON DELETE CASCADE,
    permission_id   UUID            NOT NULL
                        REFERENCES permissions (id) ON DELETE CASCADE,
    granted         BOOLEAN         NOT NULL DEFAULT TRUE,
    scope_type      VARCHAR(50),
    scope_id        UUID,
    reason          TEXT,
    expires_at      TIMESTAMPTZ,
    -- audit
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    UNIQUE (user_id, permission_id, scope_type, scope_id)
);

CREATE INDEX idx_user_permissions_user ON user_permissions (user_id);

-- ------------------------------------------------------------
-- 22.  sessions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         UUID            NOT NULL
                        REFERENCES users (id) ON DELETE CASCADE,
    token_hash      TEXT            NOT NULL UNIQUE,  -- hashed session token
    device_name     VARCHAR(200),
    device_type     VARCHAR(50),    -- 'web','android','ios'
    browser         VARCHAR(100),
    ip_address      INET,
    user_agent      TEXT,
    location_city   VARCHAR(100),
    location_country VARCHAR(5),
    last_active_at  TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    expires_at      TIMESTAMPTZ     NOT NULL,
    revoked_at      TIMESTAMPTZ,
    revoke_reason   VARCHAR(100),
    is_remembered   BOOLEAN         NOT NULL DEFAULT FALSE,
    -- audit
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_sessions_user    ON sessions (user_id) WHERE revoked_at IS NULL;
CREATE INDEX idx_sessions_token   ON sessions (token_hash);
CREATE INDEX idx_sessions_expires ON sessions (expires_at) WHERE revoked_at IS NULL;

-- ------------------------------------------------------------
-- 23.  two_factor_auth
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS two_factor_auth (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         UUID            NOT NULL UNIQUE
                        REFERENCES users (id) ON DELETE CASCADE,
    method          VARCHAR(20)     NOT NULL DEFAULT 'totp', -- 'totp','sms','email'
    secret          TEXT,           -- encrypted TOTP secret
    backup_codes    JSONB           NOT NULL DEFAULT '[]',   -- hashed backup codes
    verified_at     TIMESTAMPTZ,
    last_used_at    TIMESTAMPTZ,
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID
);

CREATE TRIGGER trg_two_factor_auth_updated_at
    BEFORE UPDATE ON two_factor_auth
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

-- ------------------------------------------------------------
-- 24.  trusted_devices
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS trusted_devices (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         UUID            NOT NULL
                        REFERENCES users (id) ON DELETE CASCADE,
    device_hash     TEXT            NOT NULL,   -- fingerprint hash
    device_name     VARCHAR(200),
    device_type     VARCHAR(50),
    browser         VARCHAR(100),
    ip_address      INET,
    trusted_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    expires_at      TIMESTAMPTZ     NOT NULL,
    last_used_at    TIMESTAMPTZ,
    revoked_at      TIMESTAMPTZ,
    -- audit
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, device_hash)
);

CREATE INDEX idx_trusted_devices_user    ON trusted_devices (user_id) WHERE revoked_at IS NULL;
CREATE INDEX idx_trusted_devices_expires ON trusted_devices (expires_at) WHERE revoked_at IS NULL;

-- ------------------------------------------------------------
-- 25.  password_resets
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         UUID            NOT NULL
                        REFERENCES users (id) ON DELETE CASCADE,
    token_hash      TEXT            NOT NULL UNIQUE,
    method          VARCHAR(20)     NOT NULL DEFAULT 'email', -- 'email','sms'
    expires_at      TIMESTAMPTZ     NOT NULL,
    used_at         TIMESTAMPTZ,
    ip_address      INET,
    -- audit
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_password_resets_user    ON password_resets (user_id);
CREATE INDEX idx_password_resets_expires ON password_resets (expires_at) WHERE used_at IS NULL;

-- ------------------------------------------------------------
-- 26.  login_attempts
--      Brute-force protection log
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    identifier      VARCHAR(254)    NOT NULL,   -- email or phone attempted
    tenant_id       UUID            REFERENCES tenants (id) ON DELETE CASCADE,
    ip_address      INET            NOT NULL,
    user_agent      TEXT,
    success         BOOLEAN         NOT NULL DEFAULT FALSE,
    failure_reason  VARCHAR(100),   -- 'wrong_password','account_locked','2fa_failed'
    attempted_at    TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    -- audit
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

-- Partial index — only failed attempts for lockout checks
CREATE INDEX idx_login_attempts_ip_fail ON login_attempts (ip_address, attempted_at)
    WHERE success = FALSE;
CREATE INDEX idx_login_attempts_id_fail ON login_attempts (identifier, attempted_at)
    WHERE success = FALSE;
-- Auto-cleanup: attempts older than 24h rarely queried
CREATE INDEX idx_login_attempts_time    ON login_attempts (attempted_at);

-- ============================================================
-- GROUP 4 : School / Tenant Settings
-- ============================================================

-- ------------------------------------------------------------
-- 27.  tenant_settings
--      Flexible key-value settings per tenant
--      (Replaces dozens of individual columns)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tenant_settings (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID            NOT NULL
                        REFERENCES tenants (id) ON DELETE CASCADE,
    group_key       VARCHAR(100)    NOT NULL,  -- 'academic','fee','attendance','notification'
    settings        JSONB           NOT NULL DEFAULT '{}',
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID,
    UNIQUE (tenant_id, group_key)
);

CREATE TRIGGER trg_tenant_settings_updated_at
    BEFORE UPDATE ON tenant_settings
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_tenant_settings_tenant ON tenant_settings (tenant_id) WHERE deleted_at IS NULL;
-- GIN index for querying inside JSONB
CREATE INDEX idx_tenant_settings_json   ON tenant_settings USING GIN (settings);

-- ------------------------------------------------------------
-- 28.  tenant_languages
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tenant_languages (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID            NOT NULL
                        REFERENCES tenants (id) ON DELETE CASCADE,
    language_code   VARCHAR(10)     NOT NULL,  -- 'bn','en','ar'
    language_name   VARCHAR(100)    NOT NULL,
    is_default      BOOLEAN         NOT NULL DEFAULT FALSE,
    is_rtl          BOOLEAN         NOT NULL DEFAULT FALSE,
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID,
    UNIQUE (tenant_id, language_code)
);

CREATE TRIGGER trg_tenant_languages_updated_at
    BEFORE UPDATE ON tenant_languages
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

-- ------------------------------------------------------------
-- 29.  tenant_modules
--      Which modules are enabled per tenant
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tenant_modules (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID            NOT NULL
                        REFERENCES tenants (id) ON DELETE CASCADE,
    module_key      VARCHAR(100)    NOT NULL,  -- 'transport','hostel','canteen','library'
    is_enabled      BOOLEAN         NOT NULL DEFAULT TRUE,
    config          JSONB           NOT NULL DEFAULT '{}',
    enabled_at      TIMESTAMPTZ,
    disabled_at     TIMESTAMPTZ,
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID,
    UNIQUE (tenant_id, module_key)
);

CREATE TRIGGER trg_tenant_modules_updated_at
    BEFORE UPDATE ON tenant_modules
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_tenant_modules_tenant ON tenant_modules (tenant_id) WHERE deleted_at IS NULL;

-- ------------------------------------------------------------
-- 30.  tenant_document_templates
--      Per-tenant document templates (marksheet, receipt, ID card…)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tenant_document_templates (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID            NOT NULL
                        REFERENCES tenants (id) ON DELETE CASCADE,
    template_type   VARCHAR(100)    NOT NULL,  -- 'marksheet','fee_receipt','id_card','certificate'
    name            VARCHAR(200)    NOT NULL,
    is_default      BOOLEAN         NOT NULL DEFAULT FALSE,
    language        VARCHAR(10)     NOT NULL DEFAULT 'bn',
    layout          JSONB           NOT NULL DEFAULT '{}',   -- design config
    content         JSONB           NOT NULL DEFAULT '{}',   -- field mapping
    preview_url     TEXT,
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID
);

CREATE TRIGGER trg_tenant_doc_templates_updated_at
    BEFORE UPDATE ON tenant_document_templates
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_tenant_doc_templates_tenant ON tenant_document_templates (tenant_id, template_type)
    WHERE deleted_at IS NULL;

-- ------------------------------------------------------------
-- 31.  tenant_brandings
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tenant_brandings (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID            NOT NULL UNIQUE
                        REFERENCES tenants (id) ON DELETE CASCADE,
    primary_color   CHAR(7)         NOT NULL DEFAULT '#1a73e8',
    secondary_color CHAR(7),
    accent_color    CHAR(7),
    logo_primary_url   TEXT,
    logo_landscape_url TEXT,
    logo_dark_url      TEXT,
    favicon_url        TEXT,
    font_family        VARCHAR(100),
    custom_css         TEXT,
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID
);

CREATE TRIGGER trg_tenant_brandings_updated_at
    BEFORE UPDATE ON tenant_brandings
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

-- ------------------------------------------------------------
-- 32.  tenant_feature_flags
--      Per-tenant override of platform feature flags
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tenant_feature_flags (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID            NOT NULL
                        REFERENCES tenants (id) ON DELETE CASCADE,
    flag_key        VARCHAR(200)    NOT NULL,
    is_enabled      BOOLEAN         NOT NULL DEFAULT TRUE,
    reason          TEXT,
    expires_at      TIMESTAMPTZ,
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID,
    UNIQUE (tenant_id, flag_key)
);

CREATE TRIGGER trg_tenant_feature_flags_updated_at
    BEFORE UPDATE ON tenant_feature_flags
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_tenant_ff_tenant ON tenant_feature_flags (tenant_id) WHERE deleted_at IS NULL;

-- ============================================================
-- GROUP 5 : Platform Infrastructure
-- ============================================================

-- ------------------------------------------------------------
-- 33.  platform_feature_flags
--      Global feature flags managed by super admin
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS platform_feature_flags (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    flag_key        VARCHAR(200)    NOT NULL UNIQUE,
    description     TEXT,
    is_enabled      BOOLEAN         NOT NULL DEFAULT FALSE,
    rollout_percentage SMALLINT     NOT NULL DEFAULT 0   -- 0-100
                        CHECK (rollout_percentage BETWEEN 0 AND 100),
    applicable_plans JSONB          NOT NULL DEFAULT '[]',  -- [] = all plans
    metadata        JSONB           NOT NULL DEFAULT '{}',
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID
);

CREATE TRIGGER trg_platform_ff_updated_at
    BEFORE UPDATE ON platform_feature_flags
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

-- ------------------------------------------------------------
-- 34.  ip_whitelist
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ip_whitelist (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID            REFERENCES tenants (id) ON DELETE CASCADE,
                    -- NULL = platform-level whitelist
    ip_range        CIDR            NOT NULL,
    label           VARCHAR(200),
    applies_to      VARCHAR(50)     NOT NULL DEFAULT 'all', -- 'all','admin','api'
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID
);

CREATE TRIGGER trg_ip_whitelist_updated_at
    BEFORE UPDATE ON ip_whitelist
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_ip_whitelist_tenant ON ip_whitelist (tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_ip_whitelist_range  ON ip_whitelist USING GIST (ip_range) WHERE deleted_at IS NULL;

-- ------------------------------------------------------------
-- 35.  audit_logs
--      Tamper-proof, append-only activity log
--      No UPDATE / DELETE allowed on this table (enforced by trigger)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID,           -- soft-ref → tenants.id
    user_id         UUID,           -- soft-ref → users.id
    user_type       VARCHAR(50),
    session_id      UUID,           -- soft-ref → sessions.id
    -- Action
    action          VARCHAR(100)    NOT NULL,  -- 'create','update','delete','login','export'
    module          VARCHAR(100)    NOT NULL,
    entity_type     VARCHAR(100),              -- 'student','teacher','invoice'
    entity_id       UUID,
    -- Change data
    old_values      JSONB,
    new_values      JSONB,
    changed_fields  TEXT[],
    -- Request context
    ip_address      INET,
    user_agent      TEXT,
    request_id      UUID,
    -- Result
    success         BOOLEAN         NOT NULL DEFAULT TRUE,
    error_message   TEXT,
    -- Hash chain (tamper detection)
    previous_hash   TEXT,           -- hash of previous log entry
    entry_hash      TEXT,           -- hash of this entry
    -- Metadata
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
    -- NO updated_at — audit logs are immutable
);

-- Prevent any modification of audit logs
CREATE OR REPLACE FUNCTION fn_protect_audit_logs()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'audit_logs are immutable — UPDATE and DELETE are not allowed';
END;
$$;

CREATE TRIGGER trg_protect_audit_logs
    BEFORE UPDATE OR DELETE ON audit_logs
    FOR EACH ROW EXECUTE FUNCTION fn_protect_audit_logs();

CREATE INDEX idx_audit_logs_tenant  ON audit_logs (tenant_id, created_at DESC);
CREATE INDEX idx_audit_logs_user    ON audit_logs (user_id, created_at DESC);
CREATE INDEX idx_audit_logs_entity  ON audit_logs (entity_type, entity_id);
CREATE INDEX idx_audit_logs_module  ON audit_logs (module, action);
CREATE INDEX idx_audit_logs_time    ON audit_logs (created_at DESC);
-- GIN for searching inside JSONB change data
CREATE INDEX idx_audit_logs_changes ON audit_logs USING GIN (new_values) WHERE new_values IS NOT NULL;

-- ------------------------------------------------------------
-- 36.  notification_templates
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notification_templates (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID            REFERENCES tenants (id) ON DELETE CASCADE,
                    -- NULL = platform default template
    event_key       VARCHAR(200)    NOT NULL,  -- 'student.absent','fee.due','result.published'
    channel         notif_channel   NOT NULL,
    language        VARCHAR(10)     NOT NULL DEFAULT 'bn',
    subject         VARCHAR(300),  -- email subject
    body_template   TEXT            NOT NULL,  -- mustache/handlebars template
    variables       JSONB           NOT NULL DEFAULT '[]',  -- available variables
    is_active       BOOLEAN         NOT NULL DEFAULT TRUE,
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID,
    UNIQUE (tenant_id, event_key, channel, language)
);

CREATE TRIGGER trg_notif_templates_updated_at
    BEFORE UPDATE ON notification_templates
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_notif_templates_tenant ON notification_templates (tenant_id, event_key)
    WHERE deleted_at IS NULL;

-- ------------------------------------------------------------
-- 37.  system_notifications
--      Outbound notification queue and delivery log
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS system_notifications (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID,           -- soft-ref → tenants.id
    recipient_id    UUID,           -- soft-ref → users.id
    recipient_type  VARCHAR(50),    -- 'student','parent','teacher','staff'
    recipient_phone VARCHAR(20),
    recipient_email VARCHAR(254),
    channel         notif_channel   NOT NULL,
    event_key       VARCHAR(200)    NOT NULL,
    subject         VARCHAR(300),
    body            TEXT            NOT NULL,
    notif_status    notif_status    NOT NULL DEFAULT 'pending',
    scheduled_at    TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    sent_at         TIMESTAMPTZ,
    delivered_at    TIMESTAMPTZ,
    read_at         TIMESTAMPTZ,
    error_message   TEXT,
    retry_count     SMALLINT        NOT NULL DEFAULT 0,
    gateway_ref     VARCHAR(200),   -- SMS/email gateway ID
    metadata        JSONB           NOT NULL DEFAULT '{}',
    -- audit
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW()
);

CREATE TRIGGER trg_system_notifications_updated_at
    BEFORE UPDATE ON system_notifications
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_sys_notif_tenant    ON system_notifications (tenant_id, created_at DESC);
CREATE INDEX idx_sys_notif_recipient ON system_notifications (recipient_id) WHERE recipient_id IS NOT NULL;
CREATE INDEX idx_sys_notif_pending   ON system_notifications (scheduled_at) WHERE notif_status = 'pending';
CREATE INDEX idx_sys_notif_channel   ON system_notifications (channel, notif_status);

-- ------------------------------------------------------------
-- 38.  media_files
--      Centralised file / media registry
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS media_files (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID            REFERENCES tenants (id) ON DELETE CASCADE,
    uploader_id     UUID,           -- soft-ref → users.id
    -- File info
    original_name   VARCHAR(500)    NOT NULL,
    stored_name     VARCHAR(500)    NOT NULL,
    mime_type       VARCHAR(100)    NOT NULL,
    file_type       media_file_type NOT NULL DEFAULT 'other',
    file_size_bytes BIGINT          NOT NULL DEFAULT 0,
    storage_path    TEXT            NOT NULL,
    public_url      TEXT,
    cdn_url         TEXT,
    -- Association (polymorphic soft-reference)
    entity_type     VARCHAR(100),   -- 'student','invoice','certificate'
    entity_id       UUID,
    usage_context   VARCHAR(100),   -- 'profile_photo','invoice_attachment','study_material'
    -- Media metadata
    width_px        INTEGER,
    height_px       INTEGER,
    duration_sec    NUMERIC(10,2),
    thumbnail_url   TEXT,
    checksum        VARCHAR(64),    -- SHA-256 for integrity
    is_public       BOOLEAN         NOT NULL DEFAULT FALSE,
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID
);

CREATE TRIGGER trg_media_files_updated_at
    BEFORE UPDATE ON media_files
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_media_tenant   ON media_files (tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_media_entity   ON media_files (entity_type, entity_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_media_demo     ON media_files (tenant_id, created_at) WHERE is_demo = TRUE;
CREATE INDEX idx_media_checksum ON media_files (checksum) WHERE checksum IS NOT NULL;

-- ------------------------------------------------------------
-- 39.  support_tickets
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS support_tickets (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_number   VARCHAR(30)     NOT NULL UNIQUE,   -- TKT-2025-00001
    tenant_id       UUID            REFERENCES tenants (id) ON DELETE SET NULL,
    submitter_id    UUID,           -- soft-ref → users.id
    submitter_name  VARCHAR(200),
    submitter_email VARCHAR(254),
    -- Ticket info
    category        VARCHAR(100)    NOT NULL,  -- 'technical','billing','feature_request','training'
    subject         VARCHAR(300)    NOT NULL,
    description     TEXT            NOT NULL,
    priority        ticket_priority NOT NULL DEFAULT 'medium',
    tk_status       ticket_status_type NOT NULL DEFAULT 'open',
    -- Assignment
    assigned_to     UUID,           -- soft-ref → users.id (support agent)
    assigned_at     TIMESTAMPTZ,
    -- SLA
    first_response_due  TIMESTAMPTZ,
    resolution_due      TIMESTAMPTZ,
    first_responded_at  TIMESTAMPTZ,
    resolved_at         TIMESTAMPTZ,
    closed_at           TIMESTAMPTZ,
    -- Satisfaction
    satisfaction_score  SMALLINT
                        CHECK (satisfaction_score BETWEEN 1 AND 5),
    satisfaction_note   TEXT,
    -- Metadata
    attachments     JSONB           NOT NULL DEFAULT '[]',
    tags            TEXT[],
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID
);

CREATE TRIGGER trg_support_tickets_updated_at
    BEFORE UPDATE ON support_tickets
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_tickets_tenant   ON support_tickets (tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_tickets_status   ON support_tickets (tk_status, priority) WHERE deleted_at IS NULL;
CREATE INDEX idx_tickets_assigned ON support_tickets (assigned_to) WHERE assigned_to IS NOT NULL;
CREATE INDEX idx_tickets_sla      ON support_tickets (resolution_due) WHERE tk_status NOT IN ('resolved','closed');

-- ------------------------------------------------------------
-- 40.  support_ticket_responses
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS support_ticket_responses (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_id       UUID            NOT NULL
                        REFERENCES support_tickets (id) ON DELETE CASCADE,
    responder_id    UUID,           -- soft-ref → users.id
    responder_name  VARCHAR(200),
    is_internal     BOOLEAN         NOT NULL DEFAULT FALSE,  -- internal note vs customer reply
    body            TEXT            NOT NULL,
    attachments     JSONB           NOT NULL DEFAULT '[]',
    -- audit
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID
);

CREATE TRIGGER trg_ticket_responses_updated_at
    BEFORE UPDATE ON support_ticket_responses
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_ticket_responses_ticket ON support_ticket_responses (ticket_id);

-- ------------------------------------------------------------
-- 41.  system_announcements
--      Platform-wide or targeted announcements from super admin
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS system_announcements (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title           VARCHAR(300)    NOT NULL,
    body            TEXT            NOT NULL,
    ann_type        announcement_type NOT NULL DEFAULT 'info',
    priority        SMALLINT        NOT NULL DEFAULT 0,
    target_plans    JSONB           NOT NULL DEFAULT '[]',   -- [] = all plans
    target_tenants  JSONB           NOT NULL DEFAULT '[]',   -- [] = all tenants
    is_pinned       BOOLEAN         NOT NULL DEFAULT FALSE,
    is_dismissible  BOOLEAN         NOT NULL DEFAULT TRUE,
    publish_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    expires_at      TIMESTAMPTZ,
    -- Delivery channels
    show_in_app     BOOLEAN         NOT NULL DEFAULT TRUE,
    send_email      BOOLEAN         NOT NULL DEFAULT FALSE,
    send_sms        BOOLEAN         NOT NULL DEFAULT FALSE,
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID
);

CREATE TRIGGER trg_announcements_updated_at
    BEFORE UPDATE ON system_announcements
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_announcements_active ON system_announcements (publish_at, expires_at)
    WHERE deleted_at IS NULL AND status = 'active';

-- ------------------------------------------------------------
-- 42.  announcement_dismissals
--      Track which tenants dismissed which announcements
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS announcement_dismissals (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    announcement_id     UUID            NOT NULL
                            REFERENCES system_announcements (id) ON DELETE CASCADE,
    tenant_id           UUID            NOT NULL
                            REFERENCES tenants (id) ON DELETE CASCADE,
    dismissed_by        UUID,           -- soft-ref → users.id
    dismissed_at        TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    is_demo             BOOLEAN         NOT NULL DEFAULT FALSE,
    UNIQUE (announcement_id, tenant_id)
);

CREATE INDEX idx_ann_dismissals_tenant ON announcement_dismissals (tenant_id);

-- ------------------------------------------------------------
-- 43.  api_keys
--      External / third-party API access management
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS api_keys (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       UUID            REFERENCES tenants (id) ON DELETE CASCADE,
    name            VARCHAR(200)    NOT NULL,
    key_hash        TEXT            NOT NULL UNIQUE,  -- hashed key (bcrypt)
    key_prefix      VARCHAR(10)     NOT NULL,         -- first 8 chars for display: 'sk_abc12...'
    key_type        VARCHAR(30)     NOT NULL DEFAULT 'read_write', -- 'read_only','read_write','admin','webhook'
    allowed_modules JSONB           NOT NULL DEFAULT '[]',  -- [] = all
    allowed_ips     JSONB           NOT NULL DEFAULT '[]',  -- [] = all
    rate_limit_per_min  INTEGER     NOT NULL DEFAULT 60,
    last_used_at    TIMESTAMPTZ,
    usage_count     BIGINT          NOT NULL DEFAULT 0,
    expires_at      TIMESTAMPTZ,
    revoked_at      TIMESTAMPTZ,
    revoke_reason   TEXT,
    -- audit
    status          record_status   NOT NULL DEFAULT 'active',
    is_demo         BOOLEAN         NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMPTZ,
    deleted_by      UUID,
    delete_reason   TEXT,
    created_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    created_by      UUID,
    updated_by      UUID
);

CREATE TRIGGER trg_api_keys_updated_at
    BEFORE UPDATE ON api_keys
    FOR EACH ROW EXECUTE FUNCTION fn_set_updated_at();

CREATE INDEX idx_api_keys_tenant   ON api_keys (tenant_id) WHERE deleted_at IS NULL AND revoked_at IS NULL;
CREATE INDEX idx_api_keys_prefix   ON api_keys (key_prefix);

-- ============================================================
-- VIEWS  (Developers query views, never raw tables)
-- ============================================================

-- Active tenants (excluding demo & deleted)
CREATE OR REPLACE VIEW v_active_tenants AS
    SELECT * FROM tenants
    WHERE deleted_at IS NULL
      AND status     = 'active'
      AND is_demo    = FALSE;

-- Trial tenants (for sales follow-up)
CREATE OR REPLACE VIEW v_trial_tenants AS
    SELECT * FROM tenants
    WHERE deleted_at    IS NULL
      AND tenant_status = 'trial'
      AND is_demo       = FALSE;

-- Demo tenants (isolated)
CREATE OR REPLACE VIEW v_demo_tenants AS
    SELECT * FROM tenants
    WHERE is_demo = TRUE;

-- Active users per tenant
CREATE OR REPLACE VIEW v_active_users AS
    SELECT * FROM users
    WHERE deleted_at    IS NULL
      AND user_status   = 'active'
      AND is_demo       = FALSE;

-- Active subscriptions
CREATE OR REPLACE VIEW v_active_subscriptions AS
    SELECT s.*, t.name_en AS tenant_name, p.name AS plan_name
    FROM   subscriptions s
    JOIN   tenants t ON t.id = s.tenant_id
    JOIN   plans   p ON p.id = s.plan_id
    WHERE  s.deleted_at    IS NULL
      AND  s.sub_status    IN ('active','trial')
      AND  s.is_demo       = FALSE;

-- Overdue invoices
CREATE OR REPLACE VIEW v_overdue_invoices AS
    SELECT i.*, t.name_en AS tenant_name
    FROM   invoices i
    JOIN   tenants  t ON t.id = i.tenant_id
    WHERE  i.deleted_at  IS NULL
      AND  i.inv_status  IN ('sent','partial','overdue')
      AND  i.due_date    <  CURRENT_DATE
      AND  i.is_demo     = FALSE;

-- Open support tickets with SLA status
CREATE OR REPLACE VIEW v_open_tickets AS
    SELECT st.*,
           CASE
               WHEN st.resolution_due < NOW() THEN 'breached'
               WHEN st.resolution_due < NOW() + INTERVAL '4 hours' THEN 'at_risk'
               ELSE 'on_track'
           END AS sla_status
    FROM   support_tickets st
    WHERE  st.deleted_at IS NULL
      AND  st.tk_status NOT IN ('resolved','closed','cancelled')
      AND  st.is_demo    = FALSE;

-- Pending notifications queue
CREATE OR REPLACE VIEW v_pending_notifications AS
    SELECT * FROM system_notifications
    WHERE  notif_status = 'pending'
      AND  scheduled_at <= NOW()
      AND  retry_count  <  5
    ORDER BY scheduled_at ASC;

-- Pending audit (active sessions)
CREATE OR REPLACE VIEW v_active_sessions AS
    SELECT s.*, u.name_en AS user_name, u.user_type
    FROM   sessions s
    JOIN   users    u ON u.id = s.user_id
    WHERE  s.revoked_at IS NULL
      AND  s.expires_at > NOW();

-- MRR summary per plan
CREATE OR REPLACE VIEW v_mrr_by_plan AS
    SELECT
        p.name               AS plan_name,
        p.slug               AS plan_slug,
        COUNT(s.id)          AS active_tenants,
        SUM(s.final_price)   AS mrr
    FROM  subscriptions s
    JOIN  plans p ON p.id = s.plan_id
    WHERE s.sub_status = 'active'
      AND s.billing_cycle = 'monthly'
      AND s.deleted_at IS NULL
      AND s.is_demo = FALSE
    GROUP BY p.id, p.name, p.slug;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default Plans
INSERT INTO plans (id, name, slug, description,
    price_monthly, price_yearly, price_quarterly,
    max_students, max_teachers, max_staff, max_branches,
    storage_gb, sms_per_month, api_calls_per_day, trial_days,
    features, display_order, status)
VALUES
    (gen_random_uuid(), 'Trial',    'trial',    'Free trial plan',
     0, 0, 0, 50, 10, 20, 1, 1, 200, 500, 14,
     '{"mobile_app":true,"biometric":false,"hostel":false,"transport":false}', 0, 'active'),

    (gen_random_uuid(), 'Basic',    'basic',    'Perfect for small schools',
     2500, 25000, 6500, 500, 50, 100, 1, 5, 1000, 1000, 14,
     '{"mobile_app":true,"biometric":false,"hostel":false,"transport":true}', 1, 'active'),

    (gen_random_uuid(), 'Standard', 'standard', 'For growing institutions',
     5000, 50000, 13000, 2000, 200, 400, 2, 20, 5000, 5000, 14,
     '{"mobile_app":true,"biometric":true,"hostel":true,"transport":true,"custom_domain":true}', 2, 'active'),

    (gen_random_uuid(), 'Premium',  'premium',  'Full-featured, unlimited scale',
     10000, 100000, 26000, 2147483647, 2147483647, 2147483647, 2147483647, 100, 20000, 50000, 14,
     '{"mobile_app":true,"biometric":true,"hostel":true,"transport":true,"custom_domain":true,"whatsapp":true,"ai":true}', 3, 'active'),

    (gen_random_uuid(), 'Enterprise','enterprise','Negotiated custom plan',
     0, 0, 0, 2147483647, 2147483647, 2147483647, 2147483647, 500, 100000, 500000, 30,
     '{"mobile_app":true,"biometric":true,"hostel":true,"transport":true,"custom_domain":true,"whatsapp":true,"ai":true,"dedicated_support":true}', 4, 'active'),

    (gen_random_uuid(), 'Demo',     'demo',     'Demo tenant plan (internal)',
     0, 0, 0, 100, 20, 30, 1, 2, 500, 1000, 9999,
     '{"mobile_app":true,"biometric":true,"hostel":true,"transport":true}', 99, 'active')
ON CONFLICT (slug) DO NOTHING;

-- Addon Catalog
INSERT INTO addon_catalog (name, slug, description, price_monthly, price_yearly, unit, status)
VALUES
    ('Extra SMS Pack',        'sms_1000',       '1,000 additional SMS per month',  200, 2000,  'per_1000_sms', 'active'),
    ('Extra Storage 10GB',    'storage_10gb',   '10 GB additional cloud storage',  300, 3000,  'per_gb',       'active'),
    ('WhatsApp Integration',  'whatsapp',       'WhatsApp Business API access',    1500, 15000, 'fixed',        'active'),
    ('Biometric Module',      'biometric',      'Fingerprint attendance device sync', 800, 8000, 'fixed',      'active'),
    ('AI Features Pack',      'ai_features',    'Predictive analytics & insights', 2000, 20000, 'fixed',       'active'),
    ('Extra Branch',          'extra_branch',   'Add one more branch/campus',      1000, 10000, 'fixed',       'active'),
    ('Priority Support',      'priority_support','4-hour SLA support',             500, 5000,   'fixed',       'active')
ON CONFLICT (slug) DO NOTHING;

-- System-level Roles (tenant_id = NULL means platform role)
INSERT INTO roles (name, slug, description, is_system, display_order, status)
VALUES
    ('Super Admin',          'super_admin',          'Full platform access',                       TRUE, 1, 'active'),
    ('Platform Support',     'platform_support',      'Support team access',                        TRUE, 2, 'active'),
    ('School Admin',         'school_admin',          'Full school management',                     TRUE, 3, 'active'),
    ('Principal',            'principal',             'Academic + financial overview',               TRUE, 4, 'active'),
    ('Vice Principal',       'vice_principal',        'Academic management',                        TRUE, 5, 'active'),
    ('Accountant',           'accountant',            'Finance and fee management',                 TRUE, 6, 'active'),
    ('Academic Coordinator', 'academic_coordinator',  'Exam, result, syllabus',                     TRUE, 7, 'active'),
    ('Admission Officer',    'admission_officer',     'Student admission workflow',                  TRUE, 8, 'active'),
    ('Class Teacher',        'class_teacher',         'Own section attendance & marks',             TRUE, 9, 'active'),
    ('Subject Teacher',      'subject_teacher',       'Own subject marks & homework',               TRUE,10, 'active'),
    ('Librarian',            'librarian',             'Library module full access',                  TRUE,11, 'active'),
    ('Transport Manager',    'transport_manager',     'Transport module full access',                TRUE,12, 'active'),
    ('Canteen Manager',      'canteen_manager',       'Canteen module full access',                  TRUE,13, 'active'),
    ('HR Manager',           'hr_manager',            'HR and payroll management',                   TRUE,14, 'active'),
    ('Security Staff',       'security_staff',        'Gate and visitor management',                TRUE,15, 'active'),
    ('Medical Officer',      'medical_officer',       'Health module access',                        TRUE,16, 'active'),
    ('Student',              'student',               'Own data, read-only mostly',                 TRUE,17, 'active'),
    ('Parent',               'parent',                'Own child data + payment',                   TRUE,18, 'active')
ON CONFLICT (tenant_id, slug) DO NOTHING;

-- Core Permissions
INSERT INTO permissions (module, action, scope, key, label, group_name, status)
VALUES
    -- Students
    ('students','view',   'all',     'students.view.all',     'View All Students',            'Student Management', 'active'),
    ('students','view',   'own',     'students.view.own',     'View Own Student Record',      'Student Management', 'active'),
    ('students','create', 'all',     'students.create',       'Admit New Student',            'Student Management', 'active'),
    ('students','edit',   'all',     'students.edit.all',     'Edit Any Student Profile',     'Student Management', 'active'),
    ('students','delete', 'all',     'students.delete',       'Soft-delete Student',          'Student Management', 'active'),
    ('students','export', 'all',     'students.export',       'Export Student Data',          'Student Management', 'active'),
    -- Attendance
    ('attendance','view', 'all',     'attendance.view.all',   'View All Attendance',          'Attendance', 'active'),
    ('attendance','view', 'section', 'attendance.view.section','View Section Attendance',     'Attendance', 'active'),
    ('attendance','create','section','attendance.mark',        'Mark Attendance',              'Attendance', 'active'),
    ('attendance','edit', 'all',     'attendance.edit',        'Edit Attendance Record',       'Attendance', 'active'),
    -- Exams & Results
    ('exams','view',   'all',  'exams.view',     'View Exam Schedule',            'Exams', 'active'),
    ('exams','create', 'all',  'exams.create',   'Create Exam',                   'Exams', 'active'),
    ('exams','edit',   'all',  'exams.edit',     'Edit Exam',                     'Exams', 'active'),
    ('results','view', 'all',  'results.view',   'View All Results',              'Results', 'active'),
    ('results','create','all', 'results.create', 'Enter Marks',                   'Results', 'active'),
    ('results','publish','all','results.publish','Publish Results',               'Results', 'active'),
    -- Fee
    ('fees','view',   'all',  'fees.view',     'View Fee Records',               'Fee Management', 'active'),
    ('fees','create', 'all',  'fees.create',   'Generate Bills',                 'Fee Management', 'active'),
    ('fees','collect','all',  'fees.collect',  'Collect Payment',                'Fee Management', 'active'),
    ('fees','waive',  'all',  'fees.waive',    'Approve Fee Waiver',             'Fee Management', 'active'),
    ('fees','export', 'all',  'fees.export',   'Export Fee Reports',             'Fee Management', 'active'),
    -- HR
    ('hr','view',  'all', 'hr.view',  'View HR Data',                           'HR Management', 'active'),
    ('hr','create','all', 'hr.create','Manage Staff',                            'HR Management', 'active'),
    ('hr','payroll','all','hr.payroll','Process Payroll',                         'HR Management', 'active'),
    -- Settings
    ('settings','view',  'all', 'settings.view',  'View School Settings',        'Settings', 'active'),
    ('settings','edit',  'all', 'settings.edit',  'Edit School Settings',        'Settings', 'active'),
    ('settings','roles', 'all', 'settings.roles', 'Manage Roles & Permissions',  'Settings', 'active'),
    -- Reports
    ('reports','view', 'all', 'reports.view', 'View Reports',                    'Reports', 'active'),
    ('reports','export','all','reports.export','Export Reports',                  'Reports', 'active')
ON CONFLICT (key) DO NOTHING;

-- Platform Feature Flags
INSERT INTO platform_feature_flags (flag_key, description, is_enabled, rollout_percentage, status)
VALUES
    ('whatsapp_integration',   'WhatsApp Business API integration',    FALSE, 0,   'active'),
    ('ai_analytics',           'AI-powered predictive analytics',      FALSE, 0,   'active'),
    ('biometric_attendance',   'Fingerprint device sync',              TRUE,  100, 'active'),
    ('multi_branch',           'Multi-campus management',              TRUE,  100, 'active'),
    ('parent_portal',          'Parent portal & app',                  TRUE,  100, 'active'),
    ('student_portal',         'Student portal & app',                 TRUE,  100, 'active'),
    ('online_class',           'Zoom/Meet virtual classroom',          TRUE,  100, 'active'),
    ('custom_domain',          'Custom domain for school',             TRUE,  50,  'active'),
    ('bulk_sms',               'Bulk SMS campaigns',                   TRUE,  100, 'active'),
    ('payment_bkash',          'bKash payment gateway',                TRUE,  100, 'active'),
    ('payment_nagad',          'Nagad payment gateway',                TRUE,  100, 'active'),
    ('payment_card',           'Card payment via SSLCOMMERZ',          TRUE,  100, 'active'),
    ('digital_certificates',   'QR-verified digital certificates',     TRUE,  100, 'active'),
    ('alumni_module',          'Alumni management module',             TRUE,  80,  'active'),
    ('question_bank',          'Exam question bank',                   TRUE,  100, 'active')
ON CONFLICT (flag_key) DO NOTHING;

COMMIT;

-- ============================================================
-- POST-COMMIT: Demo data cleanup job (run via pg_cron or app)
-- ============================================================
-- Schedule this nightly:
--
-- DELETE FROM sessions           WHERE is_demo = TRUE AND created_at < NOW() - INTERVAL '14 days';
-- DELETE FROM login_attempts     WHERE is_demo = TRUE AND created_at < NOW() - INTERVAL '14 days';
-- DELETE FROM system_notifications WHERE is_demo = TRUE AND created_at < NOW() - INTERVAL '14 days';
-- UPDATE tenants SET status = 'deleted', deleted_at = NOW()
--   WHERE is_demo = TRUE AND demo_expires_at < NOW() AND deleted_at IS NULL;
--
-- ============================================================
-- SUMMARY
-- ============================================================
-- Groups:
--   Group 1 — SaaS Core         : plans, plan_features, addon_catalog,
--                                  tenants, tenant_contacts, tenant_addresses          (6 tables)
--   Group 2 — Billing            : coupons, subscriptions, subscription_overrides,
--                                  subscription_addons, coupon_redemptions,
--                                  invoices, invoice_items, payments, dunning_logs     (9 tables)
--   Group 3 — Users/Auth/Perms  : users, roles, permissions, role_permissions,
--                                  user_roles, user_permissions, sessions,
--                                  two_factor_auth, trusted_devices,
--                                  password_resets, login_attempts                    (11 tables)
--   Group 4 — School Settings   : tenant_settings, tenant_languages, tenant_modules,
--                                  tenant_document_templates, tenant_brandings,
--                                  tenant_feature_flags                               (6 tables)
--   Group 5 — Infrastructure    : platform_feature_flags, ip_whitelist, audit_logs,
--                                  notification_templates, system_notifications,
--                                  media_files, support_tickets,
--                                  support_ticket_responses, system_announcements,
--                                  announcement_dismissals, api_keys                  (11 tables)
-- ─────────────────────────────────────────────────────────────────────────────
-- Total Tables : 43
-- ENUMs        : 16
-- Views        : 10
-- Triggers     : 30 (fn_set_updated_at) + 1 (audit immutability)
-- Seed Data    : 6 plans · 7 addons · 18 roles · 29 permissions · 15 feature flags
-- ============================================================
