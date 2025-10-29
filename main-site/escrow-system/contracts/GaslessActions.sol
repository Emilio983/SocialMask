// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

/**
 * @title GaslessActions
 * @dev Contrato para acciones 1-clic sin gas (propinas, pagos, votos, etc.)
 * Compatible con Account Abstraction y Gelato Relay
 */
contract GaslessActions is ReentrancyGuard, Ownable {
    IERC20 public immutable spheToken;
    
    // Tipos de acciones disponibles
    enum ActionType {
        TIP,           // Propina a contenido
        PAYMENT,       // Pago directo
        UNLOCK,        // Desbloquear contenido premium
        VOTE,          // Votar en encuesta/gobernanza
        DONATION,      // Donación a periodista
        BOUNTY_CLAIM   // Reclamar bounty
    }
    
    // Registro de acciones ejecutadas
    struct Action {
        address sender;
        address recipient;
        ActionType actionType;
        uint256 amount;
        uint256 timestamp;
        string metadata; // JSON con info adicional
    }
    
    // Mapping de acciones por ID
    mapping(bytes32 => Action) public actions;
    
    // Mapping de acciones por usuario (sender)
    mapping(address => bytes32[]) public userActions;
    
    // Mapping de acciones recibidas por usuario (recipient)
    mapping(address => bytes32[]) public receivedActions;
    
    // Contador de acciones
    uint256 public actionCount;
    
    // Fees del protocolo (en basis points: 100 = 1%)
    uint256 public platformFee = 250; // 2.5% por defecto
    address public treasury;
    
    // Límites por tipo de acción (para prevenir abuso)
    mapping(ActionType => uint256) public maxAmountPerAction;
    
    // Events
    event ActionExecuted(
        bytes32 indexed actionId,
        address indexed sender,
        address indexed recipient,
        ActionType actionType,
        uint256 amount,
        uint256 fee,
        string metadata
    );
    
    event FeeUpdated(uint256 oldFee, uint256 newFee);
    event TreasuryUpdated(address oldTreasury, address newTreasury);
    event MaxAmountUpdated(ActionType actionType, uint256 maxAmount);
    
    constructor(address _spheToken, address _treasury) Ownable(msg.sender) {
        require(_spheToken != address(0), "Invalid SPHE token");
        require(_treasury != address(0), "Invalid treasury");
        
        spheToken = IERC20(_spheToken);
        treasury = _treasury;
        
        // Configurar límites por defecto (en SPHE tokens)
        maxAmountPerAction[ActionType.TIP] = 1000 * 10**18;        // 1000 SPHE
        maxAmountPerAction[ActionType.PAYMENT] = 10000 * 10**18;   // 10000 SPHE
        maxAmountPerAction[ActionType.UNLOCK] = 500 * 10**18;      // 500 SPHE
        maxAmountPerAction[ActionType.VOTE] = 100 * 10**18;        // 100 SPHE
        maxAmountPerAction[ActionType.DONATION] = 50000 * 10**18;  // 50000 SPHE
        maxAmountPerAction[ActionType.BOUNTY_CLAIM] = 100000 * 10**18; // 100000 SPHE
    }
    
    /**
     * @dev Ejecutar una acción gasless
     * @param recipient Dirección que recibirá los tokens
     * @param actionType Tipo de acción a ejecutar
     * @param amount Cantidad de SPHE a transferir
     * @param metadata JSON con información adicional
     */
    function executeAction(
        address recipient,
        ActionType actionType,
        uint256 amount,
        string calldata metadata
    ) external nonReentrant returns (bytes32) {
        require(recipient != address(0), "Invalid recipient");
        require(amount > 0, "Amount must be > 0");
        require(amount <= maxAmountPerAction[actionType], "Amount exceeds limit");
        
        // Verificar que el sender tiene suficientes tokens
        require(spheToken.balanceOf(msg.sender) >= amount, "Insufficient balance");
        
        // Calcular fee
        uint256 fee = (amount * platformFee) / 10000;
        uint256 netAmount = amount - fee;
        
        // Transferir tokens del sender al contrato
        require(
            spheToken.transferFrom(msg.sender, address(this), amount),
            "Transfer failed"
        );
        
        // Transferir net amount al recipient
        require(
            spheToken.transfer(recipient, netAmount),
            "Transfer to recipient failed"
        );
        
        // Transferir fee al treasury
        if (fee > 0) {
            require(
                spheToken.transfer(treasury, fee),
                "Fee transfer failed"
            );
        }
        
        // Generar action ID único
        actionCount++;
        bytes32 actionId = keccak256(
            abi.encodePacked(
                msg.sender,
                recipient,
                actionType,
                amount,
                block.timestamp,
                actionCount
            )
        );
        
        // Guardar acción
        actions[actionId] = Action({
            sender: msg.sender,
            recipient: recipient,
            actionType: actionType,
            amount: amount,
            timestamp: block.timestamp,
            metadata: metadata
        });
        
        // Agregar a mappings de usuarios
        userActions[msg.sender].push(actionId);
        receivedActions[recipient].push(actionId);
        
        emit ActionExecuted(
            actionId,
            msg.sender,
            recipient,
            actionType,
            netAmount,
            fee,
            metadata
        );
        
        return actionId;
    }
    
    /**
     * @dev Batch de acciones (para ejecutar múltiples a la vez)
     */
    function executeBatchActions(
        address[] calldata recipients,
        ActionType[] calldata actionTypes,
        uint256[] calldata amounts,
        string[] calldata metadatas
    ) external nonReentrant returns (bytes32[] memory) {
        require(
            recipients.length == actionTypes.length &&
            recipients.length == amounts.length &&
            recipients.length == metadatas.length,
            "Array lengths mismatch"
        );
        require(recipients.length <= 10, "Max 10 actions per batch");
        
        bytes32[] memory actionIds = new bytes32[](recipients.length);
        
        for (uint256 i = 0; i < recipients.length; i++) {
            // Llamar a executeAction para cada elemento
            // Nota: esto gastará más gas pero mantiene la lógica centralizada
            bytes32 actionId = this.executeAction(
                recipients[i],
                actionTypes[i],
                amounts[i],
                metadatas[i]
            );
            actionIds[i] = actionId;
        }
        
        return actionIds;
    }
    
    /**
     * @dev Obtener acciones ejecutadas por un usuario
     */
    function getUserActions(address user, uint256 limit) 
        external 
        view 
        returns (Action[] memory) 
    {
        bytes32[] memory actionIds = userActions[user];
        uint256 count = actionIds.length > limit ? limit : actionIds.length;
        
        Action[] memory result = new Action[](count);
        for (uint256 i = 0; i < count; i++) {
            result[i] = actions[actionIds[actionIds.length - 1 - i]]; // Más recientes primero
        }
        
        return result;
    }
    
    /**
     * @dev Obtener acciones recibidas por un usuario
     */
    function getReceivedActions(address user, uint256 limit) 
        external 
        view 
        returns (Action[] memory) 
    {
        bytes32[] memory actionIds = receivedActions[user];
        uint256 count = actionIds.length > limit ? limit : actionIds.length;
        
        Action[] memory result = new Action[](count);
        for (uint256 i = 0; i < count; i++) {
            result[i] = actions[actionIds[actionIds.length - 1 - i]];
        }
        
        return result;
    }
    
    // ========== ADMIN FUNCTIONS ==========
    
    function updatePlatformFee(uint256 newFee) external onlyOwner {
        require(newFee <= 1000, "Fee cannot exceed 10%"); // Max 10%
        uint256 oldFee = platformFee;
        platformFee = newFee;
        emit FeeUpdated(oldFee, newFee);
    }
    
    function updateTreasury(address newTreasury) external onlyOwner {
        require(newTreasury != address(0), "Invalid treasury");
        address oldTreasury = treasury;
        treasury = newTreasury;
        emit TreasuryUpdated(oldTreasury, newTreasury);
    }
    
    function updateMaxAmount(ActionType actionType, uint256 maxAmount) external onlyOwner {
        maxAmountPerAction[actionType] = maxAmount;
        emit MaxAmountUpdated(actionType, maxAmount);
    }
    
    /**
     * @dev Emergency withdraw (solo en caso de problemas)
     */
    function emergencyWithdraw() external onlyOwner {
        uint256 balance = spheToken.balanceOf(address(this));
        require(balance > 0, "No balance to withdraw");
        require(spheToken.transfer(treasury, balance), "Withdrawal failed");
    }
}
