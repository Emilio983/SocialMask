<?php
// Script para limpiar el OPcache de PHP
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache limpiado exitosamente\n";
} else {
    echo "OPcache no está habilitado\n";
}

// También limpiar el cache de realpath si existe
if (function_exists('clearstatcache')) {
    clearstatcache(true);
    echo "Stat cache limpiado exitosamente\n";
}
?>
