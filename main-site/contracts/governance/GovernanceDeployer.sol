// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "../governance/TimelockController.sol";
import "../governance/GaslessVoting.sol";
import "../governance/MultiSigGovernance.sol";
import "../governance/QuadraticVoting.sol";
import "../governance/TreasuryManagement.sol";
import "../governance/ProposalTemplates.sol";
import "../governance/SnapshotBridge.sol";

/**
 * @title GovernanceDeployer
 * @dev Unified deployment script for all governance contracts
 */
contract GovernanceDeployer {
    
    struct DeployedContracts {
        address timelock;
        address gaslessVoting;
        address multiSig;
        address quadraticVoting;
        address treasury;
        address templates;
        address snapshotBridge;
        address governanceToken;
    }
    
    DeployedContracts public contracts;
    address public deployer;
    bool public deployed;
    
    event ContractsDeployed(
        address timelock,
        address gaslessVoting,
        address multiSig,
        address quadraticVoting,
        address treasury,
        address templates,
        address snapshotBridge
    );
    
    constructor() {
        deployer = msg.sender;
    }
    
    /**
     * @dev Deploy all governance contracts in correct order
     */
    function deployAll(
        address _governanceToken,
        address[] memory _initialSigners,
        string memory _snapshotSpace
    ) external returns (DeployedContracts memory) {
        require(msg.sender == deployer, "Only deployer");
        require(!deployed, "Already deployed");
        require(_initialSigners.length >= 3, "Need at least 3 signers");
        
        contracts.governanceToken = _governanceToken;
        
        // 1. Deploy Timelock (48 hour delay)
        address[] memory proposers = new address[](1);
        address[] memory executors = new address[](1);
        proposers[0] = deployer;
        executors[0] = address(0); // Anyone can execute
        
        TimelockController timelock = new TimelockController(
            2 days,
            proposers,
            executors,
            deployer
        );
        contracts.timelock = address(timelock);
        
        // 2. Deploy Gasless Voting
        GaslessVoting gaslessVoting = new GaslessVoting(
            _governanceToken,
            "Sphera Governance"
        );
        contracts.gaslessVoting = address(gaslessVoting);
        
        // 3. Deploy Multi-Signature
        MultiSigGovernance multiSig = new MultiSigGovernance(
            _initialSigners,
            3 // 3 of 5 required
        );
        contracts.multiSig = address(multiSig);
        
        // 4. Deploy Quadratic Voting
        QuadraticVoting quadraticVoting = new QuadraticVoting(_governanceToken);
        contracts.quadraticVoting = address(quadraticVoting);
        
        // 5. Deploy Treasury Management
        TreasuryManagement treasury = new TreasuryManagement();
        contracts.treasury = address(treasury);
        
        // 6. Deploy Proposal Templates
        ProposalTemplates templates = new ProposalTemplates();
        contracts.templates = address(templates);
        
        // 7. Deploy Snapshot Bridge
        SnapshotBridge snapshotBridge = new SnapshotBridge(_snapshotSpace);
        contracts.snapshotBridge = address(snapshotBridge);
        
        deployed = true;
        
        emit ContractsDeployed(
            contracts.timelock,
            contracts.gaslessVoting,
            contracts.multiSig,
            contracts.quadraticVoting,
            contracts.treasury,
            contracts.templates,
            contracts.snapshotBridge
        );
        
        return contracts;
    }
    
    /**
     * @dev Get all deployed contract addresses
     */
    function getDeployedContracts() external view returns (DeployedContracts memory) {
        require(deployed, "Not deployed yet");
        return contracts;
    }
    
    /**
     * @dev Transfer ownership of all contracts to DAO
     */
    function transferOwnershipToDAO(address _daoAddress) external {
        require(msg.sender == deployer, "Only deployer");
        require(deployed, "Not deployed yet");
        require(_daoAddress != address(0), "Invalid DAO address");
        
        // Transfer ownership of each contract
        // Note: Each contract must implement Ownable or similar
        
        // This is a placeholder - actual implementation depends on each contract's interface
        // In production, you'd call transferOwnership on each contract
    }
}
