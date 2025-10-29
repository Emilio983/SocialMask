// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/access/AccessControl.sol";
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";
import "@openzeppelin/contracts/security/ReentrancyGuard.sol";
import "@openzeppelin/contracts/security/Pausable.sol";

/**
 * @title TreasuryManagement
 * @dev Comprehensive treasury management with multi-token support,
 * budgets, scheduled payments, and department allocations
 */
contract TreasuryManagement is AccessControl, ReentrancyGuard, Pausable {
    using SafeERC20 for IERC20;

    // ============================================
    // ROLES
    // ============================================

    bytes32 public constant TREASURER_ROLE = keccak256("TREASURER_ROLE");
    bytes32 public constant APPROVER_ROLE = keccak256("APPROVER_ROLE");
    bytes32 public constant AUDITOR_ROLE = keccak256("AUDITOR_ROLE");

    // ============================================
    // STRUCTS
    // ============================================

    enum PaymentStatus {
        PENDING,
        APPROVED,
        EXECUTED,
        REJECTED,
        CANCELLED
    }

    enum PaymentType {
        ONE_TIME,
        RECURRING,
        STREAMING
    }

    struct Payment {
        uint256 id;
        address recipient;
        address token;
        uint256 amount;
        PaymentType paymentType;
        PaymentStatus status;
        string description;
        string department;
        uint256 createdAt;
        uint256 scheduledAt;
        uint256 executedAt;
        address createdBy;
        address approvedBy;
        uint256 recurringInterval;
        uint256 streamingDuration;
        uint256 streamingStartTime;
        uint256 streamingWithdrawn;
    }

    struct Budget {
        uint256 allocated;
        uint256 spent;
        uint256 period;
        bool active;
    }

    struct TokenBalance {
        uint256 balance;
        uint256 reserved;
        uint256 available;
    }

    // ============================================
    // STATE VARIABLES
    // ============================================

    uint256 public paymentCount;
    mapping(uint256 => Payment) public payments;
    
    // Department budgets: department => token => budget
    mapping(string => mapping(address => Budget)) public departmentBudgets;
    
    // Token balances
    mapping(address => TokenBalance) public tokenBalances;
    address[] public supportedTokens;
    mapping(address => bool) public isTokenSupported;
    
    // Payment approvals
    mapping(uint256 => mapping(address => bool)) public paymentApprovals;
    mapping(uint256 => uint256) public approvalCount;
    uint256 public requiredApprovals = 2;
    
    // Limits
    uint256 public singlePaymentLimit = 10000 * 10**18; // 10,000 tokens
    uint256 public dailyWithdrawalLimit = 50000 * 10**18; // 50,000 tokens
    mapping(address => uint256) public dailyWithdrawals;
    mapping(address => uint256) public lastWithdrawalDay;

    // ============================================
    // EVENTS
    // ============================================

    event PaymentCreated(
        uint256 indexed paymentId,
        address indexed recipient,
        address token,
        uint256 amount,
        PaymentType paymentType
    );

    event PaymentApproved(
        uint256 indexed paymentId,
        address indexed approver
    );

    event PaymentExecuted(
        uint256 indexed paymentId,
        address indexed recipient,
        uint256 amount
    );

    event PaymentRejected(uint256 indexed paymentId, string reason);
    event PaymentCancelled(uint256 indexed paymentId);

    event BudgetAllocated(
        string indexed department,
        address indexed token,
        uint256 amount
    );

    event TokenAdded(address indexed token);
    event TokenRemoved(address indexed token);

    event Deposit(address indexed token, uint256 amount, address indexed from);
    event StreamingWithdrawal(uint256 indexed paymentId, uint256 amount);

    // ============================================
    // CONSTRUCTOR
    // ============================================

    constructor() {
        _grantRole(DEFAULT_ADMIN_ROLE, msg.sender);
        _grantRole(TREASURER_ROLE, msg.sender);
        _grantRole(APPROVER_ROLE, msg.sender);
        _grantRole(AUDITOR_ROLE, msg.sender);
    }

    // ============================================
    // PAYMENT MANAGEMENT
    // ============================================

    function createPayment(
        address _recipient,
        address _token,
        uint256 _amount,
        PaymentType _paymentType,
        string memory _description,
        string memory _department,
        uint256 _scheduledAt
    ) external onlyRole(TREASURER_ROLE) returns (uint256) {
        require(isTokenSupported[_token], "Token not supported");
        require(_recipient != address(0), "Invalid recipient");
        require(_amount > 0, "Amount must be > 0");
        
        // Check department budget
        if (bytes(_department).length > 0) {
            Budget storage budget = departmentBudgets[_department][_token];
            require(budget.active, "Department budget not active");
            require(budget.spent + _amount <= budget.allocated, "Budget exceeded");
        }
        
        uint256 paymentId = paymentCount++;
        Payment storage payment = payments[paymentId];
        
        payment.id = paymentId;
        payment.recipient = _recipient;
        payment.token = _token;
        payment.amount = _amount;
        payment.paymentType = _paymentType;
        payment.status = PaymentStatus.PENDING;
        payment.description = _description;
        payment.department = _department;
        payment.createdAt = block.timestamp;
        payment.scheduledAt = _scheduledAt > 0 ? _scheduledAt : block.timestamp;
        payment.createdBy = msg.sender;
        
        // Reserve funds
        tokenBalances[_token].reserved += _amount;
        tokenBalances[_token].available -= _amount;
        
        emit PaymentCreated(paymentId, _recipient, _token, _amount, _paymentType);
        
        return paymentId;
    }

    function approvePayment(uint256 paymentId) 
        external 
        onlyRole(APPROVER_ROLE) 
    {
        Payment storage payment = payments[paymentId];
        require(payment.status == PaymentStatus.PENDING, "Payment not pending");
        require(!paymentApprovals[paymentId][msg.sender], "Already approved");
        
        paymentApprovals[paymentId][msg.sender] = true;
        approvalCount[paymentId]++;
        
        emit PaymentApproved(paymentId, msg.sender);
        
        if (approvalCount[paymentId] >= requiredApprovals) {
            payment.status = PaymentStatus.APPROVED;
            payment.approvedBy = msg.sender;
        }
    }

    function executePayment(uint256 paymentId) 
        external 
        nonReentrant 
        whenNotPaused 
    {
        Payment storage payment = payments[paymentId];
        require(payment.status == PaymentStatus.APPROVED, "Payment not approved");
        require(block.timestamp >= payment.scheduledAt, "Too early");
        
        // Check daily limit
        _checkDailyLimit(payment.token, payment.amount);
        
        payment.status = PaymentStatus.EXECUTED;
        payment.executedAt = block.timestamp;
        
        // Update balances
        tokenBalances[payment.token].reserved -= payment.amount;
        
        // Update budget
        if (bytes(payment.department).length > 0) {
            departmentBudgets[payment.department][payment.token].spent += payment.amount;
        }
        
        // Transfer tokens
        if (payment.token == address(0)) {
            // ETH transfer
            (bool success, ) = payment.recipient.call{value: payment.amount}("");
            require(success, "ETH transfer failed");
        } else {
            // ERC20 transfer
            IERC20(payment.token).safeTransfer(payment.recipient, payment.amount);
        }
        
        emit PaymentExecuted(paymentId, payment.recipient, payment.amount);
    }

    function rejectPayment(uint256 paymentId, string memory reason) 
        external 
        onlyRole(APPROVER_ROLE) 
    {
        Payment storage payment = payments[paymentId];
        require(payment.status == PaymentStatus.PENDING, "Payment not pending");
        
        payment.status = PaymentStatus.REJECTED;
        
        // Unreserve funds
        tokenBalances[payment.token].reserved -= payment.amount;
        tokenBalances[payment.token].available += payment.amount;
        
        emit PaymentRejected(paymentId, reason);
    }

    function cancelPayment(uint256 paymentId) 
        external 
        onlyRole(TREASURER_ROLE) 
    {
        Payment storage payment = payments[paymentId];
        require(
            payment.status == PaymentStatus.PENDING || 
            payment.status == PaymentStatus.APPROVED,
            "Cannot cancel"
        );
        
        payment.status = PaymentStatus.CANCELLED;
        
        // Unreserve funds
        tokenBalances[payment.token].reserved -= payment.amount;
        tokenBalances[payment.token].available += payment.amount;
        
        emit PaymentCancelled(paymentId);
    }

    // ============================================
    // STREAMING PAYMENTS
    // ============================================

    function createStreamingPayment(
        address _recipient,
        address _token,
        uint256 _totalAmount,
        uint256 _duration,
        string memory _description,
        string memory _department
    ) external onlyRole(TREASURER_ROLE) returns (uint256) {
        uint256 paymentId = createPayment(
            _recipient,
            _token,
            _totalAmount,
            PaymentType.STREAMING,
            _description,
            _department,
            block.timestamp
        );
        
        Payment storage payment = payments[paymentId];
        payment.streamingDuration = _duration;
        payment.streamingStartTime = block.timestamp;
        
        return paymentId;
    }

    function withdrawStreaming(uint256 paymentId) external nonReentrant {
        Payment storage payment = payments[paymentId];
        require(payment.paymentType == PaymentType.STREAMING, "Not streaming");
        require(payment.status == PaymentStatus.APPROVED, "Not approved");
        require(msg.sender == payment.recipient, "Not recipient");
        
        uint256 available = getStreamingAvailable(paymentId);
        require(available > 0, "No funds available");
        
        payment.streamingWithdrawn += available;
        
        // Transfer
        if (payment.token == address(0)) {
            (bool success, ) = payment.recipient.call{value: available}("");
            require(success, "Transfer failed");
        } else {
            IERC20(payment.token).safeTransfer(payment.recipient, available);
        }
        
        emit StreamingWithdrawal(paymentId, available);
        
        // Mark as executed if fully withdrawn
        if (payment.streamingWithdrawn >= payment.amount) {
            payment.status = PaymentStatus.EXECUTED;
            payment.executedAt = block.timestamp;
            tokenBalances[payment.token].reserved -= payment.amount;
        }
    }

    function getStreamingAvailable(uint256 paymentId) 
        public 
        view 
        returns (uint256) 
    {
        Payment storage payment = payments[paymentId];
        
        if (payment.paymentType != PaymentType.STREAMING) return 0;
        if (payment.status != PaymentStatus.APPROVED) return 0;
        
        uint256 elapsed = block.timestamp - payment.streamingStartTime;
        uint256 totalAvailable;
        
        if (elapsed >= payment.streamingDuration) {
            totalAvailable = payment.amount;
        } else {
            totalAvailable = (payment.amount * elapsed) / payment.streamingDuration;
        }
        
        return totalAvailable - payment.streamingWithdrawn;
    }

    // ============================================
    // BUDGET MANAGEMENT
    // ============================================

    function allocateBudget(
        string memory department,
        address token,
        uint256 amount,
        uint256 period
    ) external onlyRole(TREASURER_ROLE) {
        require(isTokenSupported[token], "Token not supported");
        
        Budget storage budget = departmentBudgets[department][token];
        budget.allocated = amount;
        budget.period = period;
        budget.active = true;
        budget.spent = 0;
        
        emit BudgetAllocated(department, token, amount);
    }

    function getBudgetStatus(string memory department, address token)
        external
        view
        returns (
            uint256 allocated,
            uint256 spent,
            uint256 remaining,
            bool active
        )
    {
        Budget storage budget = departmentBudgets[department][token];
        allocated = budget.allocated;
        spent = budget.spent;
        remaining = budget.allocated > budget.spent ? budget.allocated - budget.spent : 0;
        active = budget.active;
    }

    // ============================================
    // TOKEN MANAGEMENT
    // ============================================

    function addToken(address token) external onlyRole(TREASURER_ROLE) {
        require(!isTokenSupported[token], "Token already supported");
        
        isTokenSupported[token] = true;
        supportedTokens.push(token);
        
        emit TokenAdded(token);
    }

    function removeToken(address token) external onlyRole(TREASURER_ROLE) {
        require(isTokenSupported[token], "Token not supported");
        require(tokenBalances[token].balance == 0, "Token has balance");
        
        isTokenSupported[token] = false;
        
        emit TokenRemoved(token);
    }

    function deposit(address token, uint256 amount) external nonReentrant {
        require(isTokenSupported[token], "Token not supported");
        require(amount > 0, "Amount must be > 0");
        
        IERC20(token).safeTransferFrom(msg.sender, address(this), amount);
        
        tokenBalances[token].balance += amount;
        tokenBalances[token].available += amount;
        
        emit Deposit(token, amount, msg.sender);
    }

    // ============================================
    // INTERNAL FUNCTIONS
    // ============================================

    function _checkDailyLimit(address token, uint256 amount) internal {
        uint256 currentDay = block.timestamp / 1 days;
        
        if (lastWithdrawalDay[token] < currentDay) {
            dailyWithdrawals[token] = 0;
            lastWithdrawalDay[token] = currentDay;
        }
        
        require(
            dailyWithdrawals[token] + amount <= dailyWithdrawalLimit,
            "Daily limit exceeded"
        );
        
        dailyWithdrawals[token] += amount;
    }

    // ============================================
    // VIEW FUNCTIONS
    // ============================================

    function getPayment(uint256 paymentId)
        external
        view
        returns (
            address recipient,
            address token,
            uint256 amount,
            PaymentType paymentType,
            PaymentStatus status,
            string memory description,
            uint256 scheduledAt
        )
    {
        Payment storage payment = payments[paymentId];
        return (
            payment.recipient,
            payment.token,
            payment.amount,
            payment.paymentType,
            payment.status,
            payment.description,
            payment.scheduledAt
        );
    }

    function getTreasuryBalance(address token) 
        external 
        view 
        returns (uint256 balance, uint256 reserved, uint256 available) 
    {
        TokenBalance storage tb = tokenBalances[token];
        return (tb.balance, tb.reserved, tb.available);
    }

    function getSupportedTokens() external view returns (address[] memory) {
        return supportedTokens;
    }

    // ============================================
    // ADMIN FUNCTIONS
    // ============================================

    function setRequiredApprovals(uint256 _required) 
        external 
        onlyRole(DEFAULT_ADMIN_ROLE) 
    {
        require(_required > 0, "Must require at least 1");
        requiredApprovals = _required;
    }

    function setSinglePaymentLimit(uint256 _limit) 
        external 
        onlyRole(DEFAULT_ADMIN_ROLE) 
    {
        singlePaymentLimit = _limit;
    }

    function setDailyWithdrawalLimit(uint256 _limit) 
        external 
        onlyRole(DEFAULT_ADMIN_ROLE) 
    {
        dailyWithdrawalLimit = _limit;
    }

    function pause() external onlyRole(DEFAULT_ADMIN_ROLE) {
        _pause();
    }

    function unpause() external onlyRole(DEFAULT_ADMIN_ROLE) {
        _unpause();
    }

    // ============================================
    // RECEIVE ETH
    // ============================================

    receive() external payable {
        tokenBalances[address(0)].balance += msg.value;
        tokenBalances[address(0)].available += msg.value;
        emit Deposit(address(0), msg.value, msg.sender);
    }
}
