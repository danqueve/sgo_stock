-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 28-03-2026 a las 17:44:15
-- Versión del servidor: 8.4.7
-- Versión de PHP: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `c2881399_sgo`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `articulos`
--

DROP TABLE IF EXISTS `articulos`;
CREATE TABLE IF NOT EXISTS `articulos` (
  `id` mediumint UNSIGNED NOT NULL AUTO_INCREMENT,
  `categoria_id` smallint UNSIGNED NOT NULL,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `precio_contado` decimal(12,2) NOT NULL DEFAULT '0.00',
  `precio_financiado` decimal(12,2) NOT NULL DEFAULT '0.00',
  `cuotas` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `monto_cuota` decimal(12,2) GENERATED ALWAYS AS (round((`precio_financiado` / `cuotas`),2)) STORED,
  `stock_actual` smallint NOT NULL DEFAULT '0',
  `stock_minimo` smallint NOT NULL DEFAULT '1',
  `imagen_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `idx_categoria` (`categoria_id`),
  KEY `idx_stock` (`stock_actual`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `articulos`
--

INSERT INTO `articulos` (`id`, `categoria_id`, `codigo`, `nombre`, `descripcion`, `precio_contado`, `precio_financiado`, `cuotas`, `stock_actual`, `stock_minimo`, `imagen_url`, `activo`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'Smartv 55\" TCL', '', 1500000.00, 1752000.00, 12, 1, 1, NULL, 1, '2026-03-27 18:34:58', '2026-03-27 19:58:17'),
(2, 1, NULL, 'Smartv 43\"Siera', '', 555.00, 1320000.00, 12, 1, 1, NULL, 1, '2026-03-27 19:07:48', '2026-03-27 19:58:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

DROP TABLE IF EXISTS `categorias`;
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` smallint UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icono` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bi-box',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id`, `nombre`, `icono`, `activo`) VALUES
(1, 'Electrodomésticos', 'bi-tv', 1),
(2, 'Muebles', 'bi-house', 1),
(3, 'Tecnología', 'bi-phone', 1),
(4, 'Ropa y Calzado', 'bi-bag', 1),
(5, 'Otros', 'bi-grid', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

DROP TABLE IF EXISTS `clientes`;
CREATE TABLE IF NOT EXISTS `clientes` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dni` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `celular` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `localidad` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provincia_id` tinyint UNSIGNED NOT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_apellido` (`apellido`),
  KEY `idx_celular` (`celular`),
  KEY `idx_provincia` (`provincia_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `nombre`, `apellido`, `dni`, `celular`, `direccion`, `localidad`, `provincia_id`, `observaciones`, `created_at`, `updated_at`) VALUES
(1, 'Juan', 'Perez', NULL, '3815096109', 'aoosaosoasdadasd', 'sda', 3, '', '2026-03-27 19:41:47', '2026-03-27 19:41:47'),
(2, 'Daniel Alejandro', 'Quevedo', NULL, '123123123123', 'Octaviano Vera 845', 'San Miguel de Tucumán', 3, '', '2026-03-27 19:47:01', '2026-03-27 19:47:01'),
(3, 'Daniel Alejandro', 'Quevedo', NULL, '+543815096109', 'Octaviano Vera 845', 'San Miguel de Tucumán', 3, '', '2026-03-27 19:58:02', '2026-03-27 19:58:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `provincias`
--

DROP TABLE IF EXISTS `provincias`;
CREATE TABLE IF NOT EXISTS `provincias` (
  `id` tinyint UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `provincias`
--

INSERT INTO `provincias` (`id`, `nombre`) VALUES
(1, 'Catamarca'),
(2, 'Santiago del Estero'),
(3, 'Tucumán');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `stock_movimientos`
--

DROP TABLE IF EXISTS `stock_movimientos`;
CREATE TABLE IF NOT EXISTS `stock_movimientos` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `articulo_id` mediumint UNSIGNED NOT NULL,
  `usuario_id` int UNSIGNED NOT NULL,
  `tipo` enum('entrada','salida','ajuste') COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` smallint NOT NULL,
  `stock_antes` smallint NOT NULL,
  `stock_despues` smallint NOT NULL,
  `referencia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_articulo` (`articulo_id`),
  KEY `idx_fecha` (`created_at`),
  KEY `fk_mov_usr` (`usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `stock_movimientos`
--

INSERT INTO `stock_movimientos` (`id`, `articulo_id`, `usuario_id`, `tipo`, `cantidad`, `stock_antes`, `stock_despues`, `referencia`, `created_at`) VALUES
(1, 1, 3, 'entrada', 1, 0, 1, 'Alta de artículo', '2026-03-27 18:34:58'),
(2, 2, 3, 'entrada', 1, 0, 1, 'Alta de artículo', '2026-03-27 19:07:48'),
(3, 2, 3, 'salida', 1, 1, 0, 'Venta #1', '2026-03-27 19:41:47'),
(4, 1, 3, 'salida', 1, 1, 0, 'Venta #2', '2026-03-27 19:47:01'),
(5, 1, 2, 'salida', 1, 1, 0, 'Venta #3', '2026-03-27 19:58:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `clave` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('admin','vendedor') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vendedor',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `ultimo_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  KEY `idx_usuario` (`usuario`),
  KEY `idx_rol` (`rol`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `usuario`, `nombre`, `apellido`, `clave`, `rol`, `activo`, `ultimo_login`, `created_at`) VALUES
(1, 'admin', 'Admin', 'Imperio', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, NULL, '2026-03-27 16:50:56'),
(2, 'vendedor', 'Vendedor', 'Demo', '$2y$12$z7gsppPNfyTgvWQQpvYNduVpeJOrwDUungiVm9Fji3Z/zHOdYL0oG', 'vendedor', 1, '2026-03-27 19:57:00', '2026-03-27 16:50:56'),
(3, 'danqueve', 'Admin', 'Imperio', '$2y$10$12OCSwSW3HwKdFl55RjlR.Jy5YHU3jSProuqpSA8i7cajcH0JJsMy', 'admin', 1, '2026-03-27 19:58:12', '2026-03-27 16:56:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

DROP TABLE IF EXISTS `ventas`;
CREATE TABLE IF NOT EXISTS `ventas` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `cliente_id` int UNSIGNED NOT NULL,
  `vendedor_id` int UNSIGNED NOT NULL,
  `tipo_pago` enum('contado','financiado') COLLATE utf8mb4_unicode_ci NOT NULL,
  `cuotas` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `estado` enum('pendiente','confirmada','anulada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'confirmada',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cliente` (`cliente_id`),
  KEY `idx_vendedor` (`vendedor_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id`, `cliente_id`, `vendedor_id`, `tipo_pago`, `cuotas`, `total`, `estado`, `observaciones`, `created_at`) VALUES
(1, 1, 3, 'financiado', 12, 1320000.00, 'anulada', '', '2026-03-27 19:41:47'),
(2, 2, 3, 'financiado', 12, 1752000.00, 'anulada', '', '2026-03-27 19:47:01'),
(3, 3, 2, 'financiado', 12, 1752000.00, 'anulada', '', '2026-03-27 19:58:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `venta_detalles`
--

DROP TABLE IF EXISTS `venta_detalles`;
CREATE TABLE IF NOT EXISTS `venta_detalles` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `venta_id` int UNSIGNED NOT NULL,
  `articulo_id` mediumint UNSIGNED NOT NULL,
  `cantidad` smallint NOT NULL DEFAULT '1',
  `precio_unitario` decimal(12,2) NOT NULL,
  `subtotal` decimal(14,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_venta` (`venta_id`),
  KEY `idx_articulo` (`articulo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `venta_detalles`
--

INSERT INTO `venta_detalles` (`id`, `venta_id`, `articulo_id`, `cantidad`, `precio_unitario`, `subtotal`) VALUES
(1, 1, 2, 1, 1320000.00, 1320000.00),
(2, 2, 1, 1, 1752000.00, 1752000.00),
(3, 3, 1, 1, 1752000.00, 1752000.00);

--
-- Disparadores `venta_detalles`
--
DROP TRIGGER IF EXISTS `trg_descuento_stock`;
DELIMITER $$
CREATE TRIGGER `trg_descuento_stock` AFTER INSERT ON `venta_detalles` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `articulos`
--
ALTER TABLE `articulos` ADD FULLTEXT KEY `idx_busqueda` (`nombre`,`descripcion`);

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `articulos`
--
ALTER TABLE `articulos`
  ADD CONSTRAINT `fk_art_cat` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `fk_cli_prov` FOREIGN KEY (`provincia_id`) REFERENCES `provincias` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `stock_movimientos`
--
ALTER TABLE `stock_movimientos`
  ADD CONSTRAINT `fk_mov_art` FOREIGN KEY (`articulo_id`) REFERENCES `articulos` (`id`),
  ADD CONSTRAINT `fk_mov_usr` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `fk_vta_cli` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `fk_vta_ven` FOREIGN KEY (`vendedor_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `venta_detalles`
--
ALTER TABLE `venta_detalles`
  ADD CONSTRAINT `fk_det_art` FOREIGN KEY (`articulo_id`) REFERENCES `articulos` (`id`),
  ADD CONSTRAINT `fk_det_vta` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
