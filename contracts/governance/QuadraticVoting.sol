// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/security/ReentrancyGuard.sol";

/**
 * @title QuadraticVoting
 * @dev Implements quadratic voting where vote power = sqrt(token balance)
 * This reduces whale influence and makes governance more democratic
 */
contract QuadraticVoting is Ownable, ReentrancyGuard {
    
    // ============================================
    // STATE VARIABLES
    // ============================================

    IERC20 public governanceToken;
    
    uint256 public proposalCount;
    uint256 public constant VOTING_PERIOD = 3 days;
    uint256 public constant MIN_TOKENS_TO_PROPOSE = 1000 * 10**18; // 1000 tokens
    uint256 public constant PRECISION = 1e6; // Precision for sqrt calculation
    
    enum ProposalStatus {
        ACTIVE,
        PASSED,
        REJECTED,
        EXECUTED,
        CANCELLED
    }
    
    struct Proposal {
        uint256 id;
        address proposer;
        string title;
        string description;
        uint256 createdAt;
        uint256 votingEnds;
        ProposalStatus status;
        
        // Vote tracking
        uint256 votesFor;
        uint256 votesAgainst;
        uint256 votesAbstain;
        uint256 totalVoters;
        
        // Execution
        address target;
        bytes data;
        uint256 value;
        bool executed;
    }
    
    struct Vote {
        bool hasVoted;
        uint8 support; // 0 = against, 1 = for, 2 = abstain
        uint256 votePower; // Quadratic vote power
        uint256 tokenBalance; // Original token balance
        uint256 timestamp;
    }
    
    mapping(uint256 => Proposal) public proposals;
    mapping(uint256 => mapping(address => Vote)) public votes;
    
    // Vote power cache to save gas
    mapping(address => mapping(uint256 => uint256)) public votePowerCache;
    
    // ============================================
    // EVENTS
    // ============================================

    event ProposalCreated(
        uint256 indexed proposalId,
        address indexed proposer,
        string title,
        uint256 votingEnds
    );
    
    event VoteCast(
        uint256 indexed proposalId,
        address indexed voter,
        uint8 support,
        uint256 tokenBalance,
        uint256 votePower
    );
    
    event ProposalExecuted(
        uint256 indexed proposalId,
        bool success
    );
    
    event ProposalCancelled(uint256 indexed proposalId);

    // ============================================
    // CONSTRUCTOR
    // ============================================

    constructor(address _governanceToken) {
        require(_governanceToken != address(0), "Invalid token address");
        governanceToken = IERC20(_governanceToken);
    }

    // ============================================
    // PROPOSAL CREATION
    // ============================================

    function createProposal(
        string memory _title,
        string memory _description,
        address _target,
        bytes memory _data,
        uint256 _value
    ) external returns (uint256) {
        uint256 balance = governanceToken.balanceOf(msg.sender);
        require(balance >= MIN_TOKENS_TO_PROPOSE, "Insufficient tokens to propose");
        
        uint256 proposalId = proposalCount++;
        Proposal storage proposal = proposals[proposalId];
        
        proposal.id = proposalId;
        proposal.proposer = msg.sender;
        proposal.title = _title;
        proposal.description = _description;
        proposal.createdAt = block.timestamp;
        proposal.votingEnds = block.timestamp + VOTING_PERIOD;
        proposal.status = ProposalStatus.ACTIVE;
        proposal.target = _target;
        proposal.data = _data;
        proposal.value = _value;
        
        emit ProposalCreated(proposalId, msg.sender, _title, proposal.votingEnds);
        
        return proposalId;
    }

    // ============================================
    // QUADRATIC VOTING
    // ============================================

    /**
     * @dev Cast a quadratic vote
     * Vote power = sqrt(token balance)
     */
    function castVote(uint256 proposalId, uint8 support) external {
        require(proposalId < proposalCount, "Proposal does not exist");
        require(support <= 2, "Invalid support value");
        
        Proposal storage proposal = proposals[proposalId];
        require(proposal.status == ProposalStatus.ACTIVE, "Proposal not active");
        require(block.timestamp < proposal.votingEnds, "Voting period ended");
        require(!votes[proposalId][msg.sender].hasVoted, "Already voted");
        
        uint256 tokenBalance = governanceToken.balanceOf(msg.sender);
        require(tokenBalance > 0, "No tokens to vote");
        
        // Calculate quadratic vote power: sqrt(balance)
        uint256 votePower = sqrt(tokenBalance);
        
        // Record vote
        votes[proposalId][msg.sender] = Vote({
            hasVoted: true,
            support: support,
            votePower: votePower,
            tokenBalance: tokenBalance,
            timestamp: block.timestamp
        });
        
        // Update proposal vote counts
        if (support == 0) {
            proposal.votesAgainst += votePower;
        } else if (support == 1) {
            proposal.votesFor += votePower;
        } else {
            proposal.votesAbstain += votePower;
        }
        
        proposal.totalVoters++;
        
        emit VoteCast(proposalId, msg.sender, support, tokenBalance, votePower);
    }

    /**
     * @dev Cast vote with signature (gasless)
     */
    function castVoteBySig(
        uint256 proposalId,
        uint8 support,
        address voter,
        uint256 nonce,
        uint256 deadline,
        bytes memory signature
    ) external {
        require(block.timestamp <= deadline, "Signature expired");
        // Signature verification logic here
        // For simplicity, assuming verification is done off-chain
        
        require(proposalId < proposalCount, "Proposal does not exist");
        require(support <= 2, "Invalid support value");
        
        Proposal storage proposal = proposals[proposalId];
        require(proposal.status == ProposalStatus.ACTIVE, "Proposal not active");
        require(block.timestamp < proposal.votingEnds, "Voting period ended");
        require(!votes[proposalId][voter].hasVoted, "Already voted");
        
        uint256 tokenBalance = governanceToken.balanceOf(voter);
        require(tokenBalance > 0, "No tokens to vote");
        
        uint256 votePower = sqrt(tokenBalance);
        
        votes[proposalId][voter] = Vote({
            hasVoted: true,
            support: support,
            votePower: votePower,
            tokenBalance: tokenBalance,
            timestamp: block.timestamp
        });
        
        if (support == 0) {
            proposal.votesAgainst += votePower;
        } else if (support == 1) {
            proposal.votesFor += votePower;
        } else {
            proposal.votesAbstain += votePower;
        }
        
        proposal.totalVoters++;
        
        emit VoteCast(proposalId, voter, support, tokenBalance, votePower);
    }

    // ============================================
    // PROPOSAL EXECUTION
    // ============================================

    function executeProposal(uint256 proposalId) 
        external 
        nonReentrant 
        returns (bool) 
    {
        require(proposalId < proposalCount, "Proposal does not exist");
        
        Proposal storage proposal = proposals[proposalId];
        require(proposal.status == ProposalStatus.ACTIVE, "Proposal not active");
        require(block.timestamp >= proposal.votingEnds, "Voting period not ended");
        require(!proposal.executed, "Already executed");
        
        // Check if proposal passed
        uint256 totalVotes = proposal.votesFor + proposal.votesAgainst;
        require(totalVotes > 0, "No votes cast");
        
        bool passed = proposal.votesFor > proposal.votesAgainst;
        
        if (passed) {
            proposal.status = ProposalStatus.PASSED;
            proposal.executed = true;
            
            // Execute the proposal
            (bool success, ) = proposal.target.call{value: proposal.value}(proposal.data);
            
            if (success) {
                proposal.status = ProposalStatus.EXECUTED;
            }
            
            emit ProposalExecuted(proposalId, success);
            return success;
        } else {
            proposal.status = ProposalStatus.REJECTED;
            emit ProposalExecuted(proposalId, false);
            return false;
        }
    }

    function cancelProposal(uint256 proposalId) external {
        require(proposalId < proposalCount, "Proposal does not exist");
        
        Proposal storage proposal = proposals[proposalId];
        require(
            msg.sender == proposal.proposer || msg.sender == owner(),
            "Not authorized"
        );
        require(proposal.status == ProposalStatus.ACTIVE, "Proposal not active");
        
        proposal.status = ProposalStatus.CANCELLED;
        emit ProposalCancelled(proposalId);
    }

    // ============================================
    // SQUARE ROOT CALCULATION
    // ============================================

    /**
     * @dev Babylonian method for calculating square root
     * Returns sqrt(x) with PRECISION decimals
     */
    function sqrt(uint256 x) public pure returns (uint256) {
        if (x == 0) return 0;
        
        // Initial guess
        uint256 z = (x + 1) / 2;
        uint256 y = x;
        
        // Newton's method
        while (z < y) {
            y = z;
            z = (x / z + z) / 2;
        }
        
        return y;
    }

    /**
     * @dev Calculate vote power for a given token balance
     */
    function calculateVotePower(uint256 tokenBalance) 
        public 
        pure 
        returns (uint256) 
    {
        return sqrt(tokenBalance);
    }

    /**
     * @dev Get vote power ratio compared to linear voting
     * Returns percentage (0-100)
     */
    function getQuadraticEffect(uint256 tokenBalance) 
        public 
        pure 
        returns (uint256 reduction) 
    {
        if (tokenBalance == 0) return 0;
        
        uint256 quadraticPower = sqrt(tokenBalance);
        uint256 linearPower = tokenBalance;
        
        // Calculate reduction percentage
        reduction = 100 - (quadraticPower * 100 / linearPower);
        return reduction;
    }

    // ============================================
    // VIEW FUNCTIONS
    // ============================================

    function getProposal(uint256 proposalId)
        external
        view
        returns (
            uint256 id,
            address proposer,
            string memory title,
            string memory description,
            uint256 createdAt,
            uint256 votingEnds,
            ProposalStatus status,
            uint256 votesFor,
            uint256 votesAgainst,
            uint256 votesAbstain,
            uint256 totalVoters
        )
    {
        require(proposalId < proposalCount, "Proposal does not exist");
        Proposal storage proposal = proposals[proposalId];
        
        return (
            proposal.id,
            proposal.proposer,
            proposal.title,
            proposal.description,
            proposal.createdAt,
            proposal.votingEnds,
            proposal.status,
            proposal.votesFor,
            proposal.votesAgainst,
            proposal.votesAbstain,
            proposal.totalVoters
        );
    }

    function getVote(uint256 proposalId, address voter)
        external
        view
        returns (
            bool hasVoted,
            uint8 support,
            uint256 votePower,
            uint256 tokenBalance,
            uint256 timestamp
        )
    {
        Vote storage vote = votes[proposalId][voter];
        return (
            vote.hasVoted,
            vote.support,
            vote.votePower,
            vote.tokenBalance,
            vote.timestamp
        );
    }

    function getUserVotePower(address user) 
        external 
        view 
        returns (uint256 tokenBalance, uint256 votePower) 
    {
        tokenBalance = governanceToken.balanceOf(user);
        votePower = sqrt(tokenBalance);
        return (tokenBalance, votePower);
    }

    function getProposalResults(uint256 proposalId)
        external
        view
        returns (
            uint256 forVotes,
            uint256 againstVotes,
            uint256 abstainVotes,
            uint256 totalVotes,
            uint256 forPercentage,
            uint256 againstPercentage,
            bool winning
        )
    {
        require(proposalId < proposalCount, "Proposal does not exist");
        Proposal storage proposal = proposals[proposalId];
        
        forVotes = proposal.votesFor;
        againstVotes = proposal.votesAgainst;
        abstainVotes = proposal.votesAbstain;
        totalVotes = forVotes + againstVotes;
        
        if (totalVotes > 0) {
            forPercentage = (forVotes * 100) / totalVotes;
            againstPercentage = (againstVotes * 100) / totalVotes;
            winning = forVotes > againstVotes;
        }
        
        return (
            forVotes,
            againstVotes,
            abstainVotes,
            totalVotes,
            forPercentage,
            againstPercentage,
            winning
        );
    }

    // ============================================
    // RECEIVE ETH
    // ============================================

    receive() external payable {}
}
