// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";

/**
 * @title PayPerView
 * @notice Smart contract para sistema de contenido de pago con Gelato Relay
 * @dev Permite a creadores monetizar contenido exclusivo con gasless transactions
 */
contract PayPerView is Ownable, ReentrancyGuard {
    IERC20 public immutable spheToken;
    
    struct Content {
        address creator;
        uint256 price;
        bool active;
        uint256 totalSales;
        uint256 totalRevenue;
    }
    
    struct Purchase {
        address buyer;
        uint256 contentId;
        uint256 timestamp;
        uint256 price;
    }
    
    // Mappings
    mapping(uint256 => Content) public contents;
    mapping(uint256 => mapping(address => bool)) public hasAccess;
    mapping(address => uint256) public creatorBalances;
    mapping(uint256 => Purchase[]) public contentPurchases;
    
    // State variables
    uint256 public nextContentId = 1;
    uint256 public platformFee = 250; // 2.5%
    uint256 public constant FEE_DENOMINATOR = 10000;
    uint256 public constant MAX_FEE = 1000; // 10% máximo
    
    address public platformWallet;
    
    // Events
    event ContentCreated(
        uint256 indexed contentId, 
        address indexed creator, 
        uint256 price,
        uint256 timestamp
    );
    
    event ContentPurchased(
        uint256 indexed contentId, 
        address indexed buyer, 
        uint256 price,
        uint256 timestamp
    );
    
    event FundsWithdrawn(
        address indexed creator, 
        uint256 amount,
        uint256 timestamp
    );
    
    event ContentDeactivated(
        uint256 indexed contentId,
        uint256 timestamp
    );
    
    event ContentPriceUpdated(
        uint256 indexed contentId,
        uint256 oldPrice,
        uint256 newPrice,
        uint256 timestamp
    );
    
    event PlatformFeeUpdated(
        uint256 oldFee,
        uint256 newFee,
        uint256 timestamp
    );
    
    event PlatformWalletUpdated(
        address indexed oldWallet,
        address indexed newWallet,
        uint256 timestamp
    );
    
    // Errors
    error InvalidPrice();
    error ContentNotActive();
    error AlreadyPurchased();
    error TransferFailed();
    error NoFundsToWithdraw();
    error Unauthorized();
    error InvalidFee();
    error InvalidAddress();
    
    /**
     * @notice Constructor
     * @param _spheToken Dirección del token SPHE
     * @param _platformWallet Wallet de la plataforma para fees
     */
    constructor(address _spheToken, address _platformWallet) Ownable(msg.sender) {
        if (_spheToken == address(0) || _platformWallet == address(0)) {
            revert InvalidAddress();
        }
        spheToken = IERC20(_spheToken);
        platformWallet = _platformWallet;
    }
    
    /**
     * @notice Crear contenido de pago
     * @param _price Precio en SPHE tokens (wei)
     * @return contentId ID del contenido creado
     */
    function createContent(uint256 _price) external returns (uint256) {
        if (_price == 0) revert InvalidPrice();
        
        uint256 contentId = nextContentId++;
        contents[contentId] = Content({
            creator: msg.sender,
            price: _price,
            active: true,
            totalSales: 0,
            totalRevenue: 0
        });
        
        emit ContentCreated(contentId, msg.sender, _price, block.timestamp);
        return contentId;
    }
    
    /**
     * @notice Comprar acceso a contenido
     * @param _contentId ID del contenido
     */
    function purchaseContent(uint256 _contentId) external nonReentrant {
        Content storage content = contents[_contentId];
        
        if (!content.active) revert ContentNotActive();
        if (hasAccess[_contentId][msg.sender]) revert AlreadyPurchased();
        
        uint256 price = content.price;
        uint256 fee = (price * platformFee) / FEE_DENOMINATOR;
        uint256 creatorAmount = price - fee;
        
        // Transfer tokens from buyer to contract
        if (!spheToken.transferFrom(msg.sender, address(this), price)) {
            revert TransferFailed();
        }
        
        // Update balances
        creatorBalances[content.creator] += creatorAmount;
        creatorBalances[platformWallet] += fee;
        
        // Grant access
        hasAccess[_contentId][msg.sender] = true;
        content.totalSales++;
        content.totalRevenue += price;
        
        // Record purchase
        contentPurchases[_contentId].push(Purchase({
            buyer: msg.sender,
            contentId: _contentId,
            timestamp: block.timestamp,
            price: price
        }));
        
        emit ContentPurchased(_contentId, msg.sender, price, block.timestamp);
    }
    
    /**
     * @notice Retirar fondos acumulados
     */
    function withdrawFunds() external nonReentrant {
        uint256 balance = creatorBalances[msg.sender];
        if (balance == 0) revert NoFundsToWithdraw();
        
        creatorBalances[msg.sender] = 0;
        
        if (!spheToken.transfer(msg.sender, balance)) {
            revert TransferFailed();
        }
        
        emit FundsWithdrawn(msg.sender, balance, block.timestamp);
    }
    
    /**
     * @notice Verificar si un usuario tiene acceso a contenido
     * @param _contentId ID del contenido
     * @param _user Dirección del usuario
     * @return bool True si tiene acceso
     */
    function hasContentAccess(uint256 _contentId, address _user) 
        external 
        view 
        returns (bool) 
    {
        // El creador siempre tiene acceso
        if (contents[_contentId].creator == _user) {
            return true;
        }
        return hasAccess[_contentId][_user];
    }
    
    /**
     * @notice Desactivar contenido (solo creador)
     * @param _contentId ID del contenido
     */
    function deactivateContent(uint256 _contentId) external {
        Content storage content = contents[_contentId];
        if (content.creator != msg.sender) revert Unauthorized();
        
        content.active = false;
        emit ContentDeactivated(_contentId, block.timestamp);
    }
    
    /**
     * @notice Actualizar precio de contenido (solo creador)
     * @param _contentId ID del contenido
     * @param _newPrice Nuevo precio
     */
    function updateContentPrice(uint256 _contentId, uint256 _newPrice) external {
        Content storage content = contents[_contentId];
        if (content.creator != msg.sender) revert Unauthorized();
        if (_newPrice == 0) revert InvalidPrice();
        
        uint256 oldPrice = content.price;
        content.price = _newPrice;
        
        emit ContentPriceUpdated(_contentId, oldPrice, _newPrice, block.timestamp);
    }
    
    /**
     * @notice Obtener información de contenido
     * @param _contentId ID del contenido
     * @return creator Creador
     * @return price Precio
     * @return active Estado
     * @return totalSales Ventas totales
     * @return totalRevenue Ingresos totales
     */
    function getContentInfo(uint256 _contentId) 
        external 
        view 
        returns (
            address creator,
            uint256 price,
            bool active,
            uint256 totalSales,
            uint256 totalRevenue
        ) 
    {
        Content memory content = contents[_contentId];
        return (
            content.creator,
            content.price,
            content.active,
            content.totalSales,
            content.totalRevenue
        );
    }
    
    /**
     * @notice Obtener compras de un contenido
     * @param _contentId ID del contenido
     * @return Purchase[] Array de compras
     */
    function getContentPurchases(uint256 _contentId) 
        external 
        view 
        returns (Purchase[] memory) 
    {
        return contentPurchases[_contentId];
    }
    
    /**
     * @notice Obtener balance pendiente de un creador
     * @param _creator Dirección del creador
     * @return uint256 Balance en SPHE
     */
    function getCreatorBalance(address _creator) 
        external 
        view 
        returns (uint256) 
    {
        return creatorBalances[_creator];
    }
    
    /**
     * @notice Actualizar fee de la plataforma (solo owner)
     * @param _newFee Nuevo fee (en basis points, ej: 250 = 2.5%)
     */
    function updatePlatformFee(uint256 _newFee) external onlyOwner {
        if (_newFee > MAX_FEE) revert InvalidFee();
        
        uint256 oldFee = platformFee;
        platformFee = _newFee;
        
        emit PlatformFeeUpdated(oldFee, _newFee, block.timestamp);
    }
    
    /**
     * @notice Actualizar wallet de la plataforma (solo owner)
     * @param _newWallet Nueva dirección
     */
    function updatePlatformWallet(address _newWallet) external onlyOwner {
        if (_newWallet == address(0)) revert InvalidAddress();
        
        address oldWallet = platformWallet;
        platformWallet = _newWallet;
        
        emit PlatformWalletUpdated(oldWallet, _newWallet, block.timestamp);
    }
    
    /**
     * @notice Obtener estadísticas globales
     * @return totalContents Total de contenidos
     * @return totalActiveContents Contenidos activos
     */
    function getGlobalStats() 
        external 
        view 
        returns (
            uint256 totalContents,
            uint256 totalActiveContents
        ) 
    {
        totalContents = nextContentId - 1;
        
        for (uint256 i = 1; i < nextContentId; i++) {
            if (contents[i].active) {
                totalActiveContents++;
            }
        }
        
        return (totalContents, totalActiveContents);
    }
}
