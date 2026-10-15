-- Schéma V1 : boutique WhatsApp pour la Guinée (rebuild des fonctions de Take App)
-- MySQL 8. En pratique, chaque bloc devient une migration Laravel (database/migrations).
--
-- Conventions
-- - id BIGINT auto-incrémenté (convention Laravel). Ce qui est visible publiquement
--   (lien de facture) passe par un jeton ULID non devinable, jamais par l'id.
-- - Argent : entier en GNF (le franc guinéen n'a pas de centimes) + colonne currency.
-- - Heures : stockées en UTC. La Guinée est en UTC+0 sans heure d'été (Africa/Conakry).
-- - Tout ce qu'un marchand possède porte store_id ; stores porte user_id.
-- - Accès : contrôles dans la couche données (policies Laravel + scope global
--   BelongsToStore), pas de RLS (MySQL n'en a pas).

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NOT NULL,              -- format E.164 sans "+": 224XXXXXXXXX
  email VARCHAR(190) NULL,
  password VARCHAR(255) NOT NULL,
  remember_token VARCHAR(100) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY users_phone_unique (phone),
  UNIQUE KEY users_email_unique (email)
);

CREATE TABLE stores (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(60) NOT NULL,               -- le lien : monsite.com/{slug}
  whatsapp_number VARCHAR(20) NOT NULL,    -- 224XXXXXXXXX, utilisé pour wa.me
  city VARCHAR(60) NULL,                   -- Conakry, Kankan, Labé, Nzérékoré...
  description VARCHAR(500) NULL,
  logo_path VARCHAR(255) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'GNF',
  is_open TINYINT(1) NOT NULL DEFAULT 1,
  welcome_message TEXT NULL,               -- message d'accueil prêt à copier (WhatsApp Business / Messenger)
  next_order_number INT UNSIGNED NOT NULL DEFAULT 1001,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY stores_slug_unique (slug),
  KEY stores_user_id_index (user_id),
  CONSTRAINT stores_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT stores_slug_format CHECK (slug REGEXP '^[a-z0-9][a-z0-9-]{1,58}[a-z0-9]$')
);

CREATE TABLE categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  position INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  KEY categories_store_position_index (store_id, position),
  CONSTRAINT categories_store_fk FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
);

CREATE TABLE products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(160) NOT NULL,              -- lien direct produit : /{store}/p/{slug}
  description TEXT NULL,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  track_stock TINYINT(1) NOT NULL DEFAULT 0,
  position INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY products_store_slug_unique (store_id, slug),
  KEY products_store_visible_index (store_id, is_visible, position),
  KEY products_category_id_index (category_id),
  CONSTRAINT products_store_fk FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
  CONSTRAINT products_category_fk FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Tout produit a au moins une variante ("Standard" s'il n'en a pas).
-- Le prix et le stock vivent ici : une seule règle pour tout le code.
CREATE TABLE variants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL DEFAULT 'Standard',   -- ex. "42 / Noir"
  price BIGINT UNSIGNED NOT NULL,                 -- en GNF
  stock INT NULL,                                 -- NULL si le produit ne suit pas le stock
  sku VARCHAR(60) NULL,
  position INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  KEY variants_product_position_index (product_id, position),
  CONSTRAINT variants_product_fk FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT variants_stock_not_negative CHECK (stock IS NULL OR stock >= 0)
);

CREATE TABLE product_images (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,              -- WebP compressé, max 1080 px
  thumb_path VARCHAR(255) NOT NULL,        -- WebP 400 px pour la grille (3G)
  position INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  KEY product_images_product_position_index (product_id, position),
  CONSTRAINT product_images_product_fk FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NOT NULL,              -- 224XXXXXXXXX
  address VARCHAR(255) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY customers_store_phone_unique (store_id, phone),
  CONSTRAINT customers_store_fk FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
);

CREATE TABLE delivery_methods (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,               -- ex. "Livraison Kaloum", "Retrait en boutique"
  type VARCHAR(10) NOT NULL,
  fee BIGINT UNSIGNED NOT NULL DEFAULT 0,  -- GNF, forfait en V1 (zones = should)
  min_order BIGINT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  position INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  KEY delivery_methods_store_index (store_id, is_active, position),
  CONSTRAINT delivery_methods_store_fk FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
  CONSTRAINT delivery_methods_type CHECK (type IN ('pickup','delivery'))
);

CREATE TABLE payment_methods (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(20) NOT NULL,
  account_number VARCHAR(30) NULL,         -- numéro Orange Money / MTN du marchand
  account_name VARCHAR(120) NULL,          -- nom affiché par l'opérateur, pour rassurer le client
  instructions VARCHAR(500) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  position INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  KEY payment_methods_store_index (store_id, is_active, position),
  CONSTRAINT payment_methods_store_fk FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
  CONSTRAINT payment_methods_type CHECK (type IN ('cash_on_delivery','orange_money','mtn_momo','bank_transfer'))
);

CREATE TABLE orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NULL,
  number INT UNSIGNED NOT NULL,            -- n° lisible par boutique : #1001, #1002...
  public_token CHAR(26) NOT NULL,          -- ULID, lien facture /c/{token}
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  payment_status VARCHAR(12) NOT NULL DEFAULT 'unpaid',
  fulfillment_status VARCHAR(16) NOT NULL DEFAULT 'unfulfilled',
  -- copies au moment de la commande : la commande ne change pas si le marchand modifie ses réglages
  customer_name VARCHAR(120) NOT NULL,
  customer_phone VARCHAR(20) NOT NULL,
  address VARCHAR(255) NULL,
  note VARCHAR(500) NULL,
  delivery_method_id BIGINT UNSIGNED NULL,
  delivery_method_name VARCHAR(80) NOT NULL,
  delivery_fee BIGINT UNSIGNED NOT NULL DEFAULT 0,
  payment_method_id BIGINT UNSIGNED NULL,
  payment_method_type VARCHAR(20) NOT NULL,
  subtotal BIGINT UNSIGNED NOT NULL,
  total BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'GNF',
  -- d'où vient le client : ?src=facebook / tiktok / whatsapp / instagram / direct
  source VARCHAR(20) NOT NULL DEFAULT 'direct',
  campaign VARCHAR(60) NULL,               -- ?c=promo-tabaski : pour distinguer deux pubs
  internal_note VARCHAR(500) NULL,
  whatsapp_sent_at TIMESTAMP NULL,         -- le client a appuyé sur "Envoyer sur WhatsApp"
  cancelled_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY orders_store_number_unique (store_id, number),
  UNIQUE KEY orders_public_token_unique (public_token),
  KEY orders_store_status_created_index (store_id, status, created_at),
  KEY orders_store_payment_index (store_id, payment_status, created_at),
  KEY orders_store_source_index (store_id, source, created_at),
  KEY orders_customer_id_index (customer_id),
  KEY orders_delivery_method_id_index (delivery_method_id),
  KEY orders_payment_method_id_index (payment_method_id),
  CONSTRAINT orders_store_fk FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
  CONSTRAINT orders_customer_fk FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT orders_delivery_method_fk FOREIGN KEY (delivery_method_id) REFERENCES delivery_methods(id) ON DELETE SET NULL,
  CONSTRAINT orders_payment_method_fk FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL,
  CONSTRAINT orders_status CHECK (status IN ('pending','confirmed','completed','cancelled')),
  CONSTRAINT orders_payment_status CHECK (payment_status IN ('unpaid','confirming','partially_paid','paid','refunded')),
  CONSTRAINT orders_fulfillment_status CHECK (fulfillment_status IN ('unfulfilled','ready','out_for_delivery','fulfilled')),
  CONSTRAINT orders_total CHECK (total = subtotal + delivery_fee)
);

CREATE TABLE order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  variant_id BIGINT UNSIGNED NULL,
  product_name VARCHAR(150) NOT NULL,      -- copie au moment de la commande
  variant_name VARCHAR(80) NOT NULL,
  unit_price BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  line_total BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  KEY order_items_order_id_index (order_id),
  KEY order_items_product_id_index (product_id),
  KEY order_items_variant_id_index (variant_id),
  CONSTRAINT order_items_order_fk FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT order_items_product_fk FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT order_items_variant_fk FOREIGN KEY (variant_id) REFERENCES variants(id) ON DELETE SET NULL,
  CONSTRAINT order_items_quantity CHECK (quantity > 0),
  CONSTRAINT order_items_line_total CHECK (line_total = unit_price * quantity)
);

CREATE TABLE payment_proofs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,              -- capture du paiement, compressée
  reference VARCHAR(60) NULL,              -- n° de transaction saisi par le client (optionnel)
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  KEY payment_proofs_order_id_index (order_id),
  CONSTRAINT payment_proofs_order_fk FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Journal des mouvements de stock : on sait toujours pourquoi un stock a bougé.
CREATE TABLE stock_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  delta INT NOT NULL,                      -- négatif : vente ; positif : annulation ou réassort
  reason VARCHAR(12) NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  KEY stock_movements_variant_index (variant_id, created_at),
  KEY stock_movements_store_index (store_id, created_at),
  KEY stock_movements_order_id_index (order_id),
  CONSTRAINT stock_movements_store_fk FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
  CONSTRAINT stock_movements_variant_fk FOREIGN KEY (variant_id) REFERENCES variants(id) ON DELETE CASCADE,
  CONSTRAINT stock_movements_order_fk FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
  CONSTRAINT stock_movements_reason CHECK (reason IN ('sale','cancel','restock','adjust'))
);

-- Visites de la boutique par jour et par source : combien de clics a fait chaque pub.
-- Avec orders.source, on obtient "pub Facebook : 120 visites, 6 commandes".
CREATE TABLE daily_visits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id BIGINT UNSIGNED NOT NULL,
  day DATE NOT NULL,
  source VARCHAR(20) NOT NULL,
  visits INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY daily_visits_store_day_source_unique (store_id, day, source),
  CONSTRAINT daily_visits_store_fk FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
);

-- Tables Laravel standard non détaillées ici : sessions (panier), password_reset_tokens,
-- jobs, failed_jobs, cache.
