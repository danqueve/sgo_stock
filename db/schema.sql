-- ============================================================
-- Imperio Comercial - Sistema de Ventas y Stock
-- Base de Datos Normalizada v1.0
-- Motor: MySQL 8+ / InnoDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS c2881399_sgo
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE c2881399_sgo;

-- ------------------------------------------------------------
-- 1. USUARIOS (Admin / Vendedor)
-- ------------------------------------------------------------
CREATE TABLE usuarios (
    id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    usuario      VARCHAR(60)     NOT NULL UNIQUE,        -- nombre de usuario para login
    nombre       VARCHAR(80)     NOT NULL,
    apellido     VARCHAR(80)     NOT NULL,
    clave        VARCHAR(255)    NOT NULL,               -- bcrypt hash
    rol          ENUM('admin','vendedor') NOT NULL DEFAULT 'vendedor',
    activo       TINYINT(1)      NOT NULL DEFAULT 1,
    ultimo_login DATETIME            NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_usuario (usuario),
    INDEX idx_rol     (rol)
) ENGINE=InnoDB;

-- Seed: clave = "password"
INSERT INTO usuarios (usuario, nombre, apellido, clave, rol) VALUES
('admin',    'Admin',    'Imperio',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('vendedor', 'Vendedor', 'Demo',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendedor');

-- ------------------------------------------------------------
-- 2. CATEGORÍAS DE ARTÍCULOS
-- ------------------------------------------------------------
CREATE TABLE categorias (
    id        SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre    VARCHAR(80)       NOT NULL,
    icono     VARCHAR(40)       NOT NULL DEFAULT 'bi-box',
    activo    TINYINT(1)        NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

INSERT INTO categorias (nombre, icono) VALUES
('Electrodomésticos', 'bi-tv'),
('Muebles',           'bi-house'),
('Tecnología',        'bi-phone'),
('Ropa y Calzado',    'bi-bag'),
('Otros',             'bi-grid');

-- ------------------------------------------------------------
-- 3. ARTÍCULOS / STOCK
-- ------------------------------------------------------------
CREATE TABLE articulos (
    id                MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    categoria_id      SMALLINT UNSIGNED  NOT NULL,
    codigo            VARCHAR(30)            NULL UNIQUE,
    nombre            VARCHAR(150)       NOT NULL,
    descripcion       TEXT                   NULL,
    precio_contado    DECIMAL(12,2)      NOT NULL DEFAULT 0.00,
    precio_financiado DECIMAL(12,2)      NOT NULL DEFAULT 0.00,  -- precio total financiado
    cuotas            TINYINT UNSIGNED   NOT NULL DEFAULT 1,      -- N° de cuotas
    monto_cuota       DECIMAL(12,2)      GENERATED ALWAYS AS
                          (ROUND(precio_financiado / cuotas, 2)) STORED,
    stock_actual      SMALLINT           NOT NULL DEFAULT 0,
    stock_minimo      SMALLINT           NOT NULL DEFAULT 1,      -- alerta stock bajo
    imagen_url        VARCHAR(255)           NULL,
    activo            TINYINT(1)         NOT NULL DEFAULT 1,
    created_at        DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP
                          ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_categoria  (categoria_id),
    INDEX idx_stock      (stock_actual),
    INDEX idx_activo     (activo),
    FULLTEXT idx_busqueda (nombre, descripcion),
    CONSTRAINT fk_art_cat FOREIGN KEY (categoria_id)
        REFERENCES categorias(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. PROVINCIAS (catálogo normalizado)
-- ------------------------------------------------------------
CREATE TABLE provincias (
    id     TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(60)      NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

INSERT INTO provincias (nombre) VALUES
('Catamarca'),('Santiago del Estero'),('Tucumán');

-- ------------------------------------------------------------
-- 5. CLIENTES
-- ------------------------------------------------------------
CREATE TABLE clientes (
    id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nombre       VARCHAR(80)      NOT NULL,
    apellido     VARCHAR(80)      NOT NULL,
    dni          VARCHAR(15)          NULL,
    celular      VARCHAR(20)      NOT NULL,
    direccion    VARCHAR(200)     NOT NULL,
    localidad    VARCHAR(100)     NOT NULL,
    provincia_id TINYINT UNSIGNED NOT NULL,
    observaciones TEXT                NULL,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                     ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_apellido   (apellido),
    INDEX idx_celular    (celular),
    INDEX idx_provincia  (provincia_id),
    CONSTRAINT fk_cli_prov FOREIGN KEY (provincia_id)
        REFERENCES provincias(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. VENTAS (cabecera)
-- ------------------------------------------------------------
CREATE TABLE ventas (
    id            INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    cliente_id    INT UNSIGNED        NOT NULL,
    vendedor_id   INT UNSIGNED        NOT NULL,
    tipo_pago     ENUM('contado','financiado') NOT NULL,
    cuotas             TINYINT UNSIGNED    NOT NULL DEFAULT 1,
    es_mensual         TINYINT(1)          NOT NULL DEFAULT 0,
    primer_vencimiento DATE                    NULL,
    total              DECIMAL(14,2)       NOT NULL DEFAULT 0.00,
    estado        ENUM('pendiente','confirmada','anulada') NOT NULL DEFAULT 'confirmada',
    observaciones TEXT                    NULL,
    created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_cliente   (cliente_id),
    INDEX idx_vendedor  (vendedor_id),
    INDEX idx_estado    (estado),
    INDEX idx_fecha     (created_at),
    CONSTRAINT fk_vta_cli FOREIGN KEY (cliente_id)
        REFERENCES clientes(id),
    CONSTRAINT fk_vta_ven FOREIGN KEY (vendedor_id)
        REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. DETALLE DE VENTA (items)
-- ------------------------------------------------------------
CREATE TABLE venta_detalles (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    venta_id        INT UNSIGNED   NOT NULL,
    articulo_id     MEDIUMINT UNSIGNED NOT NULL,
    cantidad        SMALLINT       NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(12,2)  NOT NULL,  -- precio al momento de venta
    subtotal        DECIMAL(14,2)  NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_venta    (venta_id),
    INDEX idx_articulo (articulo_id),
    CONSTRAINT fk_det_vta FOREIGN KEY (venta_id)
        REFERENCES ventas(id) ON DELETE CASCADE,
    CONSTRAINT fk_det_art FOREIGN KEY (articulo_id)
        REFERENCES articulos(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 8. MOVIMIENTOS DE STOCK (auditoría)
-- ------------------------------------------------------------
CREATE TABLE stock_movimientos (
    id           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    articulo_id  MEDIUMINT UNSIGNED NOT NULL,
    usuario_id   INT UNSIGNED   NOT NULL,
    tipo         ENUM('entrada','salida','ajuste') NOT NULL,
    cantidad     SMALLINT       NOT NULL,
    stock_antes  SMALLINT       NOT NULL,
    stock_despues SMALLINT      NOT NULL,
    referencia   VARCHAR(100)       NULL,  -- ej: "Venta #42"
    created_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_articulo (articulo_id),
    INDEX idx_fecha    (created_at),
    CONSTRAINT fk_mov_art FOREIGN KEY (articulo_id)
        REFERENCES articulos(id),
    CONSTRAINT fk_mov_usr FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TRIGGER: descuento automático de stock al confirmar venta
-- ------------------------------------------------------------
DELIMITER //
CREATE TRIGGER trg_descuento_stock
AFTER INSERT ON venta_detalles
FOR EACH ROW
BEGIN
    DECLARE v_stock_antes SMALLINT;
    DECLARE v_venta_estado VARCHAR(20);

    SELECT estado INTO v_venta_estado FROM ventas WHERE id = NEW.venta_id;

    IF v_venta_estado = 'confirmada' THEN
        SELECT stock_actual INTO v_stock_antes
        FROM articulos WHERE id = NEW.articulo_id;

        UPDATE articulos
           SET stock_actual = stock_actual - NEW.cantidad
         WHERE id = NEW.articulo_id;

        INSERT INTO stock_movimientos
            (articulo_id, usuario_id, tipo, cantidad, stock_antes, stock_despues, referencia)
        SELECT NEW.articulo_id,
               v.vendedor_id,
               'salida',
               NEW.cantidad,
               v_stock_antes,
               v_stock_antes - NEW.cantidad,
               CONCAT('Venta #', NEW.venta_id)
        FROM ventas v WHERE v.id = NEW.venta_id;
    END IF;
END//
DELIMITER ;
