// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/governance/TimelockController.sol";

/**
 * @title SpheraTimelock
 * @dev Timelock personalizado para el DAO de Sphera
 * @notice Extiende TimelockController de OpenZeppelin con funcionalidad adicional
 * 
 * El Timelock actúa como un intermediario entre el Governor y los contratos del sistema:
 * 1. Governor aprueba una propuesta
 * 2. Propuesta se encola en el Timelock
 * 3. Esperamos el delay mínimo (2 días)
 * 4. Cualquiera puede ejecutar la propuesta
 * 
 * Roles:
 * - PROPOSER_ROLE: Puede encolar operaciones (solo el Governor)
 * - EXECUTOR_ROLE: Puede ejecutar operaciones (cualquiera después del delay)
 * - CANCELLER_ROLE: Puede cancelar operaciones pendientes (solo el Governor)
 * - ADMIN_ROLE: Puede gestionar roles (inicialmente el deployer, luego el Timelock mismo)
 * 
 * Delay: 2 días (172800 segundos)
 */
contract SpheraTimelock is TimelockController {
    
    // ============================================
    // STATE VARIABLES
    // ============================================
    
    /// @notice Mapeo de operaciones ejecutadas
    mapping(bytes32 => bool) public executedOperations;
    
    /// @notice Mapeo de operaciones canceladas
    mapping(bytes32 => bool) public cancelledOperations;
    
    /// @notice Total de operaciones encoladas
    uint256 public totalQueued;
    
    /// @notice Total de operaciones ejecutadas
    uint256 public totalExecuted;
    
    /// @notice Total de operaciones canceladas
    uint256 public totalCancelled;
    
    // ============================================
    // EVENTS
    // ============================================
    
    event OperationQueued(bytes32 indexed id, uint256 eta);
    event OperationExecuted(bytes32 indexed id);
    event OperationCancelled(bytes32 indexed id);
    
    // ============================================
    // CONSTRUCTOR
    // ============================================
    
    /**
     * @dev Constructor del Timelock
     * @param minDelay Delay mínimo en segundos (2 días = 172800)
     * @param proposers Array de addresses con rol PROPOSER (Governor)
     * @param executors Array de addresses con rol EXECUTOR (address(0) = anyone)
     * @param admin Address con rol ADMIN (address(0) = renunciar después del setup)
     */
    constructor(
        uint256 minDelay,
        address[] memory proposers,
        address[] memory executors,
        address admin
    ) TimelockController(minDelay, proposers, executors, admin) {
        // El constructor de TimelockController ya maneja todo
    }
    
    // ============================================
    // WRAPPER FUNCTIONS CON TRACKING
    // ============================================
    
    /**
     * @notice Schedule una operación (wrapper con tracking)
     * @dev Solo puede ser llamado por addresses con rol PROPOSER
     */
    function schedule(
        address target,
        uint256 value,
        bytes calldata data,
        bytes32 predecessor,
        bytes32 salt,
        uint256 delay
    ) public virtual override {
        bytes32 id = hashOperation(target, value, data, predecessor, salt);
        super.schedule(target, value, data, predecessor, salt, delay);
        
        totalQueued++;
        emit OperationQueued(id, block.timestamp + delay);
    }
    
    /**
     * @notice Schedule un batch de operaciones (wrapper con tracking)
     */
    function scheduleBatch(
        address[] calldata targets,
        uint256[] calldata values,
        bytes[] calldata payloads,
        bytes32 predecessor,
        bytes32 salt,
        uint256 delay
    ) public virtual override {
        bytes32 id = hashOperationBatch(targets, values, payloads, predecessor, salt);
        super.scheduleBatch(targets, values, payloads, predecessor, salt, delay);
        
        totalQueued++;
        emit OperationQueued(id, block.timestamp + delay);
    }
    
    /**
     * @notice Ejecutar una operación (wrapper con tracking)
     * @dev Puede ser llamado por cualquiera después del delay
     */
    function execute(
        address target,
        uint256 value,
        bytes calldata payload,
        bytes32 predecessor,
        bytes32 salt
    ) public payable virtual override {
        bytes32 id = hashOperation(target, value, payload, predecessor, salt);
        super.execute(target, value, payload, predecessor, salt);
        
        executedOperations[id] = true;
        totalExecuted++;
        emit OperationExecuted(id);
    }
    
    /**
     * @notice Ejecutar un batch de operaciones (wrapper con tracking)
     */
    function executeBatch(
        address[] calldata targets,
        uint256[] calldata values,
        bytes[] calldata payloads,
        bytes32 predecessor,
        bytes32 salt
    ) public payable virtual override {
        bytes32 id = hashOperationBatch(targets, values, payloads, predecessor, salt);
        super.executeBatch(targets, values, payloads, predecessor, salt);
        
        executedOperations[id] = true;
        totalExecuted++;
        emit OperationExecuted(id);
    }
    
    /**
     * @notice Cancelar una operación (wrapper con tracking)
     * @dev Solo puede ser llamado por addresses con rol CANCELLER
     */
    function cancel(bytes32 id) public virtual override {
        super.cancel(id);
        
        cancelledOperations[id] = true;
        totalCancelled++;
        emit OperationCancelled(id);
    }
    
    // ============================================
    // VIEW FUNCTIONS
    // ============================================
    
    /**
     * @notice Verificar si una operación fue ejecutada
     * @param id Hash de la operación
     * @return True si fue ejecutada
     */
    function isExecuted(bytes32 id) external view returns (bool) {
        return executedOperations[id];
    }
    
    /**
     * @notice Verificar si una operación fue cancelada
     * @param id Hash de la operación
     * @return True si fue cancelada
     */
    function isCancelled(bytes32 id) external view returns (bool) {
        return cancelledOperations[id];
    }
    
    /**
     * @notice Obtener estadísticas del timelock
     * @return queued Total de operaciones encoladas
     * @return executed Total de operaciones ejecutadas
     * @return cancelled Total de operaciones canceladas
     * @return minDelay Delay mínimo
     */
    function getTimelockStats()
        external
        view
        returns (
            uint256 queued,
            uint256 executed,
            uint256 cancelled,
            uint256 minDelay
        )
    {
        return (
            totalQueued,
            totalExecuted,
            totalCancelled,
            getMinDelay()
        );
    }
    
    /**
     * @notice Obtener información de una operación
     * @param id Hash de la operación
     * @return isPending Si está pendiente
     * @return isReady Si está lista para ejecutar
     * @return isDone Si ya fue ejecutada
     * @return timestamp Timestamp de cuando puede ejecutarse
     */
    function getOperationInfo(bytes32 id)
        external
        view
        returns (
            bool isPending,
            bool isReady,
            bool isDone,
            uint256 timestamp
        )
    {
        return (
            isOperationPending(id),
            isOperationReady(id),
            isOperationDone(id),
            getTimestamp(id)
        );
    }
    
    /**
     * @notice Calcular el hash de una operación
     * @dev Útil para verificar si una operación existe
     */
    function hashOperation(
        address target,
        uint256 value,
        bytes calldata data,
        bytes32 predecessor,
        bytes32 salt
    ) public pure override returns (bytes32) {
        return super.hashOperation(target, value, data, predecessor, salt);
    }
    
    /**
     * @notice Calcular el hash de un batch de operaciones
     */
    function hashOperationBatch(
        address[] calldata targets,
        uint256[] calldata values,
        bytes[] calldata payloads,
        bytes32 predecessor,
        bytes32 salt
    ) public pure override returns (bytes32) {
        return super.hashOperationBatch(targets, values, payloads, predecessor, salt);
    }
}
