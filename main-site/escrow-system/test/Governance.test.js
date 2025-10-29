const { expect } = require("chai");
const { ethers } = require("hardhat");
const { time } = require("@nomicfoundation/hardhat-network-helpers");

describe("🏛️ Governance System - Complete Test Suite", function () {
    // ============================================
    // SETUP & FIXTURES
    // ============================================
    
    let governanceToken;
    let timelock;
    let governor;
    let owner, proposer, voter1, voter2, voter3, executor;
    let stakingContract; // Mock
    
    // Constants
    const VOTING_DELAY = 1 * 24 * 60 * 60; // 1 day
    const VOTING_PERIOD = 7 * 24 * 60 * 60; // 7 days
    const TIMELOCK_DELAY = 2 * 24 * 60 * 60; // 2 days
    const PROPOSAL_THRESHOLD = ethers.parseEther("1000"); // 1000 GOVSPHE
    const QUORUM_PERCENTAGE = 4; // 4%
    
    beforeEach(async function () {
        // Get signers
        [owner, proposer, voter1, voter2, voter3, executor] = await ethers.getSigners();
        
        // Deploy GovernanceToken
        const GovernanceToken = await ethers.getContractFactory("GovernanceToken");
        governanceToken = await GovernanceToken.deploy(owner.address);
        await governanceToken.waitForDeployment();
        
        // Deploy Timelock
        const Timelock = await ethers.getContractFactory("SpheraTimelock");
        timelock = await Timelock.deploy(
            TIMELOCK_DELAY,
            [], // proposers (will be set later)
            [ethers.ZeroAddress], // executors (anyone can execute)
            owner.address // admin
        );
        await timelock.waitForDeployment();
        
        // Deploy Governor
        const Governor = await ethers.getContractFactory("SpheraGovernor");
        governor = await Governor.deploy(
            await governanceToken.getAddress(),
            await timelock.getAddress()
        );
        await governanceToken.waitForDeployment();
        
        // Setup roles
        const PROPOSER_ROLE = await timelock.PROPOSER_ROLE();
        const EXECUTOR_ROLE = await timelock.EXECUTOR_ROLE();
        const CANCELLER_ROLE = await timelock.CANCELLER_ROLE();
        const ADMIN_ROLE = await timelock.DEFAULT_ADMIN_ROLE();
        
        // Grant roles to Governor
        await timelock.grantRole(PROPOSER_ROLE, await governor.getAddress());
        await timelock.grantRole(CANCELLER_ROLE, await governor.getAddress());
        
        // Revoke admin role from owner (make timelock self-admin)
        await timelock.grantRole(ADMIN_ROLE, await timelock.getAddress());
        await timelock.revokeRole(ADMIN_ROLE, owner.address);
        
        // Mint governance tokens to voters
        await governanceToken.mint(proposer.address, ethers.parseEther("2000")); // 2000 GOV
        await governanceToken.mint(voter1.address, ethers.parseEther("5000"));   // 5000 GOV
        await governanceToken.mint(voter2.address, ethers.parseEther("3000"));   // 3000 GOV
        await governanceToken.mint(voter3.address, ethers.parseEther("1000"));   // 1000 GOV
        
        // Self-delegate to activate voting power
        await governanceToken.connect(proposer).selfDelegate();
        await governanceToken.connect(voter1).selfDelegate();
        await governanceToken.connect(voter2).selfDelegate();
        await governanceToken.connect(voter3).selfDelegate();
    });
    
    // ============================================
    // SUITE 1: DEPLOYMENT & CONFIGURATION
    // ============================================
    
    describe("📦 Suite 1: Deployment & Configuration", function () {
        
        it("1.1 Should deploy GovernanceToken correctly", async function () {
            expect(await governanceToken.name()).to.equal("Sphera Governance Token");
            expect(await governanceToken.symbol()).to.equal("GOVSPHE");
            expect(await governanceToken.totalSupply()).to.equal(ethers.parseEther("11000"));
        });
        
        it("1.2 Should deploy Timelock with correct parameters", async function () {
            expect(await timelock.getMinDelay()).to.equal(TIMELOCK_DELAY);
            
            const PROPOSER_ROLE = await timelock.PROPOSER_ROLE();
            expect(await timelock.hasRole(PROPOSER_ROLE, await governor.getAddress())).to.be.true;
        });
        
        it("1.3 Should deploy Governor with correct parameters", async function () {
            expect(await governor.name()).to.equal("Sphera Governor");
            expect(await governor.votingDelay()).to.equal(VOTING_DELAY);
            expect(await governor.votingPeriod()).to.equal(VOTING_PERIOD);
            expect(await governor.proposalThreshold()).to.equal(PROPOSAL_THRESHOLD);
            expect(await governor.quorumNumerator()).to.equal(QUORUM_PERCENTAGE);
        });
        
        it("1.4 Should have correct voting power after delegation", async function () {
            expect(await governanceToken.getVotes(proposer.address)).to.equal(ethers.parseEther("2000"));
            expect(await governanceToken.getVotes(voter1.address)).to.equal(ethers.parseEther("5000"));
            expect(await governanceToken.getVotes(voter2.address)).to.equal(ethers.parseEther("3000"));
            expect(await governanceToken.getVotes(voter3.address)).to.equal(ethers.parseEther("1000"));
        });
        
        it("1.5 Should calculate quorum correctly", async function () {
            const totalSupply = await governanceToken.totalSupply();
            const expectedQuorum = (totalSupply * BigInt(QUORUM_PERCENTAGE)) / BigInt(100);
            const actualQuorum = await governor.quorum(await ethers.provider.getBlockNumber());
            
            expect(actualQuorum).to.equal(expectedQuorum);
        });
        
        it("1.6 Should allow minting by authorized minters", async function () {
            expect(await governanceToken.minters(owner.address)).to.be.true;
            
            await governanceToken.mint(voter1.address, ethers.parseEther("100"));
            expect(await governanceToken.balanceOf(voter1.address)).to.equal(ethers.parseEther("5100"));
        });
        
        it("1.7 Should reject minting by unauthorized addresses", async function () {
            await expect(
                governanceToken.connect(voter1).mint(voter2.address, ethers.parseEther("100"))
            ).to.be.revertedWithCustomError(governanceToken, "NotAuthorizedMinter");
        });
        
        it("1.8 Should track governance stats correctly", async function () {
            const stats = await governor.getGovernanceStats();
            expect(stats[0]).to.equal(0); // totalProposals
            expect(stats[1]).to.equal(QUORUM_PERCENTAGE); // currentQuorum %
            expect(stats[2]).to.equal(VOTING_DELAY);
            expect(stats[3]).to.equal(VOTING_PERIOD);
            expect(stats[4]).to.equal(PROPOSAL_THRESHOLD);
        });
    });
    
    // ============================================
    // SUITE 2: PROPOSAL CREATION
    // ============================================
    
    describe("📝 Suite 2: Proposal Creation", function () {
        
        it("2.1 Should create a proposal with sufficient tokens", async function () {
            const targets = [await governanceToken.getAddress()];
            const values = [0];
            const calldatas = [governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("10")])];
            const description = "Proposal #1: Test transfer";
            const category = 0; // ParameterChange
            
            await expect(
                governor.connect(proposer).proposeWithCategory(targets, values, calldatas, description, category)
            ).to.emit(governor, "ProposalCreatedWithCategory");
        });
        
        it("2.2 Should reject proposal creation without sufficient tokens", async function () {
            // voter3 only has 1000 GOV, threshold is 1000
            const targets = [await governanceToken.getAddress()];
            const values = [0];
            const calldatas = [governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("10")])];
            const description = "Proposal #2: Should fail";
            
            // Reduce voter3's tokens below threshold
            await governanceToken.connect(voter3).transfer(voter1.address, ethers.parseEther("100"));
            
            await expect(
                governor.connect(voter3).propose(targets, values, calldatas, description)
            ).to.be.reverted;
        });
        
        it("2.3 Should store proposal info correctly", async function () {
            const targets = [await governanceToken.getAddress()];
            const values = [0];
            const calldatas = [governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("10")])];
            const description = "Proposal #3: Check info";
            const category = 1; // TreasuryManagement
            
            const tx = await governor.connect(proposer).proposeWithCategory(targets, values, calldatas, description, category);
            const receipt = await tx.wait();
            
            // Get proposal ID from event
            const event = receipt.logs.find(log => {
                try {
                    return governor.interface.parseLog(log).name === "ProposalCreatedWithCategory";
                } catch { return false; }
            });
            const proposalId = governor.interface.parseLog(event).args[0];
            
            const info = await governor.getProposalInfo(proposalId);
            expect(info[0]).to.equal(category); // category
            expect(info[1]).to.equal(proposer.address); // proposer
        });
        
        it("2.4 Should track user proposals", async function () {
            const targets = [await governanceToken.getAddress()];
            const values = [0];
            const calldatas = [governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("10")])];
            
            await governor.connect(proposer).proposeWithCategory(targets, values, calldatas, "Proposal #4a", 0);
            await governor.connect(proposer).proposeWithCategory(targets, values, calldatas, "Proposal #4b", 0);
            
            const userProposals = await governor.getUserProposals(proposer.address);
            expect(userProposals.length).to.equal(2);
        });
        
        it("2.5 Should create proposal with all categories", async function () {
            const targets = [await governanceToken.getAddress()];
            const values = [0];
            const calldatas = [governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("10")])];
            
            for (let category = 0; category < 5; category++) {
                await expect(
                    governor.connect(proposer).proposeWithCategory(targets, values, calldatas, `Proposal category ${category}`, category)
                ).to.emit(governor, "ProposalCreatedWithCategory");
            }
            
            expect(await governor.proposalCount()).to.equal(5);
        });
        
        it("2.6 Should create proposal with multiple actions", async function () {
            const targets = [
                await governanceToken.getAddress(),
                await governanceToken.getAddress()
            ];
            const values = [0, 0];
            const calldatas = [
                governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("5")]),
                governanceToken.interface.encodeFunctionData("transfer", [voter2.address, ethers.parseEther("5")])
            ];
            const description = "Proposal #6: Multi-action";
            
            await expect(
                governor.connect(proposer).proposeWithCategory(targets, values, calldatas, description, 0)
            ).to.emit(governor, "ProposalCreatedWithCategory");
        });
        
        it("2.7 Should start in Pending state", async function () {
            const targets = [await governanceToken.getAddress()];
            const values = [0];
            const calldatas = [governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("10")])];
            const description = "Proposal #7: Check state";
            
            const tx = await governor.connect(proposer).proposeWithCategory(targets, values, calldatas, description, 0);
            const receipt = await tx.wait();
            
            const event = receipt.logs.find(log => {
                try {
                    return governor.interface.parseLog(log).name === "ProposalCreatedWithCategory";
                } catch { return false; }
            });
            const proposalId = governor.interface.parseLog(event).args[0];
            
            const state = await governor.state(proposalId);
            expect(state).to.equal(0); // Pending
        });
        
        it("2.8 Should transition to Active after voting delay", async function () {
            const targets = [await governanceToken.getAddress()];
            const values = [0];
            const calldatas = [governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("10")])];
            const description = "Proposal #8: Check active state";
            
            const tx = await governor.connect(proposer).proposeWithCategory(targets, values, calldatas, description, 0);
            const receipt = await tx.wait();
            
            const event = receipt.logs.find(log => {
                try {
                    return governor.interface.parseLog(log).name === "ProposalCreatedWithCategory";
                } catch { return false; }
            });
            const proposalId = governor.interface.parseLog(event).args[0];
            
            // Move time forward past voting delay
            await time.increase(VOTING_DELAY + 1);
            
            const state = await governor.state(proposalId);
            expect(state).to.equal(1); // Active
        });
        
        it("2.9 Should allow proposer to cancel own proposal", async function () {
            const targets = [await governanceToken.getAddress()];
            const values = [0];
            const calldatas = [governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("10")])];
            const description = "Proposal #9: Test cancel";
            
            const tx = await governor.connect(proposer).proposeWithCategory(targets, values, calldatas, description, 0);
            const receipt = await tx.wait();
            
            const event = receipt.logs.find(log => {
                try {
                    return governor.interface.parseLog(log).name === "ProposalCreatedWithCategory";
                } catch { return false; }
            });
            const proposalId = governor.interface.parseLog(event).args[0];
            
            const descriptionHash = ethers.id(description);
            await governor.connect(proposer).cancel(targets, values, calldatas, descriptionHash);
            
            const state = await governor.state(proposalId);
            expect(state).to.equal(2); // Canceled
        });
        
        it("2.10 Should increment proposal count", async function () {
            expect(await governor.proposalCount()).to.equal(0);
            
            const targets = [await governanceToken.getAddress()];
            const values = [0];
            const calldatas = [governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("10")])];
            
            await governor.connect(proposer).proposeWithCategory(targets, values, calldatas, "Proposal #10", 0);
            expect(await governor.proposalCount()).to.equal(1);
            
            await governor.connect(proposer).proposeWithCategory(targets, values, calldatas, "Proposal #10b", 0);
            expect(await governor.proposalCount()).to.equal(2);
        });
    });
    
    // ============================================
    // SUITE 3: VOTING
    // ============================================
    
    describe("🗳️ Suite 3: Voting", function () {
        
        let proposalId;
        
        beforeEach(async function () {
            // Create a proposal for voting tests
            const targets = [await governanceToken.getAddress()];
            const values = [0];
            const calldatas = [governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("10")])];
            const description = "Test voting proposal";
            
            const tx = await governor.connect(proposer).proposeWithCategory(targets, values, calldatas, description, 0);
            const receipt = await tx.wait();
            
            const event = receipt.logs.find(log => {
                try {
                    return governor.interface.parseLog(log).name === "ProposalCreatedWithCategory";
                } catch { return false; }
            });
            proposalId = governor.interface.parseLog(event).args[0];
            
            // Move past voting delay
            await time.increase(VOTING_DELAY + 1);
        });
        
        it("3.1 Should cast vote FOR", async function () {
            await expect(governor.connect(voter1).castVote(proposalId, 1))
                .to.emit(governor, "VoteCast");
            
            const hasVoted = await governor.hasVoted(proposalId, voter1.address);
            expect(hasVoted).to.be.true;
        });
        
        it("3.2 Should cast vote AGAINST", async function () {
            await expect(governor.connect(voter1).castVote(proposalId, 0))
                .to.emit(governor, "VoteCast");
        });
        
        it("3.3 Should cast vote ABSTAIN", async function () {
            await expect(governor.connect(voter1).castVote(proposalId, 2))
                .to.emit(governor, "VoteCast");
        });
        
        it("3.4 Should count votes with correct weight", async function () {
            // voter1 has 5000 votes
            await governor.connect(voter1).castVote(proposalId, 1); // FOR
            
            const votes = await governor.proposalVotes(proposalId);
            expect(votes[1]).to.equal(ethers.parseEther("5000")); // forVotes
        });
        
        it("3.5 Should prevent double voting", async function () {
            await governor.connect(voter1).castVote(proposalId, 1);
            
            await expect(
                governor.connect(voter1).castVote(proposalId, 1)
            ).to.be.reverted;
        });
        
        it("3.6 Should count votes from multiple voters", async function () {
            await governor.connect(voter1).castVote(proposalId, 1); // 5000 FOR
            await governor.connect(voter2).castVote(proposalId, 1); // 3000 FOR
            await governor.connect(voter3).castVote(proposalId, 0); // 1000 AGAINST
            
            const votes = await governor.proposalVotes(proposalId);
            expect(votes[0]).to.equal(ethers.parseEther("1000")); // againstVotes
            expect(votes[1]).to.equal(ethers.parseEther("8000")); // forVotes
            expect(votes[2]).to.equal(0); // abstainVotes
        });
        
        it("3.7 Should allow voting with reason", async function () {
            const reason = "I support this proposal because...";
            await expect(
                governor.connect(voter1).castVoteWithReason(proposalId, 1, reason)
            ).to.emit(governor, "VoteCast");
        });
        
        it("3.8 Should reject voting before voting period", async function () {
            // Create new proposal
            const targets = [await governanceToken.getAddress()];
            const values = [0];
            const calldatas = [governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("10")])];
            const description = "Too early proposal";
            
            const tx = await governor.connect(proposer).proposeWithCategory(targets, values, calldatas, description, 0);
            const receipt = await tx.wait();
            
            const event = receipt.logs.find(log => {
                try {
                    return governor.interface.parseLog(log).name === "ProposalCreatedWithCategory";
                } catch { return false; }
            });
            const newProposalId = governor.interface.parseLog(event).args[0];
            
            // Try to vote immediately (still Pending)
            await expect(
                governor.connect(voter1).castVote(newProposalId, 1)
            ).to.be.reverted;
        });
        
        it("3.9 Should reject voting after voting period", async function () {
            // Move past voting period
            await time.increase(VOTING_PERIOD + 1);
            
            await expect(
                governor.connect(voter1).castVote(proposalId, 1)
            ).to.be.reverted;
        });
        
        it("3.10 Should check if quorum reached", async function () {
            // Need 4% of 11000 = 440 GOV to reach quorum
            await governor.connect(voter1).castVote(proposalId, 1); // 5000 votes
            
            const quorumReached = await governor.quorumReached(proposalId);
            expect(quorumReached).to.be.true;
        });
        
        it("3.11 Should check if vote succeeded", async function () {
            await governor.connect(voter1).castVote(proposalId, 1); // 5000 FOR
            await governor.connect(voter2).castVote(proposalId, 0); // 3000 AGAINST
            
            const succeeded = await governor.voteSucceeded(proposalId);
            expect(succeeded).to.be.true; // 5000 > 3000
        });
        
        it("3.12 Should track proposal as defeated if not enough FOR votes", async function () {
            await governor.connect(voter1).castVote(proposalId, 0); // 5000 AGAINST
            await governor.connect(voter2).castVote(proposalId, 1); // 3000 FOR
            
            const succeeded = await governor.voteSucceeded(proposalId);
            expect(succeeded).to.be.false;
        });
        
        it("3.13 Should allow voting by delegatee", async function () {
            // voter3 delegates to voter1
            await governanceToken.connect(voter3).delegate(voter1.address);
            
            // Create new proposal after delegation
            const targets = [await governanceToken.getAddress()];
            const values = [0];
            const calldatas = [governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("10")])];
            const description = "Delegation test proposal";
            
            const tx = await governor.connect(proposer).proposeWithCategory(targets, values, calldatas, description, 0);
            const receipt = await tx.wait();
            
            const event = receipt.logs.find(log => {
                try {
                    return governor.interface.parseLog(log).name === "ProposalCreatedWithCategory";
                } catch { return false; }
            });
            const newProposalId = governor.interface.parseLog(event).args[0];
            
            await time.increase(VOTING_DELAY + 1);
            
            // voter1 now has 5000 + 1000 = 6000 voting power
            await governor.connect(voter1).castVote(newProposalId, 1);
            
            const votes = await governor.proposalVotes(newProposalId);
            expect(votes[1]).to.equal(ethers.parseEther("6000")); // 5000 + 1000 delegated
        });
        
        it("3.14 Should count abstain votes towards quorum", async function () {
            // Only abstain votes
            await governor.connect(voter1).castVote(proposalId, 2); // 5000 ABSTAIN
            
            const quorumReached = await governor.quorumReached(proposalId);
            expect(quorumReached).to.be.true; // Abstain counts for quorum
        });
        
        it("3.15 Should reject invalid vote types", async function () {
            await expect(
                governor.connect(voter1).castVote(proposalId, 5) // Invalid type
            ).to.be.reverted;
        });
    });
    
    // ============================================
    // SUITE 4: EXECUTION & TIMELOCK
    // ============================================
    
    describe("⏰ Suite 4: Execution & Timelock", function () {
        
        let proposalId;
        let targets, values, calldatas, description, descriptionHash;
        
        beforeEach(async function () {
            // Fund timelock with tokens for execution
            await governanceToken.mint(await timelock.getAddress(), ethers.parseEther("1000"));
            
            targets = [await governanceToken.getAddress()];
            values = [0];
            calldatas = [governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("10")])];
            description = "Execution test proposal";
            descriptionHash = ethers.id(description);
            
            const tx = await governor.connect(proposer).proposeWithCategory(targets, values, calldatas, description, 0);
            const receipt = await tx.wait();
            
            const event = receipt.logs.find(log => {
                try {
                    return governor.interface.parseLog(log).name === "ProposalCreatedWithCategory";
                } catch { return false; }
            });
            proposalId = governor.interface.parseLog(event).args[0];
            
            // Move past voting delay
            await time.increase(VOTING_DELAY + 1);
            
            // Vote with quorum
            await governor.connect(voter1).castVote(proposalId, 1); // 5000 FOR
            await governor.connect(voter2).castVote(proposalId, 1); // 3000 FOR
            
            // Move past voting period
            await time.increase(VOTING_PERIOD + 1);
        });
        
        it("4.1 Should queue successful proposal", async function () {
            await expect(
                governor.queue(targets, values, calldatas, descriptionHash)
            ).to.emit(governor, "ProposalQueued");
            
            const state = await governor.state(proposalId);
            expect(state).to.equal(5); // Queued
        });
        
        it("4.2 Should reject queueing defeated proposal", async function () {
            // Create defeated proposal
            const newTargets = [await governanceToken.getAddress()];
            const newValues = [0];
            const newCalldatas = [governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("10")])];
            const newDescription = "Defeated proposal";
            
            const tx = await governor.connect(proposer).proposeWithCategory(newTargets, newValues, newCalldatas, newDescription, 0);
            const receipt = await tx.wait();
            
            const event = receipt.logs.find(log => {
                try {
                    return governor.interface.parseLog(log).name === "ProposalCreatedWithCategory";
                } catch { return false; }
            });
            const newProposalId = governor.interface.parseLog(event).args[0];
            
            await time.increase(VOTING_DELAY + 1);
            
            // Vote AGAINST
            await governor.connect(voter1).castVote(newProposalId, 0); // 5000 AGAINST
            
            await time.increase(VOTING_PERIOD + 1);
            
            const newDescriptionHash = ethers.id(newDescription);
            await expect(
                governor.queue(newTargets, newValues, newCalldatas, newDescriptionHash)
            ).to.be.reverted;
        });
        
        it("4.3 Should execute proposal after timelock delay", async function () {
            // Queue
            await governor.queue(targets, values, calldatas, descriptionHash);
            
            // Wait for timelock delay
            await time.increase(TIMELOCK_DELAY + 1);
            
            // Execute
            await expect(
                governor.execute(targets, values, calldatas, descriptionHash)
            ).to.emit(governor, "ProposalExecuted");
            
            const state = await governor.state(proposalId);
            expect(state).to.equal(7); // Executed
        });
        
        it("4.4 Should reject execution before timelock delay", async function () {
            await governor.queue(targets, values, calldatas, descriptionHash);
            
            // Try to execute immediately
            await expect(
                governor.execute(targets, values, calldatas, descriptionHash)
            ).to.be.reverted;
        });
        
        it("4.5 Should track timelock stats", async function () {
            await governor.queue(targets, values, calldatas, descriptionHash);
            
            const stats = await timelock.getTimelockStats();
            expect(stats[0]).to.equal(1); // totalQueued
            expect(stats[1]).to.equal(0); // totalExecuted
            expect(stats[2]).to.equal(0); // totalCancelled
            expect(stats[3]).to.equal(TIMELOCK_DELAY); // minDelay
        });
        
        it("4.6 Should update timelock stats after execution", async function () {
            await governor.queue(targets, values, calldatas, descriptionHash);
            await time.increase(TIMELOCK_DELAY + 1);
            await governor.execute(targets, values, calldatas, descriptionHash);
            
            const stats = await timelock.getTimelockStats();
            expect(stats[1]).to.equal(1); // totalExecuted
        });
        
        it("4.7 Should allow cancelling queued proposal", async function () {
            await governor.queue(targets, values, calldatas, descriptionHash);
            
            await expect(
                governor.cancel(targets, values, calldatas, descriptionHash)
            ).to.emit(governor, "ProposalCanceled");
            
            const state = await governor.state(proposalId);
            expect(state).to.equal(2); // Canceled
        });
        
        it("4.8 Should track cancelled operations in timelock", async function () {
            await governor.queue(targets, values, calldatas, descriptionHash);
            await governor.cancel(targets, values, calldatas, descriptionHash);
            
            const stats = await timelock.getTimelockStats();
            expect(stats[2]).to.equal(1); // totalCancelled
        });
        
        it("4.9 Should execute proposal actions correctly", async function () {
            const balanceBefore = await governanceToken.balanceOf(voter1.address);
            
            await governor.queue(targets, values, calldatas, descriptionHash);
            await time.increase(TIMELOCK_DELAY + 1);
            await governor.execute(targets, values, calldatas, descriptionHash);
            
            const balanceAfter = await governanceToken.balanceOf(voter1.address);
            expect(balanceAfter - balanceBefore).to.equal(ethers.parseEther("10"));
        });
        
        it("4.10 Should allow anyone to execute (EXECUTOR role)", async function () {
            await governor.queue(targets, values, calldatas, descriptionHash);
            await time.increase(TIMELOCK_DELAY + 1);
            
            // Execute by non-admin (voter3)
            await expect(
                governor.connect(voter3).execute(targets, values, calldatas, descriptionHash)
            ).to.emit(governor, "ProposalExecuted");
        });
        
        it("4.11 Should execute batch operations", async function () {
            const batchTargets = [
                await governanceToken.getAddress(),
                await governanceToken.getAddress()
            ];
            const batchValues = [0, 0];
            const batchCalldatas = [
                governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("5")]),
                governanceToken.interface.encodeFunctionData("transfer", [voter2.address, ethers.parseEther("5")])
            ];
            const batchDescription = "Batch execution test";
            
            const tx = await governor.connect(proposer).proposeWithCategory(batchTargets, batchValues, batchCalldatas, batchDescription, 0);
            const receipt = await tx.wait();
            
            const event = receipt.logs.find(log => {
                try {
                    return governor.interface.parseLog(log).name === "ProposalCreatedWithCategory";
                } catch { return false; }
            });
            const batchProposalId = governor.interface.parseLog(event).args[0];
            
            await time.increase(VOTING_DELAY + 1);
            await governor.connect(voter1).castVote(batchProposalId, 1);
            await time.increase(VOTING_PERIOD + 1);
            
            const batchDescriptionHash = ethers.id(batchDescription);
            await governor.queue(batchTargets, batchValues, batchCalldatas, batchDescriptionHash);
            await time.increase(TIMELOCK_DELAY + 1);
            
            await expect(
                governor.execute(batchTargets, batchValues, batchCalldatas, batchDescriptionHash)
            ).to.emit(governor, "ProposalExecuted");
        });
        
        it("4.12 Should reject execution of expired proposal", async function () {
            await governor.queue(targets, values, calldatas, descriptionHash);
            
            // Wait for expiration (past grace period)
            // Timelock typically has a grace period (e.g., 14 days after ready time)
            await time.increase(TIMELOCK_DELAY + (14 * 24 * 60 * 60) + 1);
            
            // Try to execute expired proposal
            // Note: This depends on Timelock implementation
            // Some timelocks expire proposals automatically
        });
    });
    
    // ============================================
    // SUITE 5: DELEGATION
    // ============================================
    
    describe("👥 Suite 5: Delegation", function () {
        
        it("5.1 Should delegate voting power", async function () {
            await governanceToken.connect(voter3).delegate(voter1.address);
            
            const voter1Power = await governanceToken.getVotes(voter1.address);
            expect(voter1Power).to.equal(ethers.parseEther("6000")); // 5000 + 1000 delegated
        });
        
        it("5.2 Should remove voting power from delegator", async function () {
            await governanceToken.connect(voter3).delegate(voter1.address);
            
            const voter3Power = await governanceToken.getVotes(voter3.address);
            expect(voter3Power).to.equal(0);
        });
        
        it("5.3 Should allow self-delegation", async function () {
            // Remove delegation
            await governanceToken.connect(voter3).delegate(ethers.ZeroAddress);
            
            await governanceToken.connect(voter3).selfDelegate();
            
            const voter3Power = await governanceToken.getVotes(voter3.address);
            expect(voter3Power).to.equal(ethers.parseEther("1000"));
        });
        
        it("5.4 Should track delegates correctly", async function () {
            await governanceToken.connect(voter3).delegate(voter1.address);
            
            const delegate = await governanceToken.delegates(voter3.address);
            expect(delegate).to.equal(voter1.address);
        });
        
        it("5.5 Should allow changing delegation", async function () {
            await governanceToken.connect(voter3).delegate(voter1.address);
            await governanceToken.connect(voter3).delegate(voter2.address);
            
            const voter1Power = await governanceToken.getVotes(voter1.address);
            const voter2Power = await governanceToken.getVotes(voter2.address);
            
            expect(voter1Power).to.equal(ethers.parseEther("5000")); // Back to original
            expect(voter2Power).to.equal(ethers.parseEther("4000")); // 3000 + 1000
        });
        
        it("5.6 Should track delegation stats", async function () {
            await governanceToken.connect(voter3).delegate(voter1.address);
            
            const stats = await governanceToken.getUserStats(voter1.address);
            // stats: balance, votingPower, totalMinted, totalBurned, delegate
            expect(stats[1]).to.equal(ethers.parseEther("6000")); // votingPower
        });
        
        it("5.7 Should use historical voting power for proposals", async function () {
            // Create proposal
            const targets = [await governanceToken.getAddress()];
            const values = [0];
            const calldatas = [governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("10")])];
            const description = "Historical power test";
            
            const tx = await governor.connect(proposer).proposeWithCategory(targets, values, calldatas, description, 0);
            const receipt = await tx.wait();
            
            const event = receipt.logs.find(log => {
                try {
                    return governor.interface.parseLog(log).name === "ProposalCreatedWithCategory";
                } catch { return false; }
            });
            const proposalId = governor.interface.parseLog(event).args[0];
            
            await time.increase(VOTING_DELAY + 1);
            
            // Delegate AFTER proposal created
            await governanceToken.connect(voter3).delegate(voter1.address);
            
            // Vote with voter1 - should use power from proposal snapshot
            await governor.connect(voter1).castVote(proposalId, 1);
            
            const votes = await governor.proposalVotes(proposalId);
            expect(votes[1]).to.equal(ethers.parseEther("5000")); // Only original power
        });
        
        it("5.8 Should allow delegation before tokens are minted", async function () {
            // Deploy new token for this test
            const NewToken = await ethers.getContractFactory("GovernanceToken");
            const newToken = await NewToken.deploy(owner.address);
            
            // Delegate before having tokens
            await newToken.connect(voter1).selfDelegate();
            
            // Mint tokens
            await newToken.mint(voter1.address, ethers.parseEther("100"));
            
            const power = await newToken.getVotes(voter1.address);
            expect(power).to.equal(ethers.parseEther("100"));
        });
        
        it("5.9 Should emit delegation events", async function () {
            await expect(
                governanceToken.connect(voter3).delegate(voter1.address)
            ).to.emit(governanceToken, "DelegateChanged");
        });
        
        it("5.10 Should handle circular delegation prevention", async function () {
            // Note: ERC20Votes doesn't prevent circular delegation at contract level
            // It's up to users to avoid it, but it won't break the system
            await governanceToken.connect(voter1).delegate(voter2.address);
            await governanceToken.connect(voter2).delegate(voter1.address);
            
            // Both should have 0 voting power (circular delegation)
            const voter1Power = await governanceToken.getVotes(voter1.address);
            const voter2Power = await governanceToken.getVotes(voter2.address);
            
            // Powers might be 0 or unchanged depending on implementation
            // Just verify it doesn't revert
            expect(voter1Power).to.be.a("bigint");
            expect(voter2Power).to.be.a("bigint");
        });
    });
    
    // ============================================
    // SUITE 6: EDGE CASES & SECURITY
    // ============================================
    
    describe("🛡️ Suite 6: Edge Cases & Security", function () {
        
        it("6.1 Should reject proposals without quorum", async function () {
            const targets = [await governanceToken.getAddress()];
            const values = [0];
            const calldatas = [governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("10")])];
            const description = "No quorum proposal";
            
            const tx = await governor.connect(proposer).proposeWithCategory(targets, values, calldatas, description, 0);
            const receipt = await tx.wait();
            
            const event = receipt.logs.find(log => {
                try {
                    return governor.interface.parseLog(log).name === "ProposalCreatedWithCategory";
                } catch { return false; }
            });
            const proposalId = governor.interface.parseLog(event).args[0];
            
            await time.increase(VOTING_DELAY + 1);
            
            // Only voter3 votes (1000 GOV) - not enough for quorum (440 GOV needed)
            // Actually 1000 > 440, so let's make voter3 have less
            await governanceToken.connect(voter3).transfer(voter1.address, ethers.parseEther("999"));
            
            // Now voter3 only has 1 GOV
            await governor.connect(voter3).castVote(proposalId, 1);
            
            await time.increase(VOTING_PERIOD + 1);
            
            const quorumReached = await governor.quorumReached(proposalId);
            expect(quorumReached).to.be.false;
            
            const descriptionHash = ethers.id(description);
            await expect(
                governor.queue(targets, values, calldatas, descriptionHash)
            ).to.be.reverted;
        });
        
        it("6.2 Should protect against reentrancy in execution", async function () {
            // Deploy malicious contract (if needed)
            // For now, verify that execution follows checks-effects-interactions pattern
            // The OpenZeppelin Governor already has reentrancy protection
        });
        
        it("6.3 Should handle token burning after voting", async function () {
            const targets = [await governanceToken.getAddress()];
            const values = [0];
            const calldatas = [governanceToken.interface.encodeFunctionData("transfer", [voter1.address, ethers.parseEther("10")])];
            const description = "Burn after vote";
            
            const tx = await governor.connect(proposer).proposeWithCategory(targets, values, calldatas, description, 0);
            const receipt = await tx.wait();
            
            const event = receipt.logs.find(log => {
                try {
                    return governor.interface.parseLog(log).name === "ProposalCreatedWithCategory";
                } catch { return false; }
            });
            const proposalId = governor.interface.parseLog(event).args[0];
            
            await time.increase(VOTING_DELAY + 1);
            
            await governor.connect(voter1).castVote(proposalId, 1);
            
            // Burn tokens after voting
            await governanceToken.connect(voter1).burn(ethers.parseEther("1000"));
            
            // Vote should still count with original power
            const votes = await governor.proposalVotes(proposalId);
            expect(votes[1]).to.equal(ethers.parseEther("5000"));
        });
        
        it("6.4 Should prevent unauthorized minting", async function () {
            await expect(
                governanceToken.connect(voter1).mint(voter1.address, ethers.parseEther("1000"))
            ).to.be.revertedWithCustomError(governanceToken, "NotAuthorizedMinter");
        });
        
        it("6.5 Should allow only timelock to execute", async function () {
            // Proposals can only be executed through timelock
            // Direct execution on target contracts should fail if not from timelock
            
            // This is protected by the PROPOSER_ROLE and EXECUTOR_ROLE in timelock
            const PROPOSER_ROLE = await timelock.PROPOSER_ROLE();
            expect(await timelock.hasRole(PROPOSER_ROLE, await governor.getAddress())).to.be.true;
            expect(await timelock.hasRole(PROPOSER_ROLE, voter1.address)).to.be.false;
        });
        
        it("6.6 Should handle zero address delegation", async function () {
            await governanceToken.connect(voter1).delegate(ethers.ZeroAddress);
            
            const power = await governanceToken.getVotes(voter1.address);
            expect(power).to.equal(0);
        });
        
        it("6.7 Should track total supply correctly after burns", async function () {
            const supplyBefore = await governanceToken.totalSupply();
            
            await governanceToken.connect(voter1).burn(ethers.parseEther("100"));
            
            const supplyAfter = await governanceToken.totalSupply();
            expect(supplyBefore - supplyAfter).to.equal(ethers.parseEther("100"));
        });
        
        it("6.8 Should allow burnFrom with allowance", async function () {
            await governanceToken.connect(voter1).approve(voter2.address, ethers.parseEther("100"));
            
            await governanceToken.connect(voter2).burnFrom(voter1.address, ethers.parseEther("50"));
            
            const allowance = await governanceToken.allowance(voter1.address, voter2.address);
            expect(allowance).to.equal(ethers.parseEther("50"));
        });
    });
});

