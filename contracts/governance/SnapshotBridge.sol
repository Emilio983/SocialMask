// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/access/AccessControl.sol";
import "@openzeppelin/contracts/security/ReentrancyGuard.sol";
import "@openzeppelin/contracts/utils/cryptography/MerkleProof.sol";
import "@openzeppelin/contracts/security/Pausable.sol";

/**
 * @title SnapshotBridge
 * @dev Bridge between Snapshot.org off-chain voting and on-chain execution
 * Allows gasless voting on Snapshot with verifiable on-chain execution
 */
contract SnapshotBridge is AccessControl, ReentrancyGuard, Pausable {
    
    // ============================================
    // ROLES
    // ============================================

    bytes32 public constant RELAYER_ROLE = keccak256("RELAYER_ROLE");
    bytes32 public constant EXECUTOR_ROLE = keccak256("EXECUTOR_ROLE");

    // ============================================
    // STRUCTS
    // ============================================

    enum ProposalState {
        PENDING,
        ACTIVE,
        CLOSED,
        EXECUTED,
        CANCELLED
    }

    struct SnapshotProposal {
        string snapshotId;
        string ipfsHash;
        bytes32 merkleRoot;
        uint256 votingStart;
        uint256 votingEnd;
        uint256 forVotes;
        uint256 againstVotes;
        uint256 abstainVotes;
        ProposalState state;
        bool executed;
        address proposer;
    }

    struct ExecutionData {
        address target;
        uint256 value;
        bytes data;
        string description;
    }

    // ============================================
    // STATE VARIABLES
    // ============================================

    string public snapshotSpace;
    mapping(string => SnapshotProposal) public proposals;
    mapping(string => ExecutionData) public executionData;
    mapping(string => bool) public processedProposals;
    
    string[] public proposalIds;
    
    uint256 public minVotingPeriod = 3 days;
    uint256 public maxVotingPeriod = 14 days;
    uint256 public executionDelay = 2 days;
    
    // Snapshot API configuration
    string public graphqlEndpoint = "https://hub.snapshot.org/graphql";
    
    // ============================================
    // EVENTS
    // ============================================

    event ProposalCreated(
        string indexed snapshotId,
        string ipfsHash,
        address proposer
    );
    
    event ProposalClosed(
        string indexed snapshotId,
        uint256 forVotes,
        uint256 againstVotes,
        uint256 abstainVotes
    );
    
    event ProposalExecuted(
        string indexed snapshotId,
        address executor
    );
    
    event MerkleRootUpdated(
        string indexed snapshotId,
        bytes32 merkleRoot
    );
    
    event SnapshotSpaceUpdated(string newSpace);

    // ============================================
    // CONSTRUCTOR
    // ============================================

    constructor(string memory _snapshotSpace) {
        snapshotSpace = _snapshotSpace;
        
        _grantRole(DEFAULT_ADMIN_ROLE, msg.sender);
        _grantRole(RELAYER_ROLE, msg.sender);
        _grantRole(EXECUTOR_ROLE, msg.sender);
    }

    // ============================================
    // PROPOSAL MANAGEMENT
    // ============================================

    /**
     * @dev Create a new proposal linked to Snapshot
     */
    function createProposal(
        string memory _snapshotId,
        string memory _ipfsHash,
        uint256 _votingStart,
        uint256 _votingEnd,
        address _target,
        uint256 _value,
        bytes memory _data,
        string memory _description
    ) external onlyRole(RELAYER_ROLE) returns (bool) {
        require(bytes(proposals[_snapshotId].snapshotId).length == 0, "Proposal already exists");
        require(_votingEnd > _votingStart, "Invalid voting period");
        require(_votingEnd - _votingStart >= minVotingPeriod, "Voting period too short");
        require(_votingEnd - _votingStart <= maxVotingPeriod, "Voting period too long");
        
        proposals[_snapshotId] = SnapshotProposal({
            snapshotId: _snapshotId,
            ipfsHash: _ipfsHash,
            merkleRoot: bytes32(0),
            votingStart: _votingStart,
            votingEnd: _votingEnd,
            forVotes: 0,
            againstVotes: 0,
            abstainVotes: 0,
            state: ProposalState.ACTIVE,
            executed: false,
            proposer: msg.sender
        });
        
        executionData[_snapshotId] = ExecutionData({
            target: _target,
            value: _value,
            data: _data,
            description: _description
        });
        
        proposalIds.push(_snapshotId);
        
        emit ProposalCreated(_snapshotId, _ipfsHash, msg.sender);
        
        return true;
    }

    /**
     * @dev Close voting and submit results with Merkle root
     */
    function closeProposal(
        string memory _snapshotId,
        uint256 _forVotes,
        uint256 _againstVotes,
        uint256 _abstainVotes,
        bytes32 _merkleRoot
    ) external onlyRole(RELAYER_ROLE) {
        SnapshotProposal storage proposal = proposals[_snapshotId];
        require(proposal.state == ProposalState.ACTIVE, "Proposal not active");
        require(block.timestamp >= proposal.votingEnd, "Voting still active");
        
        proposal.forVotes = _forVotes;
        proposal.againstVotes = _againstVotes;
        proposal.abstainVotes = _abstainVotes;
        proposal.merkleRoot = _merkleRoot;
        proposal.state = ProposalState.CLOSED;
        
        emit ProposalClosed(_snapshotId, _forVotes, _againstVotes, _abstainVotes);
        emit MerkleRootUpdated(_snapshotId, _merkleRoot);
    }

    /**
     * @dev Execute proposal after delay
     */
    function executeProposal(
        string memory _snapshotId
    ) external onlyRole(EXECUTOR_ROLE) nonReentrant whenNotPaused {
        SnapshotProposal storage proposal = proposals[_snapshotId];
        require(proposal.state == ProposalState.CLOSED, "Proposal not closed");
        require(!proposal.executed, "Already executed");
        require(block.timestamp >= proposal.votingEnd + executionDelay, "Execution delay not met");
        require(proposal.forVotes > proposal.againstVotes, "Proposal did not pass");
        
        ExecutionData memory execData = executionData[_snapshotId];
        
        proposal.executed = true;
        proposal.state = ProposalState.EXECUTED;
        processedProposals[_snapshotId] = true;
        
        // Execute the transaction
        (bool success, ) = execData.target.call{value: execData.value}(execData.data);
        require(success, "Execution failed");
        
        emit ProposalExecuted(_snapshotId, msg.sender);
    }

    /**
     * @dev Verify a vote using Merkle proof
     */
    function verifyVote(
        string memory _snapshotId,
        address _voter,
        uint256 _votePower,
        uint8 _choice,
        bytes32[] memory _proof
    ) external view returns (bool) {
        SnapshotProposal storage proposal = proposals[_snapshotId];
        require(proposal.merkleRoot != bytes32(0), "Merkle root not set");
        
        bytes32 leaf = keccak256(abi.encodePacked(_voter, _votePower, _choice));
        return MerkleProof.verify(_proof, proposal.merkleRoot, leaf);
    }

    // ============================================
    // ADMIN FUNCTIONS
    // ============================================

    function updateSnapshotSpace(string memory _newSpace) external onlyRole(DEFAULT_ADMIN_ROLE) {
        snapshotSpace = _newSpace;
        emit SnapshotSpaceUpdated(_newSpace);
    }

    function setVotingPeriodLimits(uint256 _min, uint256 _max) external onlyRole(DEFAULT_ADMIN_ROLE) {
        require(_min < _max, "Invalid limits");
        minVotingPeriod = _min;
        maxVotingPeriod = _max;
    }

    function setExecutionDelay(uint256 _delay) external onlyRole(DEFAULT_ADMIN_ROLE) {
        executionDelay = _delay;
    }

    function setGraphqlEndpoint(string memory _endpoint) external onlyRole(DEFAULT_ADMIN_ROLE) {
        graphqlEndpoint = _endpoint;
    }

    function pause() external onlyRole(DEFAULT_ADMIN_ROLE) {
        _pause();
    }

    function unpause() external onlyRole(DEFAULT_ADMIN_ROLE) {
        _unpause();
    }

    function cancelProposal(string memory _snapshotId) external onlyRole(DEFAULT_ADMIN_ROLE) {
        SnapshotProposal storage proposal = proposals[_snapshotId];
        require(!proposal.executed, "Already executed");
        proposal.state = ProposalState.CANCELLED;
    }

    // ============================================
    // VIEW FUNCTIONS
    // ============================================

    function getProposal(string memory _snapshotId)
        external
        view
        returns (
            string memory snapshotId,
            string memory ipfsHash,
            uint256 votingStart,
            uint256 votingEnd,
            uint256 forVotes,
            uint256 againstVotes,
            uint256 abstainVotes,
            ProposalState state,
            bool executed
        )
    {
        SnapshotProposal storage proposal = proposals[_snapshotId];
        return (
            proposal.snapshotId,
            proposal.ipfsHash,
            proposal.votingStart,
            proposal.votingEnd,
            proposal.forVotes,
            proposal.againstVotes,
            proposal.abstainVotes,
            proposal.state,
            proposal.executed
        );
    }

    function getExecutionData(string memory _snapshotId)
        external
        view
        returns (
            address target,
            uint256 value,
            bytes memory data,
            string memory description
        )
    {
        ExecutionData storage execData = executionData[_snapshotId];
        return (
            execData.target,
            execData.value,
            execData.data,
            execData.description
        );
    }

    function getAllProposalIds() external view returns (string[] memory) {
        return proposalIds;
    }

    function getActiveProposals() external view returns (string[] memory) {
        uint256 activeCount = 0;
        
        // Count active proposals
        for (uint256 i = 0; i < proposalIds.length; i++) {
            if (proposals[proposalIds[i]].state == ProposalState.ACTIVE) {
                activeCount++;
            }
        }
        
        // Create array of active proposals
        string[] memory active = new string[](activeCount);
        uint256 index = 0;
        
        for (uint256 i = 0; i < proposalIds.length; i++) {
            if (proposals[proposalIds[i]].state == ProposalState.ACTIVE) {
                active[index] = proposalIds[i];
                index++;
            }
        }
        
        return active;
    }

    function canExecute(string memory _snapshotId) external view returns (bool) {
        SnapshotProposal storage proposal = proposals[_snapshotId];
        
        if (proposal.state != ProposalState.CLOSED) return false;
        if (proposal.executed) return false;
        if (block.timestamp < proposal.votingEnd + executionDelay) return false;
        if (proposal.forVotes <= proposal.againstVotes) return false;
        
        return true;
    }

    // ============================================
    // RECEIVE FUNCTION
    // ============================================

    receive() external payable {}
}
