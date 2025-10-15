// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/utils/cryptography/ECDSA.sol";
import "@openzeppelin/contracts/security/ReentrancyGuard.sol";

/**
 * @title MultiSigGovernance
 * @dev Multi-signature governance for critical proposals
 * Requires 3 of 5 signatures for execution
 */
contract MultiSigGovernance is Ownable, ReentrancyGuard {
    using ECDSA for bytes32;

    // ============================================
    // STATE VARIABLES
    // ============================================

    uint256 public constant REQUIRED_SIGNATURES = 3;
    uint256 public constant TOTAL_SIGNERS = 5;
    
    address[] public signers;
    mapping(address => bool) public isSigner;
    
    uint256 public proposalCount;
    
    // Proposal types requiring multi-sig
    enum ProposalType {
        TREASURY_WITHDRAWAL,
        PARAMETER_CHANGE,
        SIGNER_CHANGE,
        EMERGENCY_ACTION,
        CONTRACT_UPGRADE
    }
    
    enum ProposalStatus {
        PENDING,
        APPROVED,
        EXECUTED,
        REJECTED,
        EXPIRED
    }
    
    struct Proposal {
        uint256 id;
        ProposalType proposalType;
        address proposer;
        string title;
        string description;
        bytes data; // Encoded function call
        address target; // Contract to call
        uint256 value; // ETH value to send
        uint256 createdAt;
        uint256 expiresAt;
        ProposalStatus status;
        uint256 signatureCount;
        mapping(address => bool) hasSigned;
        address[] signedBy;
    }
    
    mapping(uint256 => Proposal) public proposals;
    
    // ============================================
    // EVENTS
    // ============================================

    event ProposalCreated(
        uint256 indexed proposalId,
        ProposalType proposalType,
        address indexed proposer,
        string title,
        address target,
        uint256 value
    );
    
    event ProposalSigned(
        uint256 indexed proposalId,
        address indexed signer,
        uint256 signatureCount
    );
    
    event ProposalExecuted(
        uint256 indexed proposalId,
        bool success,
        bytes returnData
    );
    
    event ProposalRejected(
        uint256 indexed proposalId,
        string reason
    );
    
    event SignerAdded(address indexed signer);
    event SignerRemoved(address indexed signer);

    // ============================================
    // MODIFIERS
    // ============================================

    modifier onlySigner() {
        require(isSigner[msg.sender], "Not a signer");
        _;
    }
    
    modifier proposalExists(uint256 proposalId) {
        require(proposalId < proposalCount, "Proposal does not exist");
        _;
    }
    
    modifier proposalPending(uint256 proposalId) {
        require(
            proposals[proposalId].status == ProposalStatus.PENDING,
            "Proposal not pending"
        );
        _;
    }

    // ============================================
    // CONSTRUCTOR
    // ============================================

    constructor(address[] memory _signers) {
        require(_signers.length == TOTAL_SIGNERS, "Must have exactly 5 signers");
        
        for (uint256 i = 0; i < _signers.length; i++) {
            require(_signers[i] != address(0), "Invalid signer address");
            require(!isSigner[_signers[i]], "Duplicate signer");
            
            signers.push(_signers[i]);
            isSigner[_signers[i]] = true;
        }
    }

    // ============================================
    // PROPOSAL CREATION
    // ============================================

    function createProposal(
        ProposalType _type,
        string memory _title,
        string memory _description,
        address _target,
        uint256 _value,
        bytes memory _data
    ) external onlySigner returns (uint256) {
        uint256 proposalId = proposalCount++;
        
        Proposal storage proposal = proposals[proposalId];
        proposal.id = proposalId;
        proposal.proposalType = _type;
        proposal.proposer = msg.sender;
        proposal.title = _title;
        proposal.description = _description;
        proposal.target = _target;
        proposal.value = _value;
        proposal.data = _data;
        proposal.createdAt = block.timestamp;
        proposal.expiresAt = block.timestamp + 7 days;
        proposal.status = ProposalStatus.PENDING;
        proposal.signatureCount = 0;
        
        emit ProposalCreated(
            proposalId,
            _type,
            msg.sender,
            _title,
            _target,
            _value
        );
        
        return proposalId;
    }

    // ============================================
    // SIGNATURE COLLECTION
    // ============================================

    function signProposal(uint256 proposalId)
        external
        onlySigner
        proposalExists(proposalId)
        proposalPending(proposalId)
    {
        Proposal storage proposal = proposals[proposalId];
        
        require(!proposal.hasSigned[msg.sender], "Already signed");
        require(block.timestamp < proposal.expiresAt, "Proposal expired");
        
        proposal.hasSigned[msg.sender] = true;
        proposal.signedBy.push(msg.sender);
        proposal.signatureCount++;
        
        emit ProposalSigned(proposalId, msg.sender, proposal.signatureCount);
        
        // Auto-approve if threshold reached
        if (proposal.signatureCount >= REQUIRED_SIGNATURES) {
            proposal.status = ProposalStatus.APPROVED;
        }
    }

    function revokeSignature(uint256 proposalId)
        external
        onlySigner
        proposalExists(proposalId)
        proposalPending(proposalId)
    {
        Proposal storage proposal = proposals[proposalId];
        
        require(proposal.hasSigned[msg.sender], "Not signed");
        require(proposal.status != ProposalStatus.EXECUTED, "Already executed");
        
        proposal.hasSigned[msg.sender] = false;
        proposal.signatureCount--;
        
        // Remove from signedBy array
        for (uint256 i = 0; i < proposal.signedBy.length; i++) {
            if (proposal.signedBy[i] == msg.sender) {
                proposal.signedBy[i] = proposal.signedBy[proposal.signedBy.length - 1];
                proposal.signedBy.pop();
                break;
            }
        }
        
        if (proposal.signatureCount < REQUIRED_SIGNATURES) {
            proposal.status = ProposalStatus.PENDING;
        }
    }

    // ============================================
    // EXECUTION
    // ============================================

    function executeProposal(uint256 proposalId)
        external
        onlySigner
        proposalExists(proposalId)
        nonReentrant
        returns (bool, bytes memory)
    {
        Proposal storage proposal = proposals[proposalId];
        
        require(
            proposal.status == ProposalStatus.APPROVED,
            "Proposal not approved"
        );
        require(
            proposal.signatureCount >= REQUIRED_SIGNATURES,
            "Insufficient signatures"
        );
        require(block.timestamp < proposal.expiresAt, "Proposal expired");
        
        proposal.status = ProposalStatus.EXECUTED;
        
        // Execute the proposal
        (bool success, bytes memory returnData) = proposal.target.call{
            value: proposal.value
        }(proposal.data);
        
        emit ProposalExecuted(proposalId, success, returnData);
        
        return (success, returnData);
    }

    // ============================================
    // ADMIN FUNCTIONS
    // ============================================

    function rejectProposal(uint256 proposalId, string memory reason)
        external
        onlyOwner
        proposalExists(proposalId)
    {
        Proposal storage proposal = proposals[proposalId];
        require(proposal.status == ProposalStatus.PENDING, "Not pending");
        
        proposal.status = ProposalStatus.REJECTED;
        emit ProposalRejected(proposalId, reason);
    }

    function expireProposal(uint256 proposalId)
        external
        proposalExists(proposalId)
    {
        Proposal storage proposal = proposals[proposalId];
        require(block.timestamp >= proposal.expiresAt, "Not expired yet");
        require(proposal.status == ProposalStatus.PENDING, "Not pending");
        
        proposal.status = ProposalStatus.EXPIRED;
    }

    function addSigner(address newSigner) external onlyOwner {
        require(newSigner != address(0), "Invalid address");
        require(!isSigner[newSigner], "Already a signer");
        require(signers.length < 10, "Max signers reached");
        
        signers.push(newSigner);
        isSigner[newSigner] = true;
        
        emit SignerAdded(newSigner);
    }

    function removeSigner(address signer) external onlyOwner {
        require(isSigner[signer], "Not a signer");
        require(signers.length > REQUIRED_SIGNATURES, "Cannot remove");
        
        isSigner[signer] = false;
        
        for (uint256 i = 0; i < signers.length; i++) {
            if (signers[i] == signer) {
                signers[i] = signers[signers.length - 1];
                signers.pop();
                break;
            }
        }
        
        emit SignerRemoved(signer);
    }

    // ============================================
    // VIEW FUNCTIONS
    // ============================================

    function getProposal(uint256 proposalId)
        external
        view
        proposalExists(proposalId)
        returns (
            uint256 id,
            ProposalType proposalType,
            address proposer,
            string memory title,
            string memory description,
            address target,
            uint256 value,
            uint256 createdAt,
            uint256 expiresAt,
            ProposalStatus status,
            uint256 signatureCount,
            address[] memory signedBy
        )
    {
        Proposal storage proposal = proposals[proposalId];
        
        return (
            proposal.id,
            proposal.proposalType,
            proposal.proposer,
            proposal.title,
            proposal.description,
            proposal.target,
            proposal.value,
            proposal.createdAt,
            proposal.expiresAt,
            proposal.status,
            proposal.signatureCount,
            proposal.signedBy
        );
    }

    function hasSigned(uint256 proposalId, address signer)
        external
        view
        proposalExists(proposalId)
        returns (bool)
    {
        return proposals[proposalId].hasSigned[signer];
    }

    function getSigners() external view returns (address[] memory) {
        return signers;
    }

    function getSignatureProgress(uint256 proposalId)
        external
        view
        proposalExists(proposalId)
        returns (uint256 current, uint256 required)
    {
        return (proposals[proposalId].signatureCount, REQUIRED_SIGNATURES);
    }

    function isProposalExecutable(uint256 proposalId)
        external
        view
        proposalExists(proposalId)
        returns (bool)
    {
        Proposal storage proposal = proposals[proposalId];
        
        return (
            proposal.status == ProposalStatus.APPROVED &&
            proposal.signatureCount >= REQUIRED_SIGNATURES &&
            block.timestamp < proposal.expiresAt
        );
    }

    // ============================================
    // RECEIVE ETH
    // ============================================

    receive() external payable {}
}
