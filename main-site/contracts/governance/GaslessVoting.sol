// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/utils/cryptography/ECDSA.sol";
import "@openzeppelin/contracts/utils/cryptography/EIP712.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

/**
 * @title GaslessVoting
 * @dev Implements gasless voting using EIP-712 signatures and meta-transactions
 * @notice Users sign votes off-chain, relayer submits on-chain
 */
contract GaslessVoting is EIP712, Ownable {
    using ECDSA for bytes32;

    // ============================================
    // STRUCTS & TYPES
    // ============================================

    struct Vote {
        uint256 proposalId;
        uint8 support; // 0 = Against, 1 = For, 2 = Abstain
        address voter;
        uint256 nonce;
        uint256 deadline;
    }

    struct Proposal {
        uint256 id;
        uint256 forVotes;
        uint256 againstVotes;
        uint256 abstainVotes;
        bool executed;
        uint256 endTime;
    }

    // ============================================
    // STATE VARIABLES
    // ============================================

    // EIP-712 typehash for Vote struct
    bytes32 public constant VOTE_TYPEHASH = keccak256(
        "Vote(uint256 proposalId,uint8 support,address voter,uint256 nonce,uint256 deadline)"
    );

    // Nonce tracking for replay protection
    mapping(address => uint256) public nonces;

    // Voter tracking per proposal
    mapping(uint256 => mapping(address => bool)) public hasVoted;

    // Proposals storage
    mapping(uint256 => Proposal) public proposals;

    // Relayer whitelist (addresses allowed to submit votes)
    mapping(address => bool) public relayers;

    // Vote weight per address (could be token balance)
    mapping(address => uint256) public voteWeight;

    // Total proposals count
    uint256 public proposalCount;

    // ============================================
    // EVENTS
    // ============================================

    event VoteCast(
        address indexed voter,
        uint256 indexed proposalId,
        uint8 support,
        uint256 weight,
        string reason
    );

    event VoteCastWithSig(
        address indexed voter,
        uint256 indexed proposalId,
        uint8 support,
        uint256 weight,
        address indexed relayer
    );

    event ProposalCreated(
        uint256 indexed proposalId,
        uint256 endTime
    );

    event RelayerAdded(address indexed relayer);
    event RelayerRemoved(address indexed relayer);

    // ============================================
    // ERRORS
    // ============================================

    error InvalidSignature();
    error SignatureExpired();
    error AlreadyVoted();
    error ProposalNotActive();
    error InvalidSupport();
    error NotRelayer();
    error InvalidNonce();

    // ============================================
    // CONSTRUCTOR
    // ============================================

    constructor() EIP712("Sphera Governance", "1") Ownable(msg.sender) {
        // Set deployer as first relayer
        relayers[msg.sender] = true;
    }

    // ============================================
    // RELAYER MANAGEMENT
    // ============================================

    function addRelayer(address relayer) external onlyOwner {
        relayers[relayer] = true;
        emit RelayerAdded(relayer);
    }

    function removeRelayer(address relayer) external onlyOwner {
        relayers[relayer] = false;
        emit RelayerRemoved(relayer);
    }

    modifier onlyRelayer() {
        if (!relayers[msg.sender]) revert NotRelayer();
        _;
    }

    // ============================================
    // PROPOSAL MANAGEMENT
    // ============================================

    function createProposal(uint256 duration) external onlyOwner returns (uint256) {
        proposalCount++;
        uint256 proposalId = proposalCount;

        proposals[proposalId] = Proposal({
            id: proposalId,
            forVotes: 0,
            againstVotes: 0,
            abstainVotes: 0,
            executed: false,
            endTime: block.timestamp + duration
        });

        emit ProposalCreated(proposalId, block.timestamp + duration);
        return proposalId;
    }

    // ============================================
    // VOTE WEIGHT MANAGEMENT
    // ============================================

    function setVoteWeight(address voter, uint256 weight) external onlyOwner {
        voteWeight[voter] = weight;
    }

    function setVoteWeightBatch(address[] calldata voters, uint256[] calldata weights) external onlyOwner {
        require(voters.length == weights.length, "Length mismatch");
        for (uint256 i = 0; i < voters.length; i++) {
            voteWeight[voters[i]] = weights[i];
        }
    }

    // ============================================
    // VOTING - TRADITIONAL (WITH GAS)
    // ============================================

    function castVote(uint256 proposalId, uint8 support) external {
        _castVote(msg.sender, proposalId, support);
        emit VoteCast(msg.sender, proposalId, support, voteWeight[msg.sender], "");
    }

    function castVoteWithReason(
        uint256 proposalId,
        uint8 support,
        string calldata reason
    ) external {
        _castVote(msg.sender, proposalId, support);
        emit VoteCast(msg.sender, proposalId, support, voteWeight[msg.sender], reason);
    }

    // ============================================
    // VOTING - GASLESS (WITH SIGNATURE)
    // ============================================

    /**
     * @dev Cast vote using EIP-712 signature (gasless for user)
     * @param vote Vote struct containing vote details
     * @param signature EIP-712 signature from voter
     */
    function castVoteBySig(
        Vote memory vote,
        bytes memory signature
    ) external onlyRelayer {
        // Verify deadline
        if (block.timestamp > vote.deadline) {
            revert SignatureExpired();
        }

        // Verify nonce
        if (vote.nonce != nonces[vote.voter]) {
            revert InvalidNonce();
        }

        // Verify signature
        bytes32 structHash = keccak256(
            abi.encode(
                VOTE_TYPEHASH,
                vote.proposalId,
                vote.support,
                vote.voter,
                vote.nonce,
                vote.deadline
            )
        );

        bytes32 digest = _hashTypedDataV4(structHash);
        address signer = ECDSA.recover(digest, signature);

        if (signer != vote.voter) {
            revert InvalidSignature();
        }

        // Increment nonce to prevent replay
        nonces[vote.voter]++;

        // Cast vote
        _castVote(vote.voter, vote.proposalId, vote.support);

        emit VoteCastWithSig(
            vote.voter,
            vote.proposalId,
            vote.support,
            voteWeight[vote.voter],
            msg.sender // relayer
        );
    }

    /**
     * @dev Batch gasless voting (multiple votes in one transaction)
     * @param votes Array of Vote structs
     * @param signatures Array of signatures matching votes
     */
    function castVoteBySigBatch(
        Vote[] memory votes,
        bytes[] memory signatures
    ) external onlyRelayer {
        require(votes.length == signatures.length, "Length mismatch");
        
        for (uint256 i = 0; i < votes.length; i++) {
            // Use try-catch to continue on error
            try this.castVoteBySig(votes[i], signatures[i]) {
                // Success
            } catch {
                // Skip invalid votes
                continue;
            }
        }
    }

    // ============================================
    // INTERNAL VOTE LOGIC
    // ============================================

    function _castVote(
        address voter,
        uint256 proposalId,
        uint8 support
    ) internal {
        Proposal storage proposal = proposals[proposalId];

        // Validate proposal is active
        if (block.timestamp > proposal.endTime) {
            revert ProposalNotActive();
        }

        // Validate support value
        if (support > 2) {
            revert InvalidSupport();
        }

        // Check if already voted
        if (hasVoted[proposalId][voter]) {
            revert AlreadyVoted();
        }

        // Mark as voted
        hasVoted[proposalId][voter] = true;

        // Get vote weight
        uint256 weight = voteWeight[voter];
        if (weight == 0) {
            weight = 1; // Default weight
        }

        // Record vote
        if (support == 0) {
            proposal.againstVotes += weight;
        } else if (support == 1) {
            proposal.forVotes += weight;
        } else if (support == 2) {
            proposal.abstainVotes += weight;
        }
    }

    // ============================================
    // VIEW FUNCTIONS
    // ============================================

    function getProposal(uint256 proposalId) external view returns (
        uint256 id,
        uint256 forVotes,
        uint256 againstVotes,
        uint256 abstainVotes,
        bool executed,
        uint256 endTime,
        bool isActive
    ) {
        Proposal memory proposal = proposals[proposalId];
        return (
            proposal.id,
            proposal.forVotes,
            proposal.againstVotes,
            proposal.abstainVotes,
            proposal.executed,
            proposal.endTime,
            block.timestamp <= proposal.endTime
        );
    }

    function hasVotedOnProposal(uint256 proposalId, address voter) external view returns (bool) {
        return hasVoted[proposalId][voter];
    }

    function getNonce(address voter) external view returns (uint256) {
        return nonces[voter];
    }

    /**
     * @dev Get the EIP-712 domain separator
     */
    function DOMAIN_SEPARATOR() external view returns (bytes32) {
        return _domainSeparatorV4();
    }

    /**
     * @dev Generate the hash that should be signed for gasless voting
     */
    function getVoteHash(Vote memory vote) public view returns (bytes32) {
        bytes32 structHash = keccak256(
            abi.encode(
                VOTE_TYPEHASH,
                vote.proposalId,
                vote.support,
                vote.voter,
                vote.nonce,
                vote.deadline
            )
        );
        return _hashTypedDataV4(structHash);
    }

    /**
     * @dev Verify a vote signature without submitting
     */
    function verifyVoteSignature(
        Vote memory vote,
        bytes memory signature
    ) external view returns (bool) {
        bytes32 digest = getVoteHash(vote);
        address signer = ECDSA.recover(digest, signature);
        return signer == vote.voter;
    }

    // ============================================
    // UTILITY FUNCTIONS
    // ============================================

    function getProposalResults(uint256 proposalId) external view returns (
        uint256 forVotes,
        uint256 againstVotes,
        uint256 abstainVotes,
        uint256 totalVotes
    ) {
        Proposal memory proposal = proposals[proposalId];
        uint256 total = proposal.forVotes + proposal.againstVotes + proposal.abstainVotes;
        
        return (
            proposal.forVotes,
            proposal.againstVotes,
            proposal.abstainVotes,
            total
        );
    }

    function isProposalActive(uint256 proposalId) external view returns (bool) {
        return block.timestamp <= proposals[proposalId].endTime;
    }
}
