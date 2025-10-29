<!-- ============================================
     thesocialmask GLOBAL SCRIPTS
     Scripts globales para todas las páginas
     ============================================ -->

<!-- Toast Notifications System - DEBE CARGAR PRIMERO -->
<script src="/assets/js/toast-notifications.js"></script>

<!-- Modern Notification System -->
<script src="/assets/js/notifications.js"></script>

<!-- P2P Client - Sistema P2P descentralizado (sin Gun.js) -->
<script src="/assets/js/p2p-client.js"></script>

<!-- Auto-inicializar P2P Client si está activado -->
<script>
// Guardar user_id en sessionStorage para P2P Client
document.addEventListener('DOMContentLoaded', async () => {
    // Obtener user_id de la sesión PHP si existe
    <?php if (isset($_SESSION['user_id'])): ?>
    sessionStorage.setItem('user_id', '<?php echo $_SESSION['user_id']; ?>');
    <?php endif; ?>

    // El P2P Client se auto-inicializa si p2pMode está activo
    // Ver p2p-client.js líneas 442-454
});
</script>
