const { expect } = require("chai");
const { ethers } = require("hardhat");
const { time } = require("@nomicfoundation/hardhat-network-helpers");

/**
 * ============================================
 * GOVERNANCE SYSTEM INTEGRATION TESTS
 * ============================================
 * Complete test suite for all governance contracts
 */

describe("Governance System Integration Tests", function () {
    let deployer, alice, bob, charlie, dave, eve;
    let governanceToken;
    let timelock, gaslessVoting, multiSig, quadraticVoting, treasury, templates, snapshotBridge;
    
    const INITIAL_SUPPLY = ethers.utils.parseEther("1000000");
    const TIMELOCK_DELAY = 2 * 24 * 60 * 60; // 48 hours

    beforeEach(async function () {
        [deployer, alice, bob, charlie, dave, eve] = await ethers.getSigners();

        // Deploy Mock Token
        const Token = await ethers.getContractFactory("MockERC20");
        governanceToken = await Token.deploy("Sphera", "SPHERA", INITIAL_SUPPLY);
        
        // Distribute tokens
        await governanceToken.transfer(alice.address, ethers.utils.parseEther("100000"));
        await governanceToken.transfer(bob.address, ethers.utils.parseEther("50000"));
        await governanceToken.transfer(charlie.address, ethers.utils.parseEther("25000"));

        // Deploy Timelock
        const TimelockController = await ethers.getContractFactory("TimelockController");
        timelock = await TimelockController.deploy(
            TIMELOCK_DELAY,
            [deployer.address],
            [ethers.constants.AddressZero],
            deployer.address
        );

        // Deploy Gasless Voting
        const GaslessVoting = await ethers.getContractFactory("GaslessVoting");
        gaslessVoting = await GaslessVoting.deploy(governanceToken.address, "Sphera Governance");

        // Deploy Multi-Sig
        const MultiSigGovernance = await ethers.getContractFactory("MultiSigGovernance");
        multiSig = await MultiSigGovernance.deploy(
            [deployer.address, alice.address, bob.address, charlie.address, dave.address],
            3
        );

        // Deploy Quadratic Voting
        const QuadraticVoting = await ethers.getContractFactory("QuadraticVoting");
        quadraticVoting = await QuadraticVoting.deploy(governanceToken.address);

        // Deploy Treasury
        const TreasuryManagement = await ethers.getContractFactory("TreasuryManagement");
        treasury = await TreasuryManagement.deploy();

        // Deploy Templates
        const ProposalTemplates = await ethers.getContractFactory("ProposalTemplates");
        templates = await ProposalTemplates.deploy();

        // Deploy Snapshot Bridge
        const SnapshotBridge = await ethers.getContractFactory("SnapshotBridge");
        snapshotBridge = await SnapshotBridge.deploy("sphera.eth");
    });

    describe("1. Timelock Controller", function () {
        it("Should enforce delay on execution", async function () {
            const target = alice.address;
            const value = 0;
            const data = "0x";
            const predecessor = ethers.constants.HashZero;
            const salt = ethers.constants.HashZero;

            await timelock.schedule(target, value, data, predecessor, salt, TIMELOCK_DELAY);
            
            // Try to execute immediately (should fail)
            await expect(
                timelock.execute(target, value, data, predecessor, salt)
            ).to.be.reverted;

            // Fast forward time
            await time.increase(TIMELOCK_DELAY + 1);

            // Now should succeed
            await expect(
                timelock.execute(target, value, data, predecessor, salt)
            ).to.not.be.reverted;
        });
    });

    describe("2. Gasless Voting", function () {
        it("Should create proposal and allow voting", async function () {
            const description = "Test Proposal";
            
            await gaslessVoting.createProposal(description, 7 * 24 * 60 * 60); // 7 days
            
            const proposalCount = await gaslessVoting.proposalCount();
            expect(proposalCount).to.equal(1);

            // Alice votes
            await governanceToken.connect(alice).approve(gaslessVoting.address, ethers.constants.MaxUint256);
            await gaslessVoting.connect(alice).castVote(0, 1); // Vote FOR

            const proposal = await gaslessVoting.getProposal(0);
            expect(proposal.forVotes).to.be.gt(0);
        });
    });

    describe("3. Multi-Signature", function () {
        it("Should require 3 signatures to execute", async function () {
            const target = eve.address;
            const value = ethers.utils.parseEther("1");
            const data = "0x";

            // Fund multi-sig
            await deployer.sendTransaction({ to: multiSig.address, value });

            // Submit transaction
            await multiSig.submitTransaction(target, value, data, "Test payment");
            
            // Get transaction count
            const txCount = await multiSig.getTransactionCount();
            expect(txCount).to.equal(1);

            // Approve with 2 signers (not enough)
            await multiSig.connect(alice).approveTransaction(0);
            await multiSig.connect(bob).approveTransaction(0);

            let tx = await multiSig.getTransaction(0);
            expect(tx.executed).to.be.false;

            // Third approval (should execute)
            await multiSig.connect(charlie).approveTransaction(0);

            tx = await multiSig.getTransaction(0);
            expect(tx.executed).to.be.true;
        });
    });

    describe("4. Quadratic Voting", function () {
        it("Should calculate vote power as sqrt of balance", async function () {
            // Alice has 100,000 tokens
            const aliceBalance = await governanceToken.balanceOf(alice.address);
            const expectedPower = Math.floor(Math.sqrt(parseFloat(ethers.utils.formatEther(aliceBalance))));
            
            const votePower = await quadraticVoting.calculateVotePower(aliceBalance);
            
            // Allow small margin of error due to integer math
            expect(votePower).to.be.closeTo(expectedPower, 1);
        });

        it("Should reduce whale voting power significantly", async function () {
            // Whale with 1M tokens
            const whaleBalance = ethers.utils.parseEther("1000000");
            const whalePower = await quadraticVoting.calculateVotePower(whaleBalance);
            
            // Small holder with 100 tokens
            const smallBalance = ethers.utils.parseEther("100");
            const smallPower = await quadraticVoting.calculateVotePower(smallBalance);

            // Whale has 10,000x tokens but only 100x voting power
            const balanceRatio = parseFloat(ethers.utils.formatEther(whaleBalance)) / 
                                parseFloat(ethers.utils.formatEther(smallBalance));
            const powerRatio = parseFloat(whalePower) / parseFloat(smallPower);

            expect(balanceRatio).to.equal(10000);
            expect(powerRatio).to.be.closeTo(100, 1);
        });
    });

    describe("5. Treasury Management", function () {
        it("Should create and execute payment", async function () {
            // Grant roles
            const TREASURER_ROLE = await treasury.TREASURER_ROLE();
            const APPROVER_ROLE = await treasury.APPROVER_ROLE();
            
            await treasury.grantRole(TREASURER_ROLE, deployer.address);
            await treasury.grantRole(APPROVER_ROLE, alice.address);
            await treasury.grantRole(APPROVER_ROLE, bob.address);

            // Deposit funds
            await treasury.deposit(ethers.constants.AddressZero, { value: ethers.utils.parseEther("10") });

            // Create payment
            await treasury.createPayment(
                eve.address,
                ethers.constants.AddressZero,
                ethers.utils.parseEther("1"),
                0, // ONE_TIME
                "Test payment",
                "Marketing",
                "Expenses"
            );

            // Approve payment
            await treasury.connect(alice).approvePayment(0);
            await treasury.connect(bob).approvePayment(0);

            // Execute payment
            await treasury.executePayment(0);

            const payment = await treasury.getPayment(0);
            expect(payment.status).to.equal(2); // EXECUTED
        });
    });

    describe("6. Proposal Templates", function () {
        it("Should have 7 default templates", async function () {
            const templateCount = await templates.templateCount();
            expect(templateCount).to.equal(7);
        });

        it("Should increment usage count when template is used", async function () {
            const templateId = 0; // Treasury Withdrawal
            
            const beforeUsage = (await templates.getTemplate(templateId)).usageCount;
            
            await templates.useTemplate(templateId);
            
            const afterUsage = (await templates.getTemplate(templateId)).usageCount;
            expect(afterUsage).to.equal(beforeUsage.add(1));
        });
    });

    describe("7. Snapshot Bridge", function () {
        it("Should create and close proposal", async function () {
            const RELAYER_ROLE = await snapshotBridge.RELAYER_ROLE();
            await snapshotBridge.grantRole(RELAYER_ROLE, deployer.address);

            const snapshotId = "0x123";
            const ipfsHash = "QmTest123";
            const now = Math.floor(Date.now() / 1000);
            const votingStart = now;
            const votingEnd = now + 7 * 24 * 60 * 60;

            await snapshotBridge.createProposal(
                snapshotId,
                ipfsHash,
                votingStart,
                votingEnd,
                alice.address,
                0,
                "0x",
                "Test proposal"
            );

            const proposal = await snapshotBridge.getProposal(snapshotId);
            expect(proposal.snapshotId).to.equal(snapshotId);
            expect(proposal.state).to.equal(1); // ACTIVE
        });
    });

    describe("8. Integration: Complete Governance Flow", function () {
        it("Should execute full governance cycle", async function () {
            // 1. Create proposal via template
            await templates.useTemplate(0);

            // 2. Vote using quadratic voting
            await governanceToken.connect(alice).approve(quadraticVoting.address, ethers.constants.MaxUint256);
            await quadraticVoting.connect(alice).createProposal("Treasury withdrawal", 7 * 24 * 60 * 60);
            await quadraticVoting.connect(alice).castVote(0, 1);

            // 3. Multi-sig approves execution
            await multiSig.submitTransaction(eve.address, 0, "0x", "Execute proposal");
            await multiSig.connect(alice).approveTransaction(0);
            await multiSig.connect(bob).approveTransaction(0);
            await multiSig.connect(charlie).approveTransaction(0);

            // 4. Schedule in timelock
            const target = eve.address;
            const value = 0;
            const data = "0x";
            const predecessor = ethers.constants.HashZero;
            const salt = ethers.constants.HashZero;

            await timelock.schedule(target, value, data, predecessor, salt, TIMELOCK_DELAY);

            // 5. Wait for timelock
            await time.increase(TIMELOCK_DELAY + 1);

            // 6. Execute from timelock
            await timelock.execute(target, value, data, predecessor, salt);

            // Verify all steps completed successfully
            expect(await templates.templateCount()).to.be.gt(0);
            expect(await quadraticVoting.proposalCount()).to.be.gt(0);
            expect(await multiSig.getTransactionCount()).to.be.gt(0);
        });
    });
});
