<?php
/**
 * Script para inicializar datos de ejemplo en Governance
 * Solo ejecutar una vez para poblar con datos de muestra
 */
require_once __DIR__ . '/../../config/connection.php';

try {
    $pdo->beginTransaction();
    
    // Limpiar datos existentes de muestra
    $pdo->exec("DELETE FROM governance_proposals WHERE creator_id = 999");
    
    // Insertar propuestas de ejemplo
    $proposals = [
        [
            'title' => 'Reducir Fee de Swap al 0.25%',
            'description' => 'Propuesta para reducir el fee de swap de 0.3% a 0.25% para aumentar la competitividad y atraer más usuarios a la plataforma. Esto beneficiaría especialmente a traders de alto volumen y mejoraría la liquidez general del protocolo.',
            'type' => 'fee_change',
            'status' => 'active',
            'for' => 1542,
            'against' => 320
        ],
        [
            'title' => 'Implementar Sistema de Recompensas por Staking',
            'description' => 'Proponer un nuevo sistema de recompensas que incentive el staking de largo plazo. Los usuarios que mantengan sus tokens en stake por más de 30 días recibirán bonificaciones adicionales del 5%. Esto ayudará a reducir la volatilidad y aumentará el valor del token.',
            'type' => 'platform_change',
            'status' => 'active',
            'for' => 2890,
            'against' => 410
        ],
        [
            'title' => 'Integración con Aave para Lending',
            'description' => 'Propuesta para integrar préstamos mediante Aave Protocol. Los holders de SPHE podrían usar sus tokens como colateral para préstamos en USDC/DAI. Esto aumentaría la utilidad del token y atraería usuarios de DeFi.',
            'type' => 'feature_request',
            'status' => 'active',
            'for' => 1920,
            'against' => 870
        ],
        [
            'title' => 'Aumentar Liquidez en QuickSwap',
            'description' => 'Asignar $100,000 del tesoro comunitario para aumentar liquidez del par SPHE/USDT en QuickSwap. Esto reducirá el slippage y hará más atractivo el trading del token.',
            'type' => 'platform_change',
            'status' => 'passed',
            'for' => 4150,
            'against' => 820
        ],
        [
            'title' => 'Activar Gasless Transactions',
            'description' => 'Proponer la activación permanente de transacciones sin gas mediante Biconomy para todas las operaciones del protocolo. Esto mejorará significativamente la experiencia del usuario y reducirá barreras de entrada.',
            'type' => 'platform_change',
            'status' => 'active',
            'for' => 3240,
            'against' => 1150
        ]
    ];
    
    foreach ($proposals as $p) {
        $stmt = $pdo->prepare("
            INSERT INTO governance_proposals 
            (creator_id, title, description, proposal_type, voting_start, voting_end, 
             total_votes_for, total_votes_against, status, created_at)
            VALUES (999, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY), ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $p['title'],
            $p['description'],
            $p['type'],
            $p['for'],
            $p['against'],
            $p['status']
        ]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => count($proposals) . ' propuestas de muestra creadas',
        'note' => 'Datos con creator_id=999 son de ejemplo'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
