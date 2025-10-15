// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/governance/TimelockController.sol";
import "@openzeppelin/contracts/access/AccessControl.sol";

/**
 * @title SpheraTimelockController
 * @dev Timelock controller for Sphera Governance System
 * 
 * Propuestas aprobadas deben esperar en queue 48 horas antes de ejecutarse.
 * Esto da tiempo a la comunidad para reaccionar a propuestas maliciosas.
 * 
 * Features:
 * - 48 hour minimum delay (configurable)
 * - Role-based access control
 * - Emergency cancellation
 * - Batch execution support
 */
contract SpheraTimelockController is TimelockController {
    
    // ============================================
    // ROLES
    // ============================================
    
    bytes32 public constant CANCELLER_ROLE = keccak256("CANCELLER_ROLE");
    bytes32 public constant EMERGENCY_ROLE = keccak256("EMERGENCY_ROLE");
    
    // ============================================
    // EVENTS
    // ============================================
    
    event ProposalQueued(
        bytes32 indexed id,
        address indexed target,
        uint256 value,
        bytes data,
        bytes32 predecessor,
        uint256 delay,
        uint256 eta
    );
    
    event ProposalExecuted(
        bytes32 indexed id,
        address indexed target,
        uint256 value,
        bytes data,
        uint256 executedAt
    );
    
    event ProposalCancelled(
        bytes32 indexed id,
        address indexed canceller,
        uint256 cancelledAt
    );
    
    event EmergencyExecuted(
        bytes32 indexed id,
        address indexed executor,
        uint256 executedAt
    );
    
    // ============================================
    // STATE VARIABLES
    // ============================================
    
    uint256 public constant MIN_DELAY = 2 days; // 48 hours
    uint256 public constant MAX_DELAY = 30 days;
    
    // Mapping to track proposal metadata
    mapping(bytes32 => ProposalMetadata) public proposalMetadata;
    
    struct ProposalMetadata {
        uint256 proposalId; // External proposal ID from governance
        address proposer;
        uint256 queuedAt;
        uint256 eta;
        bool executed;
        bool cancelled;
        string description;
    }
    
    // ============================================
    // CONSTRUCTOR
    // ============================================
    
    /**
     * @dev Constructor
     * @param minDelay Minimum delay in seconds (48 hours = 172800)
     * @param proposers Array of addresses that can propose
     * @param executors Array of addresses that can execute
     * @param admin Admin address
     */
    constructor(
        uint256 minDelay,
        address[] memory proposers,
        address[] memory executors,
        address admin
    ) TimelockController(minDelay, proposers, executors, admin) {
        require(minDelay >= MIN_DELAY, "Delay too short");
        require(minDelay <= MAX_DELAY, "Delay too long");
        
        // Grant CANCELLER_ROLE to admin
        _grantRole(CANCELLER_ROLE, admin);
        _grantRole(EMERGENCY_ROLE, admin);
    }
    
    // ============================================
    // QUEUE FUNCTIONS
    // ============================================
    
    /**
     * @dev Queue a proposal for execution after timelock
     * @param target Target contract address
     * @param value ETH value to send
     * @param data Encoded function call
     * @param predecessor Previous operation hash (0x0 if none)
     * @param salt Unique salt
     * @param delay Additional delay beyond minimum
     * @param proposalId External proposal ID
     * @param proposer Address of proposer
     * @param description Proposal description
     */
    function queueProposal(
        address target,
        uint256 value,
        bytes calldata data,
        bytes32 predecessor,
        bytes32 salt,
        uint256 delay,
        uint256 proposalId,
        address proposer,
        string calldata description
    ) external onlyRole(PROPOSER_ROLE) returns (bytes32) {
        require(delay >= getMinDelay(), "Delay below minimum");
        
        // Schedule the operation
        bytes32 id = hashOperation(target, value, data, predecessor, salt);
        
        schedule(target, value, data, predecessor, salt, delay);
        
        // Store metadata
        uint256 eta = block.timestamp + delay;
        proposalMetadata[id] = ProposalMetadata({
            proposalId: proposalId,
            proposer: proposer,
            queuedAt: block.timestamp,
            eta: eta,
            executed: false,
            cancelled: false,
            description: description
        });
        
        emit ProposalQueued(id, target, value, data, predecessor, delay, eta);
        
        return id;
    }
    
    /**
     * @dev Queue a batch of operations
     */
    function queueBatch(
        address[] calldata targets,
        uint256[] calldata values,
        bytes[] calldata payloads,
        bytes32 predecessor,
        bytes32 salt,
        uint256 delay,
        uint256 proposalId,
        address proposer,
        string calldata description
    ) external onlyRole(PROPOSER_ROLE) returns (bytes32) {
        require(delay >= getMinDelay(), "Delay below minimum");
        
        bytes32 id = hashOperationBatch(targets, values, payloads, predecessor, salt);
        
        scheduleBatch(targets, values, payloads, predecessor, salt, delay);
        
        uint256 eta = block.timestamp + delay;
        proposalMetadata[id] = ProposalMetadata({
            proposalId: proposalId,
            proposer: proposer,
            queuedAt: block.timestamp,
            eta: eta,
            executed: false,
            cancelled: false,
            description: description
        });
        
        emit ProposalQueued(id, targets[0], values[0], payloads[0], predecessor, delay, eta);
        
        return id;
    }
    
    // ============================================
    // EXECUTE FUNCTIONS
    // ============================================
    
    /**
     * @dev Execute a queued proposal after timelock
     */
    function executeProposal(
        address target,
        uint256 value,
        bytes calldata data,
        bytes32 predecessor,
        bytes32 salt
    ) external payable onlyRole(EXECUTOR_ROLE) {
        bytes32 id = hashOperation(target, value, data, predecessor, salt);
        
        require(!proposalMetadata[id].executed, "Already executed");
        require(!proposalMetadata[id].cancelled, "Proposal cancelled");
        require(isOperationReady(id), "Timelock not expired");
        
        execute(target, value, data, predecessor, salt);
        
        proposalMetadata[id].executed = true;
        
        emit ProposalExecuted(id, target, value, data, block.timestamp);
    }
    
    /**
     * @dev Execute a batch of operations
     */
    function executeBatch(
        address[] calldata targets,
        uint256[] calldata values,
        bytes[] calldata payloads,
        bytes32 predecessor,
        bytes32 salt
    ) external payable onlyRole(EXECUTOR_ROLE) {
        bytes32 id = hashOperationBatch(targets, values, payloads, predecessor, salt);
        
        require(!proposalMetadata[id].executed, "Already executed");
        require(!proposalMetadata[id].cancelled, "Proposal cancelled");
        require(isOperationReady(id), "Timelock not expired");
        
        executeBatch(targets, values, payloads, predecessor, salt);
        
        proposalMetadata[id].executed = true;
        
        emit ProposalExecuted(id, targets[0], values[0], payloads[0], block.timestamp);
    }
    
    // ============================================
    // CANCEL FUNCTIONS
    // ============================================
    
    /**
     * @dev Cancel a queued proposal before execution
     */
    function cancelProposal(bytes32 id) external onlyRole(CANCELLER_ROLE) {
        require(!proposalMetadata[id].executed, "Already executed");
        require(!proposalMetadata[id].cancelled, "Already cancelled");
        require(isOperationPending(id), "Operation not pending");
        
        cancel(id);
        
        proposalMetadata[id].cancelled = true;
        
        emit ProposalCancelled(id, msg.sender, block.timestamp);
    }
    
    /**
     * @dev Emergency cancel - can cancel any operation
     */
    function emergencyCancel(bytes32 id) external onlyRole(EMERGENCY_ROLE) {
        require(!proposalMetadata[id].executed, "Already executed");
        
        if (isOperationPending(id) || isOperationReady(id)) {
            cancel(id);
        }
        
        proposalMetadata[id].cancelled = true;
        
        emit ProposalCancelled(id, msg.sender, block.timestamp);
    }
    
    // ============================================
    // VIEW FUNCTIONS
    // ============================================
    
    /**
     * @dev Check if proposal is ready to execute
     */
    function isProposalReady(bytes32 id) external view returns (bool) {
        return isOperationReady(id) 
            && !proposalMetadata[id].executed 
            && !proposalMetadata[id].cancelled;
    }
    
    /**
     * @dev Check if proposal is pending
     */
    function isProposalPending(bytes32 id) external view returns (bool) {
        return isOperationPending(id) 
            && !proposalMetadata[id].executed 
            && !proposalMetadata[id].cancelled;
    }
    
    /**
     * @dev Get time remaining until execution
     */
    function getTimeRemaining(bytes32 id) external view returns (uint256) {
        if (!isOperationPending(id)) return 0;
        
        uint256 eta = proposalMetadata[id].eta;
        if (block.timestamp >= eta) return 0;
        
        return eta - block.timestamp;
    }
    
    /**
     * @dev Get proposal metadata
     */
    function getProposalMetadata(bytes32 id) external view returns (
        uint256 proposalId,
        address proposer,
        uint256 queuedAt,
        uint256 eta,
        bool executed,
        bool cancelled,
        string memory description
    ) {
        ProposalMetadata memory meta = proposalMetadata[id];
        return (
            meta.proposalId,
            meta.proposer,
            meta.queuedAt,
            meta.eta,
            meta.executed,
            meta.cancelled,
            meta.description
        );
    }
    
    // ============================================
    // ADMIN FUNCTIONS
    // ============================================
    
    /**
     * @dev Update minimum delay (only via timelock)
     */
    function updateDelay(uint256 newDelay) external virtual override {
        require(msg.sender == address(this), "TimelockController: caller must be timelock");
        require(newDelay >= MIN_DELAY, "Delay too short");
        require(newDelay <= MAX_DELAY, "Delay too long");
        emit MinDelayChange(_minDelay, newDelay);
        _minDelay = newDelay;
    }
}
