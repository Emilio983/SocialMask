// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/governance/Governor.sol";
import "@openzeppelin/contracts/governance/extensions/GovernorSettings.sol";
import "@openzeppelin/contracts/governance/extensions/GovernorCountingSimple.sol";
import "@openzeppelin/contracts/governance/extensions/GovernorVotes.sol";
import "@openzeppelin/contracts/governance/extensions/GovernorVotesQuorumFraction.sol";
import "@openzeppelin/contracts/governance/extensions/GovernorTimelockControl.sol";

/**
 * @title SpheraGovernor
 * @dev Contrato de gobernanza descentralizada para el DAO de Sphera
 * @notice Permite a los holders de GOVSPHE crear y votar en propuestas
 * 
 * Características principales:
 * - Votación ponderada por balance de tokens
 * - Threshold de propuesta: 1000 GOVSPHE
 * - Quorum: 4% del supply total
 * - Voting delay: 1 día (tiempo antes de que comience la votación)
 * - Voting period: 7 días (duración de la votación)
 * - Timelock: 2 días (delay antes de ejecutar propuesta aprobada)
 * 
 * Tipos de propuestas:
 * 1. Parameter Changes (cambiar APY, fees, etc.)
 * 2. Treasury Management (asignación de fondos)
 * 3. Contract Upgrades (actualizar contratos)
 * 4. Feature Proposals (nuevas características)
 * 5. Emergency Actions (acciones de emergencia)
 * 
 * Estados de propuesta:
 * - Pending: Esperando voting delay
 * - Active: En votación
 * - Canceled: Cancelada por el proposer
 * - Defeated: Rechazada (no alcanzó quorum o más votos en contra)
 * - Succeeded: Aprobada (alcanzó quorum y mayoría a favor)
 * - Queued: En timelock (esperando delay)
 * - Expired: Expirada (no ejecutada a tiempo)
 * - Executed: Ejecutada exitosamente
 */
contract SpheraGovernor is 
    Governor, 
    GovernorSettings, 
    GovernorCountingSimple, 
    GovernorVotes, 
    GovernorVotesQuorumFraction,
    GovernorTimelockControl 
{
    
    // ============================================
    // STATE VARIABLES
    // ============================================
    
    /// @notice Categorías de propuestas
    enum ProposalCategory {
        ParameterChange,
        TreasuryManagement,
        ContractUpgrade,
        FeatureProposal,
        EmergencyAction
    }
    
    /// @notice Información adicional de propuestas
    struct ProposalInfo {
        ProposalCategory category;
        address proposer;
        uint256 createdAt;
    }
    
    /// @notice Mapeo de propuestas a su información adicional
    mapping(uint256 => ProposalInfo) public proposalInfo;
    
    /// @notice Total de propuestas creadas
    uint256 public proposalCount;
    
    /// @notice Propuestas creadas por cada usuario
    mapping(address => uint256[]) public userProposals;
    
    // ============================================
    // EVENTS
    // ============================================
    
    event ProposalCreatedWithCategory(
        uint256 indexed proposalId,
        address indexed proposer,
        ProposalCategory category,
        string description
    );
    
    // ============================================
    // CONSTRUCTOR
    // ============================================
    
    /**
     * @dev Constructor del Governor
     * @param _token Token de gobernanza (GOVSPHE)
     * @param _timelock Contrato de timelock
     */
    constructor(
        IVotes _token,
        TimelockController _timelock
    )
        Governor("Sphera Governor")
        GovernorSettings(
            1 days,    /* voting delay: 1 día */
            7 days,    /* voting period: 7 días */
            1000e18    /* proposal threshold: 1000 GOVSPHE */
        )
        GovernorVotes(_token)
        GovernorVotesQuorumFraction(4) /* 4% quorum */
        GovernorTimelockControl(_timelock)
    {}
    
    // ============================================
    // PROPOSAL FUNCTIONS
    // ============================================
    
    /**
     * @notice Crear una propuesta con categoría
     * @param targets Array de contratos a ejecutar
     * @param values Array de valores ETH a enviar
     * @param calldatas Array de datos de llamadas
     * @param description Descripción de la propuesta
     * @param category Categoría de la propuesta
     * @return proposalId ID de la propuesta creada
     */
    function proposeWithCategory(
        address[] memory targets,
        uint256[] memory values,
        bytes[] memory calldatas,
        string memory description,
        ProposalCategory category
    ) public returns (uint256) {
        // Crear propuesta normal
        uint256 proposalId = propose(targets, values, calldatas, description);
        
        // Guardar información adicional
        proposalInfo[proposalId] = ProposalInfo({
            category: category,
            proposer: msg.sender,
            createdAt: block.timestamp
        });
        
        proposalCount++;
        userProposals[msg.sender].push(proposalId);
        
        emit ProposalCreatedWithCategory(proposalId, msg.sender, category, description);
        
        return proposalId;
    }
    
    /**
     * @notice Obtener propuestas de un usuario
     * @param user Dirección del usuario
     * @return Array de IDs de propuestas
     */
    function getUserProposals(address user) external view returns (uint256[] memory) {
        return userProposals[user];
    }
    
    /**
     * @notice Obtener información de una propuesta
     * @param proposalId ID de la propuesta
     * @return category Categoría
     * @return proposer Creador
     * @return createdAt Timestamp de creación
     * @return state Estado actual
     */
    function getProposalInfo(uint256 proposalId) 
        external 
        view 
        returns (
            ProposalCategory category,
            address proposer,
            uint256 createdAt,
            ProposalState state
        )
    {
        ProposalInfo memory info = proposalInfo[proposalId];
        return (
            info.category,
            info.proposer,
            info.createdAt,
            state(proposalId)
        );
    }
    
    // ============================================
    // VOTING FUNCTIONS
    // ============================================
    
    /**
     * @notice Votar en una propuesta con razón
     * @param proposalId ID de la propuesta
     * @param support 0=Against, 1=For, 2=Abstain
     * @param reason Razón del voto
     * @return balance Peso del voto
     */
    function castVoteWithReasonAndParams(
        uint256 proposalId,
        uint8 support,
        string calldata reason,
        bytes memory params
    ) public override returns (uint256) {
        return super.castVoteWithReasonAndParams(proposalId, support, reason, params);
    }
    
    // ============================================
    // EXECUTION FUNCTIONS
    // ============================================
    
    /**
     * @notice Encolar propuesta en timelock
     * @dev Debe llamarse después de que la propuesta sea aprobada
     */
    function queue(
        address[] memory targets,
        uint256[] memory values,
        bytes[] memory calldatas,
        bytes32 descriptionHash
    ) public override returns (uint256) {
        return super.queue(targets, values, calldatas, descriptionHash);
    }
    
    /**
     * @notice Ejecutar propuesta después del timelock
     * @dev Solo puede ejecutarse después del delay del timelock
     */
    function execute(
        address[] memory targets,
        uint256[] memory values,
        bytes[] memory calldatas,
        bytes32 descriptionHash
    ) public payable override returns (uint256) {
        return super.execute(targets, values, calldatas, descriptionHash);
    }
    
    // ============================================
    // VIEW FUNCTIONS
    // ============================================
    
    /**
     * @notice Obtener resultados de votación de una propuesta
     * @param proposalId ID de la propuesta
     * @return againstVotes Votos en contra
     * @return forVotes Votos a favor
     * @return abstainVotes Votos abstención
     */
    function proposalVotes(uint256 proposalId)
        external
        view
        returns (
            uint256 againstVotes,
            uint256 forVotes,
            uint256 abstainVotes
        )
    {
        return super.proposalVotes(proposalId);
    }
    
    /**
     * @notice Verificar si un voto alcanzó el quorum
     * @param proposalId ID de la propuesta
     * @return True si alcanzó quorum
     */
    function quorumReached(uint256 proposalId) external view returns (bool) {
        return _quorumReached(proposalId);
    }
    
    /**
     * @notice Verificar si una propuesta fue aprobada
     * @param proposalId ID de la propuesta
     * @return True si fue aprobada
     */
    function voteSucceeded(uint256 proposalId) external view returns (bool) {
        return _voteSucceeded(proposalId);
    }
    
    /**
     * @notice Obtener el quorum requerido en un bloque específico
     * @param blockNumber Número de bloque
     * @return Cantidad de votos requeridos para quorum
     */
    function quorumAtBlock(uint256 blockNumber) external view returns (uint256) {
        return quorum(blockNumber);
    }
    
    /**
     * @notice Obtener estadísticas del governance
     * @return totalProposals Total de propuestas
     * @return currentQuorum Quorum actual (%)
     * @return votingDelay Delay de votación
     * @return votingPeriod Período de votación
     * @return proposalThreshold Threshold para proponer
     */
    function getGovernanceStats() 
        external 
        view 
        returns (
            uint256 totalProposals,
            uint256 currentQuorum,
            uint256 votingDelay,
            uint256 votingPeriod,
            uint256 proposalThreshold
        )
    {
        return (
            proposalCount,
            quorumNumerator(),
            votingDelay(),
            votingPeriod(),
            proposalThreshold()
        );
    }
    
    // ============================================
    // REQUIRED OVERRIDES
    // ============================================
    
    function votingDelay()
        public
        view
        override(Governor, GovernorSettings)
        returns (uint256)
    {
        return super.votingDelay();
    }
    
    function votingPeriod()
        public
        view
        override(Governor, GovernorSettings)
        returns (uint256)
    {
        return super.votingPeriod();
    }
    
    function quorum(uint256 blockNumber)
        public
        view
        override(Governor, GovernorVotesQuorumFraction)
        returns (uint256)
    {
        return super.quorum(blockNumber);
    }
    
    function state(uint256 proposalId)
        public
        view
        override(Governor, GovernorTimelockControl)
        returns (ProposalState)
    {
        return super.state(proposalId);
    }
    
    function proposalNeedsQueuing(uint256 proposalId)
        public
        view
        override(Governor, GovernorTimelockControl)
        returns (bool)
    {
        return super.proposalNeedsQueuing(proposalId);
    }
    
    function proposalThreshold()
        public
        view
        override(Governor, GovernorSettings)
        returns (uint256)
    {
        return super.proposalThreshold();
    }
    
    function _queueOperations(
        uint256 proposalId,
        address[] memory targets,
        uint256[] memory values,
        bytes[] memory calldatas,
        bytes32 descriptionHash
    ) internal override(Governor, GovernorTimelockControl) returns (uint48) {
        return super._queueOperations(proposalId, targets, values, calldatas, descriptionHash);
    }
    
    function _executeOperations(
        uint256 proposalId,
        address[] memory targets,
        uint256[] memory values,
        bytes[] memory calldatas,
        bytes32 descriptionHash
    ) internal override(Governor, GovernorTimelockControl) {
        super._executeOperations(proposalId, targets, values, calldatas, descriptionHash);
    }
    
    function _cancel(
        address[] memory targets,
        uint256[] memory values,
        bytes[] memory calldatas,
        bytes32 descriptionHash
    ) internal override(Governor, GovernorTimelockControl) returns (uint256) {
        return super._cancel(targets, values, calldatas, descriptionHash);
    }
    
    function _executor()
        internal
        view
        override(Governor, GovernorTimelockControl)
        returns (address)
    {
        return super._executor();
    }
}
