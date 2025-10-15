// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import "@openzeppelin/contracts/token/ERC20/extensions/ERC20Votes.sol";
import "@openzeppelin/contracts/token/ERC20/extensions/ERC20Permit.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

/**
 * @title GovernanceToken
 * @dev Token de gobernanza para el DAO de Sphera
 * @notice Los holders de este token pueden votar en propuestas de gobernanza
 * 
 * Características:
 * - ERC20Votes: Permite votación ponderada por balance
 * - Checkpoints: Sistema de snapshots para votos históricos
 * - Delegación: Los usuarios pueden delegar su poder de voto
 * - Permit: Permite aprobaciones sin gas (EIP-2612)
 * 
 * Distribución:
 * - 1 GOV token por cada 100 SPHE stakeados
 * - Minteable solo por contratos autorizados
 * - No hay supply máximo (minteable según staking)
 */
contract GovernanceToken is ERC20, ERC20Votes, ERC20Permit, Ownable {
    
    // ============================================
    // STATE VARIABLES
    // ============================================
    
    /// @notice Contratos autorizados para mintear tokens
    mapping(address => bool) public minters;
    
    /// @notice Total de tokens minteados por cada usuario
    mapping(address => uint256) public totalMinted;
    
    /// @notice Total de tokens quemados por cada usuario
    mapping(address => uint256) public totalBurned;
    
    // ============================================
    // EVENTS
    // ============================================
    
    event MinterAdded(address indexed minter);
    event MinterRemoved(address indexed minter);
    event TokensMinted(address indexed to, uint256 amount, address indexed minter);
    event TokensBurned(address indexed from, uint256 amount);
    
    // ============================================
    // ERRORS
    // ============================================
    
    error NotAuthorizedMinter();
    error ZeroAddress();
    error ZeroAmount();
    
    // ============================================
    // CONSTRUCTOR
    // ============================================
    
    /**
     * @dev Constructor del token de gobernanza
     * @param _initialOwner Dirección del owner inicial
     */
    constructor(address _initialOwner)
        ERC20("Sphera Governance Token", "GOVSPHE")
        ERC20Permit("Sphera Governance Token")
        Ownable(_initialOwner)
    {
        // El owner es minter por defecto
        minters[_initialOwner] = true;
        emit MinterAdded(_initialOwner);
    }
    
    // ============================================
    // MINTING FUNCTIONS
    // ============================================
    
    /**
     * @notice Mintear tokens de gobernanza
     * @dev Solo puede ser llamado por contratos autorizados (minters)
     * @param to Dirección que recibirá los tokens
     * @param amount Cantidad de tokens a mintear
     */
    function mint(address to, uint256 amount) external {
        if (!minters[msg.sender]) revert NotAuthorizedMinter();
        if (to == address(0)) revert ZeroAddress();
        if (amount == 0) revert ZeroAmount();
        
        _mint(to, amount);
        totalMinted[to] += amount;
        
        emit TokensMinted(to, amount, msg.sender);
    }
    
    /**
     * @notice Quemar tokens de gobernanza
     * @dev Cualquier holder puede quemar sus propios tokens
     * @param amount Cantidad de tokens a quemar
     */
    function burn(uint256 amount) external {
        if (amount == 0) revert ZeroAmount();
        
        _burn(msg.sender, amount);
        totalBurned[msg.sender] += amount;
        
        emit TokensBurned(msg.sender, amount);
    }
    
    /**
     * @notice Quemar tokens de otra dirección (con aprobación)
     * @param from Dirección de la cual quemar tokens
     * @param amount Cantidad de tokens a quemar
     */
    function burnFrom(address from, uint256 amount) external {
        if (amount == 0) revert ZeroAmount();
        if (from == address(0)) revert ZeroAddress();
        
        _spendAllowance(from, msg.sender, amount);
        _burn(from, amount);
        totalBurned[from] += amount;
        
        emit TokensBurned(from, amount);
    }
    
    // ============================================
    // ADMIN FUNCTIONS
    // ============================================
    
    /**
     * @notice Agregar un contrato como minter autorizado
     * @param minter Dirección del contrato minter
     */
    function addMinter(address minter) external onlyOwner {
        if (minter == address(0)) revert ZeroAddress();
        minters[minter] = true;
        emit MinterAdded(minter);
    }
    
    /**
     * @notice Remover un contrato minter
     * @param minter Dirección del contrato minter a remover
     */
    function removeMinter(address minter) external onlyOwner {
        if (minter == address(0)) revert ZeroAddress();
        minters[minter] = false;
        emit MinterRemoved(minter);
    }
    
    // ============================================
    // VIEW FUNCTIONS
    // ============================================
    
    /**
     * @notice Obtener el poder de voto de una dirección en el bloque actual
     * @param account Dirección a consultar
     * @return Poder de voto (balance + delegado)
     */
    function getVotingPower(address account) external view returns (uint256) {
        return getVotes(account);
    }
    
    /**
     * @notice Obtener el poder de voto de una dirección en un bloque específico
     * @param account Dirección a consultar
     * @param blockNumber Número de bloque
     * @return Poder de voto en ese bloque
     */
    function getPastVotingPower(address account, uint256 blockNumber) 
        external 
        view 
        returns (uint256) 
    {
        return getPastVotes(account, blockNumber);
    }
    
    /**
     * @notice Obtener estadísticas de un usuario
     * @param account Dirección a consultar
     * @return balance Balance actual
     * @return votingPower Poder de voto actual
     * @return minted Total minteado
     * @return burned Total quemado
     * @return delegatee A quién delegó (address(0) si es self-delegate)
     */
    function getUserStats(address account) 
        external 
        view 
        returns (
            uint256 balance,
            uint256 votingPower,
            uint256 minted,
            uint256 burned,
            address delegatee
        )
    {
        balance = balanceOf(account);
        votingPower = getVotes(account);
        minted = totalMinted[account];
        burned = totalBurned[account];
        delegatee = delegates(account);
    }
    
    // ============================================
    // DELEGATION HELPERS
    // ============================================
    
    /**
     * @notice Delegar votos a otra dirección
     * @dev Wrapper sobre delegate() para mejor UX
     * @param delegatee Dirección a la que delegar
     */
    function delegateVotes(address delegatee) external {
        delegate(delegatee);
    }
    
    /**
     * @notice Delegar votos a uno mismo (self-delegate)
     * @dev Necesario para activar el poder de voto
     */
    function selfDelegate() external {
        delegate(msg.sender);
    }
    
    // ============================================
    // INTERNAL OVERRIDES (Required by Solidity)
    // ============================================
    
    /**
     * @dev Override requerido por ERC20Votes
     */
    function _update(address from, address to, uint256 amount)
        internal
        override(ERC20, ERC20Votes)
    {
        super._update(from, to, amount);
    }
    
    /**
     * @dev Override requerido por ERC20Votes
     */
    function nonces(address owner)
        public
        view
        override(ERC20Permit, Nonces)
        returns (uint256)
    {
        return super.nonces(owner);
    }
}
