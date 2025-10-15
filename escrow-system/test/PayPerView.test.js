const { expect } = require("chai");
const { ethers } = require("hardhat");
const { loadFixture } = require("@nomicfoundation/hardhat-network-helpers");

describe("PayPerView", function () {
    // Fixture para deploy rápido
    async function deployPayPerViewFixture() {
        const [owner, creator, buyer, buyer2, platform] = await ethers.getSigners();
        
        // Deploy Mock SPHE Token
        const MockERC20 = await ethers.getContractFactory("MockERC20");
        const sphe = await MockERC20.deploy("thesocialmask", "SPHE", 18);
        await sphe.waitForDeployment();
        
        // Deploy PayPerView
        const PayPerView = await ethers.getContractFactory("PayPerView");
        const payPerView = await PayPerView.deploy(
            await sphe.getAddress(),
            platform.address
        );
        await payPerView.waitForDeployment();
        
        // Mint tokens a buyers
        const mintAmount = ethers.parseEther("10000");
        await sphe.mint(buyer.address, mintAmount);
        await sphe.mint(buyer2.address, mintAmount);
        
        return { payPerView, sphe, owner, creator, buyer, buyer2, platform };
    }
    
    describe("Deployment", function () {
        it("Should deploy with correct parameters", async function () {
            const { payPerView, sphe, platform } = await loadFixture(deployPayPerViewFixture);
            
            expect(await payPerView.spheToken()).to.equal(await sphe.getAddress());
            expect(await payPerView.platformWallet()).to.equal(platform.address);
            expect(await payPerView.platformFee()).to.equal(250); // 2.5%
            expect(await payPerView.nextContentId()).to.equal(1);
        });
        
        it("Should revert with invalid addresses", async function () {
            const PayPerView = await ethers.getContractFactory("PayPerView");
            const [owner, platform] = await ethers.getSigners();
            
            await expect(
                PayPerView.deploy(ethers.ZeroAddress, platform.address)
            ).to.be.revertedWithCustomError(PayPerView, "InvalidAddress");
            
            await expect(
                PayPerView.deploy(platform.address, ethers.ZeroAddress)
            ).to.be.revertedWithCustomError(PayPerView, "InvalidAddress");
        });
    });
    
    describe("Content Creation", function () {
        it("Should create content successfully", async function () {
            const { payPerView, creator } = await loadFixture(deployPayPerViewFixture);
            const price = ethers.parseEther("10");
            
            await expect(payPerView.connect(creator).createContent(price))
                .to.emit(payPerView, "ContentCreated")
                .withArgs(1, creator.address, price, await ethers.provider.getBlock('latest').then(b => b.timestamp + 1));
            
            const content = await payPerView.contents(1);
            expect(content.creator).to.equal(creator.address);
            expect(content.price).to.equal(price);
            expect(content.active).to.be.true;
            expect(content.totalSales).to.equal(0);
            expect(content.totalRevenue).to.equal(0);
        });
        
        it("Should revert with zero price", async function () {
            const { payPerView, creator } = await loadFixture(deployPayPerViewFixture);
            
            await expect(
                payPerView.connect(creator).createContent(0)
            ).to.be.revertedWithCustomError(payPerView, "InvalidPrice");
        });
        
        it("Should increment contentId", async function () {
            const { payPerView, creator } = await loadFixture(deployPayPerViewFixture);
            const price = ethers.parseEther("10");
            
            await payPerView.connect(creator).createContent(price);
            await payPerView.connect(creator).createContent(price);
            await payPerView.connect(creator).createContent(price);
            
            expect(await payPerView.nextContentId()).to.equal(4);
        });
    });
    
    describe("Content Purchase", function () {
        it("Should purchase content successfully", async function () {
            const { payPerView, sphe, creator, buyer } = await loadFixture(deployPayPerViewFixture);
            const price = ethers.parseEther("100");
            
            // Create content
            await payPerView.connect(creator).createContent(price);
            
            // Approve tokens
            await sphe.connect(buyer).approve(await payPerView.getAddress(), price);
            
            // Purchase
            await expect(payPerView.connect(buyer).purchaseContent(1))
                .to.emit(payPerView, "ContentPurchased")
                .withArgs(1, buyer.address, price, await ethers.provider.getBlock('latest').then(b => b.timestamp + 1));
            
            // Check access
            expect(await payPerView.hasContentAccess(1, buyer.address)).to.be.true;
        });
        
        it("Should calculate fees correctly", async function () {
            const { payPerView, sphe, creator, buyer, platform } = await loadFixture(deployPayPerViewFixture);
            const price = ethers.parseEther("100");
            
            await payPerView.connect(creator).createContent(price);
            await sphe.connect(buyer).approve(await payPerView.getAddress(), price);
            await payPerView.connect(buyer).purchaseContent(1);
            
            const creatorBalance = await payPerView.creatorBalances(creator.address);
            const platformBalance = await payPerView.creatorBalances(platform.address);
            
            // Fee 2.5%
            expect(platformBalance).to.equal(ethers.parseEther("2.5"));
            expect(creatorBalance).to.equal(ethers.parseEther("97.5"));
        });
        
        it("Should update content statistics", async function () {
            const { payPerView, sphe, creator, buyer } = await loadFixture(deployPayPerViewFixture);
            const price = ethers.parseEther("50");
            
            await payPerView.connect(creator).createContent(price);
            await sphe.connect(buyer).approve(await payPerView.getAddress(), price);
            await payPerView.connect(buyer).purchaseContent(1);
            
            const content = await payPerView.contents(1);
            expect(content.totalSales).to.equal(1);
            expect(content.totalRevenue).to.equal(price);
        });
        
        it("Should revert if content not active", async function () {
            const { payPerView, sphe, creator, buyer } = await loadFixture(deployPayPerViewFixture);
            const price = ethers.parseEther("10");
            
            await payPerView.connect(creator).createContent(price);
            await payPerView.connect(creator).deactivateContent(1);
            
            await sphe.connect(buyer).approve(await payPerView.getAddress(), price);
            
            await expect(
                payPerView.connect(buyer).purchaseContent(1)
            ).to.be.revertedWithCustomError(payPerView, "ContentNotActive");
        });
        
        it("Should revert if already purchased", async function () {
            const { payPerView, sphe, creator, buyer } = await loadFixture(deployPayPerViewFixture);
            const price = ethers.parseEther("10");
            
            await payPerView.connect(creator).createContent(price);
            await sphe.connect(buyer).approve(await payPerView.getAddress(), price * 2n);
            await payPerView.connect(buyer).purchaseContent(1);
            
            await expect(
                payPerView.connect(buyer).purchaseContent(1)
            ).to.be.revertedWithCustomError(payPerView, "AlreadyPurchased");
        });
        
        it("Should allow multiple buyers", async function () {
            const { payPerView, sphe, creator, buyer, buyer2 } = await loadFixture(deployPayPerViewFixture);
            const price = ethers.parseEther("10");
            
            await payPerView.connect(creator).createContent(price);
            
            await sphe.connect(buyer).approve(await payPerView.getAddress(), price);
            await payPerView.connect(buyer).purchaseContent(1);
            
            await sphe.connect(buyer2).approve(await payPerView.getAddress(), price);
            await payPerView.connect(buyer2).purchaseContent(1);
            
            expect(await payPerView.hasContentAccess(1, buyer.address)).to.be.true;
            expect(await payPerView.hasContentAccess(1, buyer2.address)).to.be.true;
            
            const content = await payPerView.contents(1);
            expect(content.totalSales).to.equal(2);
        });
    });
    
    describe("Withdraw Funds", function () {
        it("Should withdraw funds successfully", async function () {
            const { payPerView, sphe, creator, buyer } = await loadFixture(deployPayPerViewFixture);
            const price = ethers.parseEther("100");
            
            await payPerView.connect(creator).createContent(price);
            await sphe.connect(buyer).approve(await payPerView.getAddress(), price);
            await payPerView.connect(buyer).purchaseContent(1);
            
            const balanceBefore = await sphe.balanceOf(creator.address);
            
            await expect(payPerView.connect(creator).withdrawFunds())
                .to.emit(payPerView, "FundsWithdrawn");
            
            const balanceAfter = await sphe.balanceOf(creator.address);
            const expectedAmount = ethers.parseEther("97.5"); // 100 - 2.5% fee
            
            expect(balanceAfter - balanceBefore).to.equal(expectedAmount);
            expect(await payPerView.creatorBalances(creator.address)).to.equal(0);
        });
        
        it("Should revert if no funds", async function () {
            const { payPerView, creator } = await loadFixture(deployPayPerViewFixture);
            
            await expect(
                payPerView.connect(creator).withdrawFunds()
            ).to.be.revertedWithCustomError(payPerView, "NoFundsToWithdraw");
        });
        
        it("Platform should withdraw fees", async function () {
            const { payPerView, sphe, creator, buyer, platform } = await loadFixture(deployPayPerViewFixture);
            const price = ethers.parseEther("1000");
            
            await payPerView.connect(creator).createContent(price);
            await sphe.connect(buyer).approve(await payPerView.getAddress(), price);
            await payPerView.connect(buyer).purchaseContent(1);
            
            const balanceBefore = await sphe.balanceOf(platform.address);
            await payPerView.connect(platform).withdrawFunds();
            const balanceAfter = await sphe.balanceOf(platform.address);
            
            const expectedFee = ethers.parseEther("25"); // 2.5% of 1000
            expect(balanceAfter - balanceBefore).to.equal(expectedFee);
        });
    });
    
    describe("Access Control", function () {
        it("Creator should always have access", async function () {
            const { payPerView, creator } = await loadFixture(deployPayPerViewFixture);
            const price = ethers.parseEther("10");
            
            await payPerView.connect(creator).createContent(price);
            
            expect(await payPerView.hasContentAccess(1, creator.address)).to.be.true;
        });
        
        it("Buyer should have access after purchase", async function () {
            const { payPerView, sphe, creator, buyer } = await loadFixture(deployPayPerViewFixture);
            const price = ethers.parseEther("10");
            
            await payPerView.connect(creator).createContent(price);
            
            expect(await payPerView.hasContentAccess(1, buyer.address)).to.be.false;
            
            await sphe.connect(buyer).approve(await payPerView.getAddress(), price);
            await payPerView.connect(buyer).purchaseContent(1);
            
            expect(await payPerView.hasContentAccess(1, buyer.address)).to.be.true;
        });
    });
    
    describe("Content Management", function () {
        it("Should deactivate content", async function () {
            const { payPerView, creator } = await loadFixture(deployPayPerViewFixture);
            const price = ethers.parseEther("10");
            
            await payPerView.connect(creator).createContent(price);
            
            await expect(payPerView.connect(creator).deactivateContent(1))
                .to.emit(payPerView, "ContentDeactivated");
            
            const content = await payPerView.contents(1);
            expect(content.active).to.be.false;
        });
        
        it("Should revert deactivate from non-creator", async function () {
            const { payPerView, creator, buyer } = await loadFixture(deployPayPerViewFixture);
            const price = ethers.parseEther("10");
            
            await payPerView.connect(creator).createContent(price);
            
            await expect(
                payPerView.connect(buyer).deactivateContent(1)
            ).to.be.revertedWithCustomError(payPerView, "Unauthorized");
        });
        
        it("Should update content price", async function () {
            const { payPerView, creator } = await loadFixture(deployPayPerViewFixture);
            const oldPrice = ethers.parseEther("10");
            const newPrice = ethers.parseEther("20");
            
            await payPerView.connect(creator).createContent(oldPrice);
            
            await expect(payPerView.connect(creator).updateContentPrice(1, newPrice))
                .to.emit(payPerView, "ContentPriceUpdated")
                .withArgs(1, oldPrice, newPrice, await ethers.provider.getBlock('latest').then(b => b.timestamp + 1));
            
            const content = await payPerView.contents(1);
            expect(content.price).to.equal(newPrice);
        });
        
        it("Should revert price update from non-creator", async function () {
            const { payPerView, creator, buyer } = await loadFixture(deployPayPerViewFixture);
            const price = ethers.parseEther("10");
            
            await payPerView.connect(creator).createContent(price);
            
            await expect(
                payPerView.connect(buyer).updateContentPrice(1, ethers.parseEther("20"))
            ).to.be.revertedWithCustomError(payPerView, "Unauthorized");
        });
    });
    
    describe("Admin Functions", function () {
        it("Should update platform fee", async function () {
            const { payPerView, owner } = await loadFixture(deployPayPerViewFixture);
            const newFee = 500; // 5%
            
            await expect(payPerView.connect(owner).updatePlatformFee(newFee))
                .to.emit(payPerView, "PlatformFeeUpdated")
                .withArgs(250, newFee, await ethers.provider.getBlock('latest').then(b => b.timestamp + 1));
            
            expect(await payPerView.platformFee()).to.equal(newFee);
        });
        
        it("Should revert if fee too high", async function () {
            const { payPerView, owner } = await loadFixture(deployPayPerViewFixture);
            const invalidFee = 1001; // > 10%
            
            await expect(
                payPerView.connect(owner).updatePlatformFee(invalidFee)
            ).to.be.revertedWithCustomError(payPerView, "InvalidFee");
        });
        
        it("Should update platform wallet", async function () {
            const { payPerView, owner, buyer } = await loadFixture(deployPayPerViewFixture);
            
            await expect(payPerView.connect(owner).updatePlatformWallet(buyer.address))
                .to.emit(payPerView, "PlatformWalletUpdated");
            
            expect(await payPerView.platformWallet()).to.equal(buyer.address);
        });
        
        it("Should revert with zero address wallet", async function () {
            const { payPerView, owner } = await loadFixture(deployPayPerViewFixture);
            
            await expect(
                payPerView.connect(owner).updatePlatformWallet(ethers.ZeroAddress)
            ).to.be.revertedWithCustomError(payPerView, "InvalidAddress");
        });
    });
    
    describe("View Functions", function () {
        it("Should get content info", async function () {
            const { payPerView, creator } = await loadFixture(deployPayPerViewFixture);
            const price = ethers.parseEther("10");
            
            await payPerView.connect(creator).createContent(price);
            
            const [contentCreator, contentPrice, active, totalSales, totalRevenue] = 
                await payPerView.getContentInfo(1);
            
            expect(contentCreator).to.equal(creator.address);
            expect(contentPrice).to.equal(price);
            expect(active).to.be.true;
            expect(totalSales).to.equal(0);
            expect(totalRevenue).to.equal(0);
        });
        
        it("Should get content purchases", async function () {
            const { payPerView, sphe, creator, buyer } = await loadFixture(deployPayPerViewFixture);
            const price = ethers.parseEther("10");
            
            await payPerView.connect(creator).createContent(price);
            await sphe.connect(buyer).approve(await payPerView.getAddress(), price);
            await payPerView.connect(buyer).purchaseContent(1);
            
            const purchases = await payPerView.getContentPurchases(1);
            expect(purchases.length).to.equal(1);
            expect(purchases[0].buyer).to.equal(buyer.address);
            expect(purchases[0].price).to.equal(price);
        });
        
        it("Should get global stats", async function () {
            const { payPerView, creator } = await loadFixture(deployPayPerViewFixture);
            const price = ethers.parseEther("10");
            
            await payPerView.connect(creator).createContent(price);
            await payPerView.connect(creator).createContent(price);
            await payPerView.connect(creator).createContent(price);
            await payPerView.connect(creator).deactivateContent(2);
            
            const [totalContents, totalActiveContents] = await payPerView.getGlobalStats();
            expect(totalContents).to.equal(3);
            expect(totalActiveContents).to.equal(2);
        });
    });
});
