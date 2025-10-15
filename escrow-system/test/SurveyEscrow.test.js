const { expect } = require("chai");
const { ethers } = require("hardhat");

describe("SurveyEscrow", function () {
  let surveyEscrow;
  let spheToken;
  let owner;
  let participant1;
  let participant2;
  let participant3;

  const SURVEY_ID = 1;
  const DEPOSIT_AMOUNT = ethers.parseEther("100"); // 100 SPHE

  beforeEach(async function () {
    // Get signers
    [owner, participant1, participant2, participant3] = await ethers.getSigners();

    // Deploy mock SPHE token
    const MockERC20 = await ethers.getContractFactory("MockERC20");
    spheToken = await MockERC20.deploy("SPHE Token", "SPHE", ethers.parseEther("1000000000")); // 1B supply

    // Deploy SurveyEscrow
    const SurveyEscrow = await ethers.getContractFactory("SurveyEscrow");
    surveyEscrow = await SurveyEscrow.deploy(await spheToken.getAddress());

    // Transfer SPHE to participants
    await spheToken.transfer(participant1.address, ethers.parseEther("1000"));
    await spheToken.transfer(participant2.address, ethers.parseEther("1000"));
    await spheToken.transfer(participant3.address, ethers.parseEther("1000"));
  });

  describe("Deployment", function () {
    it("Should set the correct SPHE token address", async function () {
      expect(await surveyEscrow.spheToken()).to.equal(await spheToken.getAddress());
    });

    it("Should set the deployer as owner", async function () {
      expect(await surveyEscrow.owner()).to.equal(owner.address);
    });
  });

  describe("Deposits", function () {
    it("Should allow participants to deposit SPHE", async function () {
      // Approve escrow contract
      await spheToken.connect(participant1).approve(await surveyEscrow.getAddress(), DEPOSIT_AMOUNT);

      // Deposit
      await expect(
        surveyEscrow.connect(participant1).deposit(SURVEY_ID, DEPOSIT_AMOUNT)
      ).to.emit(surveyEscrow, "Deposit")
        .withArgs(SURVEY_ID, participant1.address, DEPOSIT_AMOUNT, await ethers.provider.getBlock('latest').then(b => b.timestamp + 1));

      // Check deposit amount
      expect(await surveyEscrow.getDeposit(SURVEY_ID, participant1.address)).to.equal(DEPOSIT_AMOUNT);
    });

    it("Should revert if amount is 0", async function () {
      await expect(
        surveyEscrow.connect(participant1).deposit(SURVEY_ID, 0)
      ).to.be.revertedWith("Amount must be greater than 0");
    });

    it("Should revert if user hasn't approved tokens", async function () {
      await expect(
        surveyEscrow.connect(participant1).deposit(SURVEY_ID, DEPOSIT_AMOUNT)
      ).to.be.reverted;
    });

    it("Should allow multiple deposits from same participant", async function () {
      await spheToken.connect(participant1).approve(await surveyEscrow.getAddress(), DEPOSIT_AMOUNT * 2n);

      await surveyEscrow.connect(participant1).deposit(SURVEY_ID, DEPOSIT_AMOUNT);
      await surveyEscrow.connect(participant1).deposit(SURVEY_ID, DEPOSIT_AMOUNT);

      expect(await surveyEscrow.getDeposit(SURVEY_ID, participant1.address)).to.equal(DEPOSIT_AMOUNT * 2n);
    });

    it("Should track participants correctly", async function () {
      // Deposit from 3 participants
      await spheToken.connect(participant1).approve(await surveyEscrow.getAddress(), DEPOSIT_AMOUNT);
      await spheToken.connect(participant2).approve(await surveyEscrow.getAddress(), DEPOSIT_AMOUNT);
      await spheToken.connect(participant3).approve(await surveyEscrow.getAddress(), DEPOSIT_AMOUNT);

      await surveyEscrow.connect(participant1).deposit(SURVEY_ID, DEPOSIT_AMOUNT);
      await surveyEscrow.connect(participant2).deposit(SURVEY_ID, DEPOSIT_AMOUNT);
      await surveyEscrow.connect(participant3).deposit(SURVEY_ID, DEPOSIT_AMOUNT);

      const participants = await surveyEscrow.getParticipants(SURVEY_ID);
      expect(participants).to.have.lengthOf(3);
      expect(participants).to.include(participant1.address);
      expect(participants).to.include(participant2.address);
      expect(participants).to.include(participant3.address);
    });
  });

  describe("Survey Info", function () {
    beforeEach(async function () {
      // Setup: 3 participants deposit
      await spheToken.connect(participant1).approve(await surveyEscrow.getAddress(), DEPOSIT_AMOUNT);
      await spheToken.connect(participant2).approve(await surveyEscrow.getAddress(), DEPOSIT_AMOUNT);
      await spheToken.connect(participant3).approve(await surveyEscrow.getAddress(), DEPOSIT_AMOUNT);

      await surveyEscrow.connect(participant1).deposit(SURVEY_ID, DEPOSIT_AMOUNT);
      await surveyEscrow.connect(participant2).deposit(SURVEY_ID, DEPOSIT_AMOUNT);
      await surveyEscrow.connect(participant3).deposit(SURVEY_ID, DEPOSIT_AMOUNT);
    });

    it("Should return correct survey info", async function () {
      const info = await surveyEscrow.getSurveyInfo(SURVEY_ID);

      expect(info.totalDeposited).to.equal(DEPOSIT_AMOUNT * 3n);
      expect(info.totalPaidOut).to.equal(0);
      expect(info.finalized).to.equal(false);
      expect(info.participantsCount).to.equal(3);
    });
  });

  describe("Finalize Survey", function () {
    beforeEach(async function () {
      // Setup: 3 participants deposit
      await spheToken.connect(participant1).approve(await surveyEscrow.getAddress(), DEPOSIT_AMOUNT);
      await spheToken.connect(participant2).approve(await surveyEscrow.getAddress(), DEPOSIT_AMOUNT);
      await spheToken.connect(participant3).approve(await surveyEscrow.getAddress(), DEPOSIT_AMOUNT);

      await surveyEscrow.connect(participant1).deposit(SURVEY_ID, DEPOSIT_AMOUNT);
      await surveyEscrow.connect(participant2).deposit(SURVEY_ID, DEPOSIT_AMOUNT);
      await surveyEscrow.connect(participant3).deposit(SURVEY_ID, DEPOSIT_AMOUNT);
    });

    it("Should allow owner to finalize survey", async function () {
      const winners = [participant1.address, participant2.address];
      const amounts = [ethers.parseEther("150"), ethers.parseEther("150")];

      await expect(
        surveyEscrow.finalizeSurvey(SURVEY_ID, winners, amounts)
      ).to.emit(surveyEscrow, "SurveyFinalized")
        .withArgs(SURVEY_ID, ethers.parseEther("300"), 2, await ethers.provider.getBlock('latest').then(b => b.timestamp + 1));

      const info = await surveyEscrow.getSurveyInfo(SURVEY_ID);
      expect(info.finalized).to.equal(true);
      expect(info.totalPaidOut).to.equal(ethers.parseEther("300"));
    });

    it("Should revert if not called by owner", async function () {
      const winners = [participant1.address];
      const amounts = [ethers.parseEther("100")];

      await expect(
        surveyEscrow.connect(participant1).finalizeSurvey(SURVEY_ID, winners, amounts)
      ).to.be.reverted;
    });

    it("Should revert if arrays length mismatch", async function () {
      const winners = [participant1.address, participant2.address];
      const amounts = [ethers.parseEther("150")]; // Solo 1 amount

      await expect(
        surveyEscrow.finalizeSurvey(SURVEY_ID, winners, amounts)
      ).to.be.revertedWith("Arrays length mismatch");
    });

    it("Should revert if insufficient balance", async function () {
      const winners = [participant1.address];
      const amounts = [ethers.parseEther("500")]; // Más del total depositado

      await expect(
        surveyEscrow.finalizeSurvey(SURVEY_ID, winners, amounts)
      ).to.be.revertedWith("Insufficient balance");
    });

    it("Should revert if already finalized", async function () {
      const winners = [participant1.address];
      const amounts = [ethers.parseEther("100")];

      await surveyEscrow.finalizeSurvey(SURVEY_ID, winners, amounts);

      await expect(
        surveyEscrow.finalizeSurvey(SURVEY_ID, winners, amounts)
      ).to.be.revertedWith("Survey already finalized");
    });

    it("Should transfer correct amounts to winners", async function () {
      const winners = [participant1.address, participant2.address];
      const amounts = [ethers.parseEther("200"), ethers.parseEther("100")];

      const balanceBefore1 = await spheToken.balanceOf(participant1.address);
      const balanceBefore2 = await spheToken.balanceOf(participant2.address);

      await surveyEscrow.finalizeSurvey(SURVEY_ID, winners, amounts);

      const balanceAfter1 = await spheToken.balanceOf(participant1.address);
      const balanceAfter2 = await spheToken.balanceOf(participant2.address);

      expect(balanceAfter1 - balanceBefore1).to.equal(amounts[0]);
      expect(balanceAfter2 - balanceBefore2).to.equal(amounts[1]);
    });
  });

  describe("Payout Batch", function () {
    beforeEach(async function () {
      // Setup: 3 participants deposit
      await spheToken.connect(participant1).approve(await surveyEscrow.getAddress(), DEPOSIT_AMOUNT);
      await spheToken.connect(participant2).approve(await surveyEscrow.getAddress(), DEPOSIT_AMOUNT);
      await spheToken.connect(participant3).approve(await surveyEscrow.getAddress(), DEPOSIT_AMOUNT);

      await surveyEscrow.connect(participant1).deposit(SURVEY_ID, DEPOSIT_AMOUNT);
      await surveyEscrow.connect(participant2).deposit(SURVEY_ID, DEPOSIT_AMOUNT);
      await surveyEscrow.connect(participant3).deposit(SURVEY_ID, DEPOSIT_AMOUNT);
    });

    it("Should allow batch payout", async function () {
      const winners = [participant1.address, participant2.address, participant3.address];
      const amounts = [ethers.parseEther("100"), ethers.parseEther("100"), ethers.parseEther("100")];

      await surveyEscrow.payoutBatch(SURVEY_ID, winners, amounts, 0, 2);

      const info = await surveyEscrow.getSurveyInfo(SURVEY_ID);
      expect(info.totalPaidOut).to.equal(ethers.parseEther("200"));
    });

    it("Should revert if already finalized", async function () {
      const winners = [participant1.address];
      const amounts = [ethers.parseEther("100")];

      await surveyEscrow.finalizeSurvey(SURVEY_ID, winners, amounts);

      await expect(
        surveyEscrow.payoutBatch(SURVEY_ID, winners, amounts, 0, 1)
      ).to.be.revertedWith("Survey already finalized");
    });
  });

  describe("Emergency Withdraw", function () {
    it("Should allow owner to emergency withdraw", async function () {
      // Deposit some tokens
      await spheToken.connect(participant1).approve(await surveyEscrow.getAddress(), DEPOSIT_AMOUNT);
      await surveyEscrow.connect(participant1).deposit(SURVEY_ID, DEPOSIT_AMOUNT);

      const balanceBefore = await spheToken.balanceOf(owner.address);

      await surveyEscrow.emergencyWithdraw(owner.address, DEPOSIT_AMOUNT);

      const balanceAfter = await spheToken.balanceOf(owner.address);
      expect(balanceAfter - balanceBefore).to.equal(DEPOSIT_AMOUNT);
    });

    it("Should revert if not owner", async function () {
      await expect(
        surveyEscrow.connect(participant1).emergencyWithdraw(participant1.address, DEPOSIT_AMOUNT)
      ).to.be.reverted;
    });
  });
});

// Mock ERC20 for testing
const MockERC20 = `
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";

contract MockERC20 is ERC20 {
    constructor(string memory name, string memory symbol, uint256 initialSupply) ERC20(name, symbol) {
        _mint(msg.sender, initialSupply);
    }
}
`;
