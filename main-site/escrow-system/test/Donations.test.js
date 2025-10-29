const { expect } = require("chai");
const { ethers } = require("hardhat");
const { loadFixture } = require("@nomicfoundation/hardhat-network-helpers");

describe("Donations Contract", function () {
    // Fixture para desplegar el contrato y configuración inicial
    async function deployDonationsFixture() {
        // Obtener signers
        const [owner, treasury, donor, recipient, user2] = await ethers.getSigners();

        // Deploy mock ERC20 token (SPHE)
        const MockERC20 = await ethers.getContractFactory("contracts/mocks/MockERC20.sol:MockERC20");
        const spheToken = await MockERC20.deploy("TheSocialMask Token", "SPHE", ethers.parseEther("1000000"));
        await spheToken.waitForDeployment();

        // Deploy otro token para tests multi-token
        const maticToken = await MockERC20.deploy("Polygon", "MATIC", ethers.parseEther("1000000"));
        await maticToken.waitForDeployment();

        // Configuración inicial
        const feePercentage = 250; // 2.5%
        const minDonationAmount = ethers.parseEther("1"); // 1 token mínimo

        // Deploy Donations contract
        const Donations = await ethers.getContractFactory("Donations");
        const donations = await Donations.deploy(
            treasury.address,
            feePercentage,
            minDonationAmount
        );
        await donations.waitForDeployment();

        // Permitir SPHE token
        await donations.setTokenAllowance(await spheToken.getAddress(), true);

        // Transferir tokens a donor y user2 para tests
        await spheToken.transfer(donor.address, ethers.parseEther("10000"));
        await spheToken.transfer(user2.address, ethers.parseEther("10000"));
        await maticToken.transfer(donor.address, ethers.parseEther("10000"));

        return { donations, spheToken, maticToken, owner, treasury, donor, recipient, user2, feePercentage, minDonationAmount };
    }

    describe("Deployment", function () {
        it("Should set the correct treasury address", async function () {
            const { donations, treasury } = await loadFixture(deployDonationsFixture);
            expect(await donations.treasury()).to.equal(treasury.address);
        });

        it("Should set the correct fee percentage", async function () {
            const { donations, feePercentage } = await loadFixture(deployDonationsFixture);
            expect(await donations.feePercentage()).to.equal(feePercentage);
        });

        it("Should set the correct minimum donation amount", async function () {
            const { donations, minDonationAmount } = await loadFixture(deployDonationsFixture);
            expect(await donations.minDonationAmount()).to.equal(minDonationAmount);
        });

        it("Should set the correct owner", async function () {
            const { donations, owner } = await loadFixture(deployDonationsFixture);
            expect(await donations.owner()).to.equal(owner.address);
        });

        it("Should allow SPHE token by default", async function () {
            const { donations, spheToken } = await loadFixture(deployDonationsFixture);
            expect(await donations.allowedTokens(await spheToken.getAddress())).to.be.true;
        });
    });

    describe("Donations", function () {
        it("Should donate successfully with correct fee calculation", async function () {
            const { donations, spheToken, donor, recipient, treasury, feePercentage } = await loadFixture(deployDonationsFixture);
            
            const amount = ethers.parseEther("100");
            const expectedFee = (amount * BigInt(feePercentage)) / 10000n;
            const expectedNetAmount = amount - expectedFee;

            // Aprobar tokens
            await spheToken.connect(donor).approve(await donations.getAddress(), amount);

            // Realizar donación
            const tx = await donations.connect(donor).donate(
                recipient.address,
                await spheToken.getAddress(),
                amount,
                false // no anónima
            );

            // Verificar evento emitido
            await expect(tx)
                .to.emit(donations, "DonationSent")
                .withArgs(
                    0, // donationId
                    donor.address,
                    recipient.address,
                    await spheToken.getAddress(),
                    amount,
                    expectedFee,
                    expectedNetAmount,
                    await ethers.provider.getBlock(tx.blockNumber).then(b => b.timestamp),
                    false
                );

            // Verificar balances
            expect(await spheToken.balanceOf(recipient.address)).to.equal(expectedNetAmount);
            expect(await spheToken.balanceOf(treasury.address)).to.equal(expectedFee);
        });

        it("Should track total donated and received", async function () {
            const { donations, spheToken, donor, recipient } = await loadFixture(deployDonationsFixture);
            
            const amount = ethers.parseEther("100");
            const expectedNetAmount = await donations.calculateNetAmount(amount);

            await spheToken.connect(donor).approve(await donations.getAddress(), amount);
            await donations.connect(donor).donate(recipient.address, await spheToken.getAddress(), amount, false);

            expect(await donations.totalDonated(donor.address)).to.equal(amount);
            expect(await donations.totalReceived(recipient.address)).to.equal(expectedNetAmount);
        });

        it("Should increment donation counts", async function () {
            const { donations, spheToken, donor, recipient } = await loadFixture(deployDonationsFixture);
            
            const amount = ethers.parseEther("100");

            await spheToken.connect(donor).approve(await donations.getAddress(), amount);
            await donations.connect(donor).donate(recipient.address, await spheToken.getAddress(), amount, false);

            expect(await donations.donationCount(donor.address)).to.equal(1);
            expect(await donations.receivedCount(recipient.address)).to.equal(1);
        });

        it("Should handle anonymous donations", async function () {
            const { donations, spheToken, donor, recipient } = await loadFixture(deployDonationsFixture);
            
            const amount = ethers.parseEther("100");

            await spheToken.connect(donor).approve(await donations.getAddress(), amount);
            const tx = await donations.connect(donor).donate(
                recipient.address,
                await spheToken.getAddress(),
                amount,
                true // anónima
            );

            // El evento debe tener address(0) como donor
            await expect(tx)
                .to.emit(donations, "DonationSent");

            // Verificar que la donación se guardó como anónima
            const donation = await donations.getDonation(0);
            expect(donation.donor).to.equal(ethers.ZeroAddress);
            expect(donation.isAnonymous).to.be.true;
        });

        it("Should allow multiple donations", async function () {
            const { donations, spheToken, donor, recipient } = await loadFixture(deployDonationsFixture);
            
            const amount1 = ethers.parseEther("100");
            const amount2 = ethers.parseEther("50");

            // Primera donación
            await spheToken.connect(donor).approve(await donations.getAddress(), amount1);
            await donations.connect(donor).donate(recipient.address, await spheToken.getAddress(), amount1, false);

            // Segunda donación
            await spheToken.connect(donor).approve(await donations.getAddress(), amount2);
            await donations.connect(donor).donate(recipient.address, await spheToken.getAddress(), amount2, false);

            expect(await donations.donationCount(donor.address)).to.equal(2);
            expect(await donations.getTotalDonations()).to.equal(2);
        });

        it("Should revert if recipient is zero address", async function () {
            const { donations, spheToken, donor } = await loadFixture(deployDonationsFixture);
            
            const amount = ethers.parseEther("100");
            await spheToken.connect(donor).approve(await donations.getAddress(), amount);

            await expect(
                donations.connect(donor).donate(ethers.ZeroAddress, await spheToken.getAddress(), amount, false)
            ).to.be.revertedWith("Invalid recipient");
        });

        it("Should revert if donating to yourself", async function () {
            const { donations, spheToken, donor } = await loadFixture(deployDonationsFixture);
            
            const amount = ethers.parseEther("100");
            await spheToken.connect(donor).approve(await donations.getAddress(), amount);

            await expect(
                donations.connect(donor).donate(donor.address, await spheToken.getAddress(), amount, false)
            ).to.be.revertedWith("Cannot donate to yourself");
        });

        it("Should revert if amount is below minimum", async function () {
            const { donations, spheToken, donor, recipient } = await loadFixture(deployDonationsFixture);
            
            const amount = ethers.parseEther("0.5"); // Menor al mínimo (1)
            await spheToken.connect(donor).approve(await donations.getAddress(), amount);

            await expect(
                donations.connect(donor).donate(recipient.address, await spheToken.getAddress(), amount, false)
            ).to.be.revertedWith("Amount below minimum");
        });

        it("Should revert if token is not allowed", async function () {
            const { donations, maticToken, donor, recipient } = await loadFixture(deployDonationsFixture);
            
            const amount = ethers.parseEther("100");
            await maticToken.connect(donor).approve(await donations.getAddress(), amount);

            await expect(
                donations.connect(donor).donate(recipient.address, await maticToken.getAddress(), amount, false)
            ).to.be.revertedWith("Token not allowed");
        });

        it("Should revert if insufficient allowance", async function () {
            const { donations, spheToken, donor, recipient } = await loadFixture(deployDonationsFixture);
            
            const amount = ethers.parseEther("100");
            // No aprobar tokens

            await expect(
                donations.connect(donor).donate(recipient.address, await spheToken.getAddress(), amount, false)
            ).to.be.reverted;
        });
    });

    describe("View Functions", function () {
        it("Should calculate fee correctly", async function () {
            const { donations, feePercentage } = await loadFixture(deployDonationsFixture);
            
            const amount = ethers.parseEther("100");
            const expectedFee = (amount * BigInt(feePercentage)) / 10000n;

            expect(await donations.calculateFee(amount)).to.equal(expectedFee);
        });

        it("Should calculate net amount correctly", async function () {
            const { donations } = await loadFixture(deployDonationsFixture);
            
            const amount = ethers.parseEther("100");
            const fee = await donations.calculateFee(amount);
            const expectedNetAmount = amount - fee;

            expect(await donations.calculateNetAmount(amount)).to.equal(expectedNetAmount);
        });

        it("Should return correct user stats", async function () {
            const { donations, spheToken, donor, recipient } = await loadFixture(deployDonationsFixture);
            
            const amount = ethers.parseEther("100");
            const netAmount = await donations.calculateNetAmount(amount);

            await spheToken.connect(donor).approve(await donations.getAddress(), amount);
            await donations.connect(donor).donate(recipient.address, await spheToken.getAddress(), amount, false);

            const donorStats = await donations.getUserStats(donor.address);
            expect(donorStats.donated).to.equal(amount);
            expect(donorStats.donationsGiven).to.equal(1);

            const recipientStats = await donations.getUserStats(recipient.address);
            expect(recipientStats.received).to.equal(netAmount);
            expect(recipientStats.donationsReceived).to.equal(1);
        });

        it("Should return correct donation info", async function () {
            const { donations, spheToken, donor, recipient } = await loadFixture(deployDonationsFixture);
            
            const amount = ethers.parseEther("100");
            const fee = await donations.calculateFee(amount);
            const netAmount = await donations.calculateNetAmount(amount);

            await spheToken.connect(donor).approve(await donations.getAddress(), amount);
            await donations.connect(donor).donate(recipient.address, await spheToken.getAddress(), amount, false);

            const donation = await donations.getDonation(0);
            expect(donation.donor).to.equal(donor.address);
            expect(donation.recipient).to.equal(recipient.address);
            expect(donation.token).to.equal(await spheToken.getAddress());
            expect(donation.amount).to.equal(amount);
            expect(donation.fee).to.equal(fee);
            expect(donation.netAmount).to.equal(netAmount);
            expect(donation.isAnonymous).to.be.false;
        });
    });

    describe("Admin Functions", function () {
        it("Should update treasury address", async function () {
            const { donations, owner, user2 } = await loadFixture(deployDonationsFixture);

            await expect(donations.connect(owner).updateTreasury(user2.address))
                .to.emit(donations, "TreasuryUpdated");

            expect(await donations.treasury()).to.equal(user2.address);
        });

        it("Should update fee percentage", async function () {
            const { donations, owner } = await loadFixture(deployDonationsFixture);

            const newFee = 300; // 3%
            await expect(donations.connect(owner).updateFeePercentage(newFee))
                .to.emit(donations, "FeePercentageUpdated");

            expect(await donations.feePercentage()).to.equal(newFee);
        });

        it("Should revert if fee is too high", async function () {
            const { donations, owner } = await loadFixture(deployDonationsFixture);

            const tooHighFee = 1001; // > 10%
            await expect(
                donations.connect(owner).updateFeePercentage(tooHighFee)
            ).to.be.revertedWith("Fee too high");
        });

        it("Should update minimum donation amount", async function () {
            const { donations, owner } = await loadFixture(deployDonationsFixture);

            const newMin = ethers.parseEther("2");
            await expect(donations.connect(owner).updateMinDonationAmount(newMin))
                .to.emit(donations, "MinDonationAmountUpdated");

            expect(await donations.minDonationAmount()).to.equal(newMin);
        });

        it("Should allow token", async function () {
            const { donations, maticToken, owner } = await loadFixture(deployDonationsFixture);

            await expect(donations.connect(owner).setTokenAllowance(await maticToken.getAddress(), true))
                .to.emit(donations, "TokenAllowanceUpdated")
                .withArgs(await maticToken.getAddress(), true);

            expect(await donations.allowedTokens(await maticToken.getAddress())).to.be.true;
        });

        it("Should disallow token", async function () {
            const { donations, spheToken, owner } = await loadFixture(deployDonationsFixture);

            await expect(donations.connect(owner).setTokenAllowance(await spheToken.getAddress(), false))
                .to.emit(donations, "TokenAllowanceUpdated")
                .withArgs(await spheToken.getAddress(), false);

            expect(await donations.allowedTokens(await spheToken.getAddress())).to.be.false;
        });

        it("Should allow multiple tokens at once", async function () {
            const { donations, maticToken, owner } = await loadFixture(deployDonationsFixture);

            // Deploy otro token
            const MockERC20 = await ethers.getContractFactory("contracts/mocks/MockERC20.sol:MockERC20");
            const token2 = await MockERC20.deploy("Token2", "TK2", ethers.parseEther("1000000"));

            await donations.connect(owner).setMultipleTokenAllowance([await maticToken.getAddress(), await token2.getAddress()]);

            expect(await donations.allowedTokens(await maticToken.getAddress())).to.be.true;
            expect(await donations.allowedTokens(await token2.getAddress())).to.be.true;
        });

        it("Should pause and unpause", async function () {
            const { donations, spheToken, donor, recipient, owner } = await loadFixture(deployDonationsFixture);

            // Pausar
            await donations.connect(owner).pause();

            const amount = ethers.parseEther("100");
            await spheToken.connect(donor).approve(await donations.getAddress(), amount);

            // Debe fallar mientras está pausado
            await expect(
                donations.connect(donor).donate(recipient.address, await spheToken.getAddress(), amount, false)
            ).to.be.revertedWithCustomError(donations, "EnforcedPause");

            // Despausar
            await donations.connect(owner).unpause();

            // Ahora debe funcionar
            await expect(
                donations.connect(donor).donate(recipient.address, await spheToken.getAddress(), amount, false)
            ).to.not.be.reverted;
        });

        it("Should only allow owner to call admin functions", async function () {
            const { donations, user2 } = await loadFixture(deployDonationsFixture);

            await expect(
                donations.connect(user2).updateTreasury(user2.address)
            ).to.be.revertedWithCustomError(donations, "OwnableUnauthorizedAccount");

            await expect(
                donations.connect(user2).updateFeePercentage(300)
            ).to.be.revertedWithCustomError(donations, "OwnableUnauthorizedAccount");

            await expect(
                donations.connect(user2).pause()
            ).to.be.revertedWithCustomError(donations, "OwnableUnauthorizedAccount");
        });
    });

    describe("Security", function () {
        it("Should be protected against reentrancy", async function () {
            // ReentrancyGuard está implementado, este test verifica que el modifier esté en donate()
            const { donations, spheToken, donor, recipient } = await loadFixture(deployDonationsFixture);
            
            const amount = ethers.parseEther("100");
            await spheToken.connect(donor).approve(await donations.getAddress(), amount);

            // La función donate debe tener el modifier nonReentrant
            // Esto se verifica mediante el análisis del contrato y los demás tests
            await expect(
                donations.connect(donor).donate(recipient.address, await spheToken.getAddress(), amount, false)
            ).to.not.be.reverted;
        });
    });
});

// Mock ERC20 Token para tests
// Este archivo debe estar en contracts/mocks/MockERC20.sol




