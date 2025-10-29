// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/utils/Pausable.sol";

/**
 * @title TokenStaking
 * @dev Contrato de staking para tokens SPHE con recompensas
 * @notice Permite a usuarios stakear tokens y ganar recompensas por tiempo
 */
contract TokenStaking is Ownable, ReentrancyGuard, Pausable {
    using SafeERC20 for IERC20;

    // ============ State Variables ============

    /// @notice Token que se stakea (SPHE)
    IERC20 public immutable stakingToken;

    /// @notice Tasa de recompensa por segundo (en wei)
    uint256 public rewardRatePerSecond;

    /// @notice Total de tokens stakeados en el contrato
    uint256 public totalStaked;

    /// @notice Pool de recompensas disponibles
    uint256 public rewardPool;

    /// @notice Mínimo de stake permitido
    uint256 public minimumStake;

    /// @notice Máximo de stake por usuario
    uint256 public maximumStake;

    /// @notice Fee de early unstake (en basis points, 100 = 1%)
    uint256 public earlyUnstakeFee;

    /// @notice Contador de pools creados
    uint256 public poolCount;

    // ============ Structs ============

    /// @dev Información de stake de un usuario
    struct StakeInfo {
        uint256 amount;           // Cantidad stakeada
        uint256 startTime;        // Timestamp de inicio
        uint256 lastClaimTime;    // Última vez que reclamó rewards
        uint256 accumulatedRewards; // Rewards acumuladas no reclamadas
        uint256 poolId;           // ID del pool (para multi-pool)
    }

    /// @dev Pool de staking con diferentes términos
    struct StakingPool {
        uint256 id;
        string name;
        uint256 lockPeriod;       // Período de bloqueo en segundos
        uint256 rewardMultiplier; // Multiplicador de rewards (100 = 1x, 200 = 2x)
        uint256 totalStaked;      // Total stakeado en este pool
        uint256 minStake;         // Mínimo para este pool
        bool active;              // Si el pool está activo
    }

    // ============ Mappings ============

    /// @notice Información de stake por usuario
    mapping(address => StakeInfo) public stakes;

    /// @notice Pools de staking disponibles
    mapping(uint256 => StakingPool) public pools;

    /// @notice Total de rewards reclamadas por usuario
    mapping(address => uint256) public totalRewardsClaimed;

    /// @notice Si un usuario está stakeando
    mapping(address => bool) public isStaking;

    // ============ Events ============

    event Staked(
        address indexed user,
        uint256 amount,
        uint256 poolId,
        uint256 timestamp
    );

    event Unstaked(
        address indexed user,
        uint256 amount,
        uint256 rewards,
        uint256 timestamp
    );

    event RewardsClaimed(
        address indexed user,
        uint256 amount,
        uint256 timestamp
    );

    event RewardRateUpdated(
        uint256 oldRate,
        uint256 newRate,
        uint256 timestamp
    );

    event RewardPoolFunded(
        address indexed funder,
        uint256 amount,
        uint256 timestamp
    );

    event PoolCreated(
        uint256 indexed poolId,
        string name,
        uint256 lockPeriod,
        uint256 rewardMultiplier
    );

    event EmergencyWithdraw(
        address indexed user,
        uint256 amount,
        uint256 timestamp
    );

    // ============ Errors ============

    error InsufficientBalance();
    error NoStakeFound();
    error InsufficientStake();
    error StakeBelowMinimum();
    error StakeAboveMaximum();
    error InsufficientRewardPool();
    error PoolNotActive();
    error StillLocked();
    error InvalidPool();
    error ZeroAmount();

    // ============ Constructor ============

    /**
     * @notice Constructor del contrato
     * @param _stakingToken Dirección del token SPHE
     * @param _rewardRatePerSecond Tasa de reward inicial por segundo
     */
    constructor(
        address _stakingToken,
        uint256 _rewardRatePerSecond
    ) virtual Ownable(msg.sender) {
        require(_stakingToken != address(0), "Invalid token address");
        
        stakingToken = IERC20(_stakingToken);
        rewardRatePerSecond = _rewardRatePerSecond;
        minimumStake = 10 * 10**18; // 10 SPHE mínimo
        maximumStake = 1_000_000 * 10**18; // 1M SPHE máximo
        earlyUnstakeFee = 500; // 5% fee para early unstake
        poolCount = 0;

        // Crear pool por defecto (flexible)
        _createPool(0, "Flexible", 0, 100, 1 * 10**18);
    }

    // ============ External Functions ============

    /**
     * @notice Stakear tokens en un pool específico
     * @param amount Cantidad a stakear
     * @param poolId ID del pool
     */
    function stake(uint256 amount, uint256 poolId) external nonReentrant whenNotPaused {
        if (amount == 0) revert ZeroAmount();
        if (amount < minimumStake) revert StakeBelowMinimum();
        
        StakingPool memory pool = pools[poolId];
        if (!pool.active) revert PoolNotActive();
        if (amount < pool.minStake) revert StakeBelowMinimum();

        StakeInfo storage userStake = stakes[msg.sender];

        // Si ya está stakeando, reclamar rewards primero
        if (isStaking[msg.sender]) {
            _claimRewards(msg.sender);
        }

        // Transferir tokens al contrato
        stakingToken.safeTransferFrom(msg.sender, address(this), amount);

        // Actualizar stake info
        if (userStake.amount == 0) {
            // Nuevo stake
            userStake.amount = amount;
            userStake.startTime = block.timestamp;
            userStake.lastClaimTime = block.timestamp;
            userStake.poolId = poolId;
            isStaking[msg.sender] = true;
        } else {
            // Agregar a stake existente
            userStake.amount += amount;
        }

        // Verificar máximo
        if (userStake.amount > maximumStake) revert StakeAboveMaximum();

        // Actualizar totales
        totalStaked += amount;
        pools[poolId].totalStaked += amount;

        emit Staked(msg.sender, amount, poolId, block.timestamp);
    }

    /**
     * @notice Unstakear tokens y reclamar rewards
     * @param amount Cantidad a unstakear (0 = todo)
     */
    function unstake(uint256 amount) external nonReentrant {
        StakeInfo storage userStake = stakes[msg.sender];
        
        if (!isStaking[msg.sender]) revert NoStakeFound();
        if (userStake.amount == 0) revert NoStakeFound();

        // Si amount es 0, unstakear todo
        uint256 unstakeAmount = amount == 0 ? userStake.amount : amount;
        
        if (unstakeAmount > userStake.amount) revert InsufficientStake();

        StakingPool memory pool = pools[userStake.poolId];

        // Verificar lock period
        if (pool.lockPeriod > 0) {
            uint256 lockEndTime = userStake.startTime + pool.lockPeriod;
            if (block.timestamp < lockEndTime) revert StillLocked();
        }

        // Calcular rewards
        uint256 pendingRewards = calculateRewards(msg.sender);
        uint256 totalRewards = userStake.accumulatedRewards + pendingRewards;

        // Verificar si hay suficientes rewards en el pool
        if (totalRewards > rewardPool) revert InsufficientRewardPool();

        // Actualizar estado
        userStake.amount -= unstakeAmount;
        totalStaked -= unstakeAmount;
        pools[userStake.poolId].totalStaked -= unstakeAmount;

        if (userStake.amount == 0) {
            isStaking[msg.sender] = false;
            delete stakes[msg.sender];
        } else {
            userStake.lastClaimTime = block.timestamp;
            userStake.accumulatedRewards = 0;
        }

        // Actualizar rewards pool
        rewardPool -= totalRewards;
        totalRewardsClaimed[msg.sender] += totalRewards;

        // Transferir tokens + rewards
        stakingToken.safeTransfer(msg.sender, unstakeAmount + totalRewards);

        emit Unstaked(msg.sender, unstakeAmount, totalRewards, block.timestamp);
    }

    /**
     * @notice Reclamar rewards sin unstakear
     */
    function claimRewards() external nonReentrant {
        _claimRewards(msg.sender);
    }

    /**
     * @notice Emergency withdraw sin rewards (con fee)
     * @dev Solo para situaciones de emergencia
     */
    function emergencyWithdraw() external nonReentrant {
        StakeInfo storage userStake = stakes[msg.sender];
        
        if (!isStaking[msg.sender]) revert NoStakeFound();
        if (userStake.amount == 0) revert NoStakeFound();

        uint256 amount = userStake.amount;
        
        // Calcular fee
        uint256 fee = (amount * earlyUnstakeFee) / 10000;
        uint256 amountAfterFee = amount - fee;

        // Actualizar estado
        totalStaked -= amount;
        pools[userStake.poolId].totalStaked -= amount;
        isStaking[msg.sender] = false;
        delete stakes[msg.sender];

        // Fee va al reward pool
        rewardPool += fee;

        // Transferir tokens sin rewards
        stakingToken.safeTransfer(msg.sender, amountAfterFee);

        emit EmergencyWithdraw(msg.sender, amountAfterFee, block.timestamp);
    }

    // ============ View Functions ============

    /**
     * @notice Calcular rewards pendientes de un usuario
     * @param user Dirección del usuario
     * @return Cantidad de rewards pendientes
     */
    function calculateRewards(address user) public view returns (uint256) {
        StakeInfo memory userStake = stakes[user];
        
        if (!isStaking[user] || userStake.amount == 0) {
            return 0;
        }

        StakingPool memory pool = pools[userStake.poolId];
        
        uint256 stakingDuration = block.timestamp - userStake.lastClaimTime;
        uint256 baseReward = (userStake.amount * rewardRatePerSecond * stakingDuration) / 1e18;
        
        // Aplicar multiplicador del pool
        uint256 reward = (baseReward * pool.rewardMultiplier) / 100;
        
        return reward;
    }

    /**
     * @notice Obtener información completa de stake de un usuario
     * @param user Dirección del usuario
     */
    function getStakeInfo(address user) external view returns (
        uint256 amount,
        uint256 startTime,
        uint256 lastClaimTime,
        uint256 accumulatedRewards,
        uint256 pendingRewards,
        uint256 poolId,
        uint256 lockEndTime,
        bool canUnstake
    ) {
        StakeInfo memory userStake = stakes[user];
        StakingPool memory pool = pools[userStake.poolId];
        
        amount = userStake.amount;
        startTime = userStake.startTime;
        lastClaimTime = userStake.lastClaimTime;
        accumulatedRewards = userStake.accumulatedRewards;
        pendingRewards = calculateRewards(user);
        poolId = userStake.poolId;
        lockEndTime = startTime + pool.lockPeriod;
        canUnstake = block.timestamp >= lockEndTime;
    }

    /**
     * @notice Calcular APY actual de un pool
     * @param poolId ID del pool
     * @return APY en basis points (10000 = 100%)
     */
    function calculateAPY(uint256 poolId) external view returns (uint256) {
        StakingPool memory pool = pools[poolId];
        
        if (!pool.active || totalStaked == 0) return 0;
        
        // Rewards anuales por token
        uint256 yearlyRewardPerToken = rewardRatePerSecond * 365 days;
        
        // APY = (yearlyReward / 1 token) * 100 * pool multiplier
        uint256 apy = (yearlyRewardPerToken * 100 * pool.rewardMultiplier) / 100;
        
        return apy;
    }

    /**
     * @notice Obtener información de un pool
     */
    function getPoolInfo(uint256 poolId) external view returns (
        string memory name,
        uint256 lockPeriod,
        uint256 rewardMultiplier,
        uint256 totalPoolStaked,
        uint256 minStakeAmount,
        bool active,
        uint256 apy
    ) {
        StakingPool memory pool = pools[poolId];
        
        name = pool.name;
        lockPeriod = pool.lockPeriod;
        rewardMultiplier = pool.rewardMultiplier;
        totalPoolStaked = pool.totalStaked;
        minStakeAmount = pool.minStake;
        active = pool.active;
        apy = this.calculateAPY(poolId);
    }

    /**
     * @notice Obtener número total de pools creados
     */
    function getPoolCount() external view returns (uint256) {
        return poolCount;
    }

    // ============ Admin Functions ============

    /**
     * @notice Crear un nuevo pool de staking
     */
    function createPool(
        uint256 poolId,
        string memory name,
        uint256 lockPeriod,
        uint256 rewardMultiplier,
        uint256 minStake
    ) external onlyOwner {
        _createPool(poolId, name, lockPeriod, rewardMultiplier, minStake);
    }

    /**
     * @notice Actualizar tasa de reward
     */
    function updateRewardRate(uint256 newRate) external onlyOwner {
        uint256 oldRate = rewardRatePerSecond;
        rewardRatePerSecond = newRate;
        emit RewardRateUpdated(oldRate, newRate, block.timestamp);
    }

    /**
     * @notice Agregar fondos al reward pool
     */
    function fundRewardPool(uint256 amount) external onlyOwner {
        stakingToken.safeTransferFrom(msg.sender, address(this), amount);
        rewardPool += amount;
        emit RewardPoolFunded(msg.sender, amount, block.timestamp);
    }

    /**
     * @notice Actualizar fee de early unstake
     */
    function updateEarlyUnstakeFee(uint256 newFee) external onlyOwner {
        require(newFee <= 1000, "Fee too high"); // Max 10%
        earlyUnstakeFee = newFee;
    }

    /**
     * @notice Pausar el contrato
     */
    function pause() external onlyOwner {
        _pause();
    }

    /**
     * @notice Despausar el contrato
     */
    function unpause() external onlyOwner {
        _unpause();
    }

    /**
     * @notice Activar/desactivar un pool
     */
    function togglePool(uint256 poolId, bool active) external onlyOwner {
        pools[poolId].active = active;
    }

    // ============ Internal Functions ============

    function _claimRewards(address user) internal {
        StakeInfo storage userStake = stakes[user];
        
        if (!isStaking[user]) revert NoStakeFound();

        uint256 pendingRewards = calculateRewards(user);
        uint256 totalRewards = userStake.accumulatedRewards + pendingRewards;

        if (totalRewards == 0) return;
        if (totalRewards > rewardPool) revert InsufficientRewardPool();

        // Actualizar estado
        userStake.lastClaimTime = block.timestamp;
        userStake.accumulatedRewards = 0;
        rewardPool -= totalRewards;
        totalRewardsClaimed[user] += totalRewards;

        // Transferir rewards
        stakingToken.safeTransfer(user, totalRewards);

        emit RewardsClaimed(user, totalRewards, block.timestamp);
    }

    function _createPool(
        uint256 poolId,
        string memory name,
        uint256 lockPeriod,
        uint256 rewardMultiplier,
        uint256 minStake
    ) internal {
        pools[poolId] = StakingPool({
            id: poolId,
            name: name,
            lockPeriod: lockPeriod,
            rewardMultiplier: rewardMultiplier,
            totalStaked: 0,
            minStake: minStake,
            active: true
        });

        poolCount++;

        emit PoolCreated(poolId, name, lockPeriod, rewardMultiplier);
    }
}
