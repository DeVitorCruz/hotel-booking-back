-- ─────────────────────────────────────────────────────────────────────────────
-- migrate_data.sql
-- Copies data from ecommerce_blog → mercatura (platform)
--                                 → mercatura_tenant_demo (tenant)
-- Run: mysql -h 127.0.0.1 -P 3306 -u root -proot < migrate_data.sql
-- ─────────────────────────────────────────────────────────────────────────────

-- ── PLATFORM DATA → mercatura ─────────────────────────────────────────────────

INSERT INTO mercatura.users
    SELECT * FROM ecommerce_blog.users;

INSERT INTO mercatura.user_profiles
    SELECT * FROM ecommerce_blog.user_profiles;

INSERT INTO mercatura.personal_access_tokens
    SELECT * FROM ecommerce_blog.personal_access_tokens;

INSERT INTO mercatura.password_reset_tokens
    SELECT * FROM ecommerce_blog.password_reset_tokens;

INSERT INTO mercatura.roles
    SELECT * FROM ecommerce_blog.roles;

INSERT INTO mercatura.permissions
    SELECT * FROM ecommerce_blog.permissions;

INSERT INTO mercatura.role_has_permissions
    SELECT * FROM ecommerce_blog.role_has_permissions;

INSERT INTO mercatura.model_has_roles
    SELECT * FROM ecommerce_blog.model_has_roles;

INSERT INTO mercatura.model_has_permissions
    SELECT * FROM ecommerce_blog.model_has_permissions;

INSERT INTO mercatura.staff_spotlights
    SELECT * FROM ecommerce_blog.staff_spotlights;

INSERT INTO mercatura.teams
    SELECT * FROM ecommerce_blog.teams;

INSERT INTO mercatura.team_members
    SELECT * FROM ecommerce_blog.team_members;

INSERT INTO mercatura.employments
    SELECT * FROM ecommerce_blog.employments;

-- ── TENANT DATA → mercatura_tenant_demo ──────────────────────────────────────

INSERT INTO mercatura_tenant_demo.sellers
    SELECT * FROM ecommerce_blog.sellers;

INSERT INTO mercatura_tenant_demo.categories
    SELECT * FROM ecommerce_blog.categories;

INSERT INTO mercatura_tenant_demo.products
    SELECT * FROM ecommerce_blog.products;

INSERT INTO mercatura_tenant_demo.product_variants
    SELECT * FROM ecommerce_blog.product_variants;

INSERT INTO mercatura_tenant_demo.orders
    SELECT * FROM ecommerce_blog.orders;

INSERT INTO mercatura_tenant_demo.order_items
    SELECT * FROM ecommerce_blog.order_items;

INSERT INTO mercatura_tenant_demo.payments
    SELECT * FROM ecommerce_blog.payments;

INSERT INTO mercatura_tenant_demo.refunds
    SELECT * FROM ecommerce_blog.refunds;

INSERT INTO mercatura_tenant_demo.blogs
    SELECT * FROM ecommerce_blog.blogs;

INSERT INTO mercatura_tenant_demo.faqs
    SELECT * FROM ecommerce_blog.faqs;

INSERT INTO mercatura_tenant_demo.contacts
    SELECT * FROM ecommerce_blog.contacts;

INSERT INTO mercatura_tenant_demo.wishlists
    SELECT * FROM ecommerce_blog.wishlists;

INSERT INTO mercatura_tenant_demo.wishlist_items
    SELECT * FROM ecommerce_blog.wishlist_items;

INSERT INTO mercatura_tenant_demo.reviews
    SELECT * FROM ecommerce_blog.reviews;