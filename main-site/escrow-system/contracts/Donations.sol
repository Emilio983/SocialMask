// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/utils/Pausable.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

/**
 * @title Donations
 * @dev Sistema de donaciones multi-token para Sphoria
 * 
 * Features:
 * - Soporte para cualquier token ERC20 (SPHE, MATIC, etc.)
 * - Comisión de plataforma configurable (default: 2.5%)
 * - Tracking on-chain de donaciones
 * - Leaderboard de donadores
 * - Sistema pausable para emergencias
 * - Seguridad con ReentrancyGuard
 * 
 * @author Sphoria Team
 * @notice Contrato para FASE 3.2
 */
contract Donations is ReentrancyGuard, Pausable, Ownable {
    using SafeERC20 for IERC20;

    // ============================================
    // CONSTANTS & IMMUTABLES
    // ============================================
    
    /// @notice Basis points para cálculos de porcentaje (100% = 10000)
    uint256 public constant BASIS_POINTS = 10000;
    
    /// @notice Comisión máxima permitida (10% = 1000 basis points)
    uint256 public constant MAX_FEE_PERCENTAGE = 1000;

    // ============================================
    // STATE VARIABLES
    // ============================================
    
    /// @notice Dirección del treasury que recibe las comisiones
    address public treasury;
    
    /// @notice Comisión de plataforma en basis points (2.5% = 250)
    uint256 public feePercentage;
    
    /// @notice Mínimo de donación permitido (en wei/smallest unit)
    uint256 public minDonationAmount;

    // ============================================
    // MAPPINGS - TRACKING
    // ============================================
    
    /// @notice Total donado por cada usuario (acumulado)
    mapping(address => uint256) public totalDonated;
    
    /// @notice Total recibido por cada usuario (acumulado)
    mapping(address => uint256) public totalReceived;
    
    /// @notice Cantidad de donaciones realizadas por usuario
    mapping(address => uint256) public donationCount;
    
    /// @notice Cantidad de donaciones recibidas por usuario
    mapping(address => uint256) public receivedCount;
    
    /// @notice Mapeo de tokens permitidos (address => bool)
    mapping(address => bool) public allowedTokens;

    // ============================================
    // STRUCTS
    // ============================================
    
    /// @notice Estructura de una donación
    struct Donation {
        address donor;          // Quien dona
        address recipient;      // Quien recibe
        address token;          // Token usado
        uint256 amount;         // Monto total
        uint256 fee;            // Comisión cobrada
        uint256 netAmount;      // Monto neto recibido
        uint256 timestamp;      // Timestamp de la donación
        bool isAnonymous;       // Si es anónima o no
    }
    
    /// @notice Array de todas las donaciones (para leaderboard)
    Donation[] public donations;

    // ============================================
    // EVENTS
    // ============================================
    
    /// @notice Emitido cuando se realiza una donación
    event DonationSent(
        uint256 indexed donationId,
        address indexed donor,
        address indexed recipient,
        address token,
        uint256 amount,
        uint256 fee,
        uint256 netAmount,
        uint256 timestamp,
        bool isAnonymous
    );
    
    /// @notice Emitido cuando se actualiza el treasury
    event TreasuryUpdated(
        address indexed oldTreasury,
        address indexed newTreasury
    );
    
    /// @notice Emitido cuando se actualiza el porcentaje de comisión
    event FeePercentageUpdated(
        uint256 oldFeePercentage,
        uint256 newFeePercentage
    );
    
    /// @notice Emitido cuando se actualiza el monto mínimo de donación
    event MinDonationAmountUpdated(
        uint256 oldMinAmount,
        uint256 newMinAmount
    );
    
    /// @notice Emitido cuando se agrega/elimina un token permitido
    event TokenAllowanceUpdated(
        address indexed token,
        bool allowed
    );

    // ============================================
    // CONSTRUCTOR
    // ============================================
    
    /**
     * @notice Constructor del contrato
     * @param _treasury Dirección del treasury
     * @param _feePercentage Porcentaje de comisión en basis points (250 = 2.5%)
     * @param _minDonationAmount Monto mínimo de donación
     */
    constructor(
        address _treasury,
        uint256 _feePercentage,
        uint256 _minDonationAmount
    ) Ownable(msg.sender) {
        require(_treasury != address(0), "Invalid treasury address");
        require(_feePercentage <= MAX_FEE_PERCENTAGE, "Fee too high");
        
        treasury = _treasury;
        feePercentage = _feePercentage;
        minDonationAmount = _minDonationAmount;
    }

    // ============================================
    // MAIN FUNCTIONS
    // ============================================
    
    /**
     * @notice Realizar una donación
     * @param recipient Dirección que recibirá la donación
     * @param tokenAddress Dirección del token a donar
     * @param amount Monto total a donar (incluye comisión)
     * @param isAnonymous Si la donación es anónima
     */
    function donate(
        address recipient,
        address tokenAddress,
        uint256 amount,
        bool isAnonymous
    ) external nonReentrant whenNotPaused returns (uint256 donationId) {
        // Validaciones
        require(recipient != address(0), "Invalid recipient");
        require(recipient != msg.sender, "Cannot donate to yourself");
        require(amount >= minDonationAmount, "Amount below minimum");
        require(allowedTokens[tokenAddress], "Token not allowed");
        
        // Calcular comisión y monto neto
        uint256 fee = (amount * feePercentage) / BASIS_POINTS;
        uint256 netAmount = amount - fee;
        
        require(netAmount > 0, "Net amount must be > 0");
        
        // Obtener instancia del token
        IERC20 token = IERC20(tokenAddress);
        
        // Transferir tokens del donador al contrato primero
        token.safeTransferFrom(msg.sender, address(this), amount);
        
        // Transferir monto neto al recipiente
        token.safeTransfer(recipient, netAmount);
        
        // Transferir comisión al treasury
        if (fee > 0) {
            token.safeTransfer(treasury, fee);
        }
        
        // Actualizar tracking
        totalDonated[msg.sender] += amount;
        totalReceived[recipient] += netAmount;
        donationCount[msg.sender]++;
        receivedCount[recipient]++;
        
        // Crear registro de donación
        donationId = donations.length;
        donations.push(Donation({
            donor: isAnonymous ? address(0) : msg.sender,
            recipient: recipient,
            token: tokenAddress,
            amount: amount,
            fee: fee,
            netAmount: netAmount,
            timestamp: block.timestamp,
            isAnonymous: isAnonymous
        }));
        
        // Emitir evento
        emit DonationSent(
            donationId,
            isAnonymous ? address(0) : msg.sender,
            recipient,
            tokenAddress,
            amount,
            fee,
            netAmount,
            block.timestamp,
            isAnonymous
        );
        
        return donationId;
    }

    // ============================================
    // VIEW FUNCTIONS
    // ============================================
    
    /**
     * @notice Obtener el total de donaciones registradas
     */
    function getTotalDonations() external view returns (uint256) {
        return donations.length;
    }
    
    /**
     * @notice Obtener información de una donación específica
     * @param donationId ID de la donación
     */
    function getDonation(uint256 donationId) external view returns (
        address donor,
        address recipient,
        address token,
        uint256 amount,
        uint256 fee,
        uint256 netAmount,
        uint256 timestamp,
        bool isAnonymous
    ) {
        require(donationId < donations.length, "Invalid donation ID");
        Donation memory d = donations[donationId];
        return (
            d.donor,
            d.recipient,
            d.token,
            d.amount,
            d.fee,
            d.netAmount,
            d.timestamp,
            d.isAnonymous
        );
    }
    
    /**
     * @notice Calcular la comisión para un monto dado
     * @param amount Monto total
     */
    function calculateFee(uint256 amount) public view returns (uint256) {
        return (amount * feePercentage) / BASIS_POINTS;
    }
    
    /**
     * @notice Calcular el monto neto (después de comisiones)
     * @param amount Monto total
     */
    function calculateNetAmount(uint256 amount) public view returns (uint256) {
        uint256 fee = calculateFee(amount);
        return amount - fee;
    }
    
    /**
     * @notice Obtener estadísticas de un usuario
     * @param user Dirección del usuario
     */
    function getUserStats(address user) external view returns (
        uint256 donated,
        uint256 received,
        uint256 donationsGiven,
        uint256 donationsReceived
    ) {
        return (
            totalDonated[user],
            totalReceived[user],
            donationCount[user],
            receivedCount[user]
        );
    }

    // ============================================
    // ADMIN FUNCTIONS
    // ============================================
    
    /**
     * @notice Actualizar la dirección del treasury
     * @param _newTreasury Nueva dirección del treasury
     */
    function updateTreasury(address _newTreasury) external onlyOwner {
        require(_newTreasury != address(0), "Invalid treasury address");
        address oldTreasury = treasury;
        treasury = _newTreasury;
        emit TreasuryUpdated(oldTreasury, _newTreasury);
    }
    
    /**
     * @notice Actualizar el porcentaje de comisión
     * @param _newFeePercentage Nuevo porcentaje en basis points
     */
    function updateFeePercentage(uint256 _newFeePercentage) external onlyOwner {
        require(_newFeePercentage <= MAX_FEE_PERCENTAGE, "Fee too high");
        uint256 oldFeePercentage = feePercentage;
        feePercentage = _newFeePercentage;
        emit FeePercentageUpdated(oldFeePercentage, _newFeePercentage);
    }
    
    /**
     * @notice Actualizar el monto mínimo de donación
     * @param _newMinAmount Nuevo monto mínimo
     */
    function updateMinDonationAmount(uint256 _newMinAmount) external onlyOwner {
        uint256 oldMinAmount = minDonationAmount;
        minDonationAmount = _newMinAmount;
        emit MinDonationAmountUpdated(oldMinAmount, _newMinAmount);
    }
    
    /**
     * @notice Agregar o remover un token de la lista permitida
     * @param tokenAddress Dirección del token
     * @param allowed True para permitir, False para denegar
     */
    function setTokenAllowance(address tokenAddress, bool allowed) external onlyOwner {
        require(tokenAddress != address(0), "Invalid token address");
        allowedTokens[tokenAddress] = allowed;
        emit TokenAllowanceUpdated(tokenAddress, allowed);
    }
    
    /**
     * @notice Agregar múltiples tokens a la lista permitida
     * @param tokenAddresses Array de direcciones de tokens
     */
    function setMultipleTokenAllowance(address[] calldata tokenAddresses) external onlyOwner {
        for (uint256 i = 0; i < tokenAddresses.length; i++) {
            require(tokenAddresses[i] != address(0), "Invalid token address");
            allowedTokens[tokenAddresses[i]] = true;
            emit TokenAllowanceUpdated(tokenAddresses[i], true);
        }
    }
    
    /**
     * @notice Pausar el contrato (emergencias)
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
     * @notice Función de emergencia para recuperar tokens atascados
     * @param tokenAddress Dirección del token
     * @param amount Monto a recuperar
     * @dev Solo usar en emergencias, no debe contener fondos normalmente
     */
    function emergencyWithdraw(address tokenAddress, uint256 amount) external onlyOwner {
        IERC20 token = IERC20(tokenAddress);
        token.safeTransfer(owner(), amount);
    }
}
