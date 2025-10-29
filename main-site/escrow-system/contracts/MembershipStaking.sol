// SPDX-License-Identifier: MIT
pragma solidity ^0.8.19;

/**
 * @title MembershipStaking
 * @notice Sistema de staking para membresías de Sphoria
 * @dev 50% del pago va al treasury, 50% se hace stake por 30 días
 */

interface IERC20 {
    function transfer(address to, uint256 amount) external returns (bool);
    function transferFrom(address from, address to, uint256 amount) external returns (bool);
    function balanceOf(address account) external view returns (uint256);
}

contract MembershipStaking {
    // ===== STATE VARIABLES =====
    IERC20 public immutable spheToken;
    address public treasury;
    address public owner;

    uint256 public constant STAKE_DURATION = 30 days;
    uint256 public totalStaked;
    uint256 public totalStakers;

    // ===== STRUCTS =====
    struct Stake {
        uint256 amount;           // Cantidad en stake (50% del precio total)
        uint256 unlockTime;       // Timestamp cuando se puede reclamar
        string planType;          // platinum, gold, diamond, creator
        bool claimed;             // Si ya fue reclamado
        uint256 stakedAt;         // Timestamp del stake
    }

    // ===== MAPPINGS =====
    mapping(address => Stake[]) public userStakes;
    mapping(string => uint256) public planPrices;

    // ===== EVENTS =====
    event MembershipPurchased(
        address indexed user,
        string planType,
        uint256 totalAmount,
        uint256 paymentAmount,
        uint256 stakeAmount,
        uint256 unlockTime,
        uint256 stakeId
    );

    event StakeUnlocked(
        address indexed user,
        uint256 stakeId,
        uint256 amount
    );

    event TreasuryUpdated(address indexed oldTreasury, address indexed newTreasury);
    event PlanPriceUpdated(string planType, uint256 newPrice);

    // ===== MODIFIERS =====
    modifier onlyOwner() {
        require(msg.sender == owner, "Only owner can call this");
        _;
    }

    // ===== CONSTRUCTOR =====
    constructor(
        address _spheToken,
        address _treasury
    ) {
        require(_spheToken != address(0), "Invalid SPHE token address");
        require(_treasury != address(0), "Invalid treasury address");

        spheToken = IERC20(_spheToken);
        treasury = _treasury;
        owner = msg.sender;

        // Precios iniciales (en Wei - 18 decimales)
        planPrices["platinum"] = 100 * 10**18;  // 100 SPHE
        planPrices["gold"] = 250 * 10**18;      // 250 SPHE
        planPrices["diamond"] = 500 * 10**18;   // 500 SPHE
        planPrices["creator"] = 750 * 10**18;   // 750 SPHE
    }

    // ===== MAIN FUNCTIONS =====

    /**
     * @notice Comprar membresía con sistema de staking
     * @param planType Tipo de plan (platinum, gold, diamond, creator)
     * @dev El usuario debe aprobar el contrato para gastar SPHE antes de llamar esta función
     */
    function purchaseMembership(string memory planType) external returns (uint256) {
        uint256 totalPrice = planPrices[planType];
        require(totalPrice > 0, "Invalid plan type");

        // Verificar balance del usuario
        require(
            spheToken.balanceOf(msg.sender) >= totalPrice,
            "Insufficient SPHE balance"
        );

        // Calcular split 50/50
        uint256 paymentAmount = totalPrice / 2;  // 50% al treasury
        uint256 stakeAmount = totalPrice - paymentAmount;  // 50% a stake (por si es impar)

        // Transferir 50% al treasury
        require(
            spheToken.transferFrom(msg.sender, treasury, paymentAmount),
            "Payment transfer failed"
        );

        // Transferir 50% a este contrato para stake
        require(
            spheToken.transferFrom(msg.sender, address(this), stakeAmount),
            "Stake transfer failed"
        );

        // Crear stake
        uint256 unlockTime = block.timestamp + STAKE_DURATION;

        Stake memory newStake = Stake({
            amount: stakeAmount,
            unlockTime: unlockTime,
            planType: planType,
            claimed: false,
            stakedAt: block.timestamp
        });

        // Agregar stake al usuario
        if (userStakes[msg.sender].length == 0) {
            totalStakers++;
        }

        userStakes[msg.sender].push(newStake);
        totalStaked += stakeAmount;

        uint256 stakeId = userStakes[msg.sender].length - 1;

        emit MembershipPurchased(
            msg.sender,
            planType,
            totalPrice,
            paymentAmount,
            stakeAmount,
            unlockTime,
            stakeId
        );

        return stakeId;
    }

    /**
     * @notice Reclamar stake después de 30 días
     * @param stakeId ID del stake a reclamar
     */
    function unstake(uint256 stakeId) external {
        require(stakeId < userStakes[msg.sender].length, "Invalid stake ID");

        Stake storage stake = userStakes[msg.sender][stakeId];

        require(!stake.claimed, "Stake already claimed");
        require(block.timestamp >= stake.unlockTime, "Stake still locked");
        require(stake.amount > 0, "No stake amount");

        uint256 amountToReturn = stake.amount;

        // Marcar como reclamado
        stake.claimed = true;
        totalStaked -= amountToReturn;

        // Transferir tokens de vuelta al usuario
        require(
            spheToken.transfer(msg.sender, amountToReturn),
            "Unstake transfer failed"
        );

        emit StakeUnlocked(msg.sender, stakeId, amountToReturn);
    }

    /**
     * @notice Reclamar múltiples stakes de una vez
     * @param stakeIds Array de IDs de stakes a reclamar
     */
    function unstakeMultiple(uint256[] memory stakeIds) external {
        uint256 totalAmount = 0;

        for (uint256 i = 0; i < stakeIds.length; i++) {
            uint256 stakeId = stakeIds[i];
            require(stakeId < userStakes[msg.sender].length, "Invalid stake ID");

            Stake storage stake = userStakes[msg.sender][stakeId];

            if (!stake.claimed && block.timestamp >= stake.unlockTime && stake.amount > 0) {
                totalAmount += stake.amount;
                stake.claimed = true;
                totalStaked -= stake.amount;

                emit StakeUnlocked(msg.sender, stakeId, stake.amount);
            }
        }

        require(totalAmount > 0, "No stakes available to claim");
        require(spheToken.transfer(msg.sender, totalAmount), "Unstake transfer failed");
    }

    // ===== VIEW FUNCTIONS =====

    /**
     * @notice Obtener todos los stakes de un usuario
     */
    function getUserStakes(address user) external view returns (Stake[] memory) {
        return userStakes[user];
    }

    /**
     * @notice Obtener stake específico de un usuario
     */
    function getUserStake(address user, uint256 stakeId) external view returns (Stake memory) {
        require(stakeId < userStakes[user].length, "Invalid stake ID");
        return userStakes[user][stakeId];
    }

    /**
     * @notice Obtener cantidad de stakes de un usuario
     */
    function getUserStakeCount(address user) external view returns (uint256) {
        return userStakes[user].length;
    }

    /**
     * @notice Obtener stakes desbloqueados de un usuario
     */
    function getUnlockedStakes(address user) external view returns (uint256[] memory) {
        Stake[] memory stakes = userStakes[user];
        uint256 unlockedCount = 0;

        // Contar desbloqueados
        for (uint256 i = 0; i < stakes.length; i++) {
            if (!stakes[i].claimed && block.timestamp >= stakes[i].unlockTime) {
                unlockedCount++;
            }
        }

        // Crear array con IDs de desbloqueados
        uint256[] memory unlockedIds = new uint256[](unlockedCount);
        uint256 index = 0;

        for (uint256 i = 0; i < stakes.length; i++) {
            if (!stakes[i].claimed && block.timestamp >= stakes[i].unlockTime) {
                unlockedIds[index] = i;
                index++;
            }
        }

        return unlockedIds;
    }

    /**
     * @notice Obtener total en stake de un usuario (no reclamado)
     */
    function getUserTotalStaked(address user) external view returns (uint256) {
        Stake[] memory stakes = userStakes[user];
        uint256 total = 0;

        for (uint256 i = 0; i < stakes.length; i++) {
            if (!stakes[i].claimed) {
                total += stakes[i].amount;
            }
        }

        return total;
    }

    /**
     * @notice Verificar si un stake está desbloqueado
     */
    function isStakeUnlocked(address user, uint256 stakeId) external view returns (bool) {
        require(stakeId < userStakes[user].length, "Invalid stake ID");
        Stake memory stake = userStakes[user][stakeId];
        return !stake.claimed && block.timestamp >= stake.unlockTime;
    }

    /**
     * @notice Obtener precio de un plan
     */
    function getPlanPrice(string memory planType) external view returns (uint256) {
        return planPrices[planType];
    }

    // ===== ADMIN FUNCTIONS =====

    /**
     * @notice Actualizar dirección del treasury
     */
    function updateTreasury(address newTreasury) external onlyOwner {
        require(newTreasury != address(0), "Invalid treasury address");
        address oldTreasury = treasury;
        treasury = newTreasury;
        emit TreasuryUpdated(oldTreasury, newTreasury);
    }

    /**
     * @notice Actualizar precio de un plan
     */
    function updatePlanPrice(string memory planType, uint256 newPrice) external onlyOwner {
        require(newPrice > 0, "Price must be greater than 0");
        planPrices[planType] = newPrice;
        emit PlanPriceUpdated(planType, newPrice);
    }

    /**
     * @notice Transferir ownership
     */
    function transferOwnership(address newOwner) external onlyOwner {
        require(newOwner != address(0), "Invalid owner address");
        owner = newOwner;
    }

    /**
     * @notice Emergency: Recuperar tokens enviados por error
     */
    function emergencyWithdraw(address token, uint256 amount) external onlyOwner {
        require(token != address(spheToken), "Cannot withdraw SPHE (use only for emergency)");
        IERC20(token).transfer(owner, amount);
    }
}
