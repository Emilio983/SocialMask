// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/access/Ownable.sol";

/**
 * @title ProposalTemplates
 * @dev Predefined templates for common governance proposals
 * Makes it easy to create standardized proposals
 */
contract ProposalTemplates is Ownable {
    
    // ============================================
    // STRUCTS
    // ============================================

    enum TemplateCategory {
        TREASURY,
        PARAMETER,
        MEMBER,
        EMERGENCY,
        UPGRADE,
        CUSTOM
    }

    struct Template {
        uint256 id;
        string name;
        string description;
        TemplateCategory category;
        string[] fieldNames;
        string[] fieldTypes; // address, uint256, string, bool
        string[] fieldDescriptions;
        bool isActive;
        uint256 usageCount;
        address createdBy;
        uint256 createdAt;
    }

    // ============================================
    // STATE VARIABLES
    // ============================================

    uint256 public templateCount;
    mapping(uint256 => Template) public templates;
    mapping(string => uint256) public templateByName;
    
    uint256[] public activeTemplateIds;
    mapping(TemplateCategory => uint256[]) public templatesByCategory;

    // ============================================
    // EVENTS
    // ============================================

    event TemplateCreated(
        uint256 indexed templateId,
        string name,
        TemplateCategory category
    );
    
    event TemplateUsed(uint256 indexed templateId);
    event TemplateActivated(uint256 indexed templateId);
    event TemplateDeactivated(uint256 indexed templateId);

    // ============================================
    // CONSTRUCTOR
    // ============================================

    constructor() {
        _createDefaultTemplates();
    }

    // ============================================
    // TEMPLATE CREATION
    // ============================================

    function createTemplate(
        string memory _name,
        string memory _description,
        TemplateCategory _category,
        string[] memory _fieldNames,
        string[] memory _fieldTypes,
        string[] memory _fieldDescriptions
    ) external onlyOwner returns (uint256) {
        require(_fieldNames.length == _fieldTypes.length, "Field arrays mismatch");
        require(_fieldNames.length == _fieldDescriptions.length, "Description array mismatch");
        
        uint256 templateId = templateCount++;
        
        Template storage template = templates[templateId];
        template.id = templateId;
        template.name = _name;
        template.description = _description;
        template.category = _category;
        template.fieldNames = _fieldNames;
        template.fieldTypes = _fieldTypes;
        template.fieldDescriptions = _fieldDescriptions;
        template.isActive = true;
        template.createdBy = msg.sender;
        template.createdAt = block.timestamp;
        
        templateByName[_name] = templateId;
        activeTemplateIds.push(templateId);
        templatesByCategory[_category].push(templateId);
        
        emit TemplateCreated(templateId, _name, _category);
        
        return templateId;
    }

    // ============================================
    // TEMPLATE USAGE
    // ============================================

    function useTemplate(uint256 templateId) external {
        require(templateId < templateCount, "Template does not exist");
        require(templates[templateId].isActive, "Template not active");
        
        templates[templateId].usageCount++;
        emit TemplateUsed(templateId);
    }

    function activateTemplate(uint256 templateId) external onlyOwner {
        require(templateId < templateCount, "Template does not exist");
        templates[templateId].isActive = true;
        emit TemplateActivated(templateId);
    }

    function deactivateTemplate(uint256 templateId) external onlyOwner {
        require(templateId < templateCount, "Template does not exist");
        templates[templateId].isActive = false;
        emit TemplateDeactivated(templateId);
    }

    // ============================================
    // VIEW FUNCTIONS
    // ============================================

    function getTemplate(uint256 templateId)
        external
        view
        returns (
            string memory name,
            string memory description,
            TemplateCategory category,
            string[] memory fieldNames,
            string[] memory fieldTypes,
            string[] memory fieldDescriptions,
            bool isActive,
            uint256 usageCount
        )
    {
        Template storage template = templates[templateId];
        return (
            template.name,
            template.description,
            template.category,
            template.fieldNames,
            template.fieldTypes,
            template.fieldDescriptions,
            template.isActive,
            template.usageCount
        );
    }

    function getActiveTemplates() external view returns (uint256[] memory) {
        return activeTemplateIds;
    }

    function getTemplatesByCategory(TemplateCategory category)
        external
        view
        returns (uint256[] memory)
    {
        return templatesByCategory[category];
    }

    function getTemplateIdByName(string memory name)
        external
        view
        returns (uint256)
    {
        return templateByName[name];
    }

    // ============================================
    // DEFAULT TEMPLATES
    // ============================================

    function _createDefaultTemplates() internal {
        // 1. Treasury Withdrawal Template
        string[] memory treasuryFields = new string[](3);
        treasuryFields[0] = "recipient";
        treasuryFields[1] = "amount";
        treasuryFields[2] = "reason";
        
        string[] memory treasuryTypes = new string[](3);
        treasuryTypes[0] = "address";
        treasuryTypes[1] = "uint256";
        treasuryTypes[2] = "string";
        
        string[] memory treasuryDescriptions = new string[](3);
        treasuryDescriptions[0] = "Recipient address";
        treasuryDescriptions[1] = "Amount to withdraw";
        treasuryDescriptions[2] = "Reason for withdrawal";
        
        _createTemplate(
            "Treasury Withdrawal",
            "Withdraw funds from treasury to a specific address",
            TemplateCategory.TREASURY,
            treasuryFields,
            treasuryTypes,
            treasuryDescriptions
        );
        
        // 2. Change Voting Period Template
        string[] memory votingFields = new string[](1);
        votingFields[0] = "newPeriod";
        
        string[] memory votingTypes = new string[](1);
        votingTypes[0] = "uint256";
        
        string[] memory votingDescriptions = new string[](1);
        votingDescriptions[0] = "New voting period in days";
        
        _createTemplate(
            "Change Voting Period",
            "Modify the duration of voting periods",
            TemplateCategory.PARAMETER,
            votingFields,
            votingTypes,
            votingDescriptions
        );
        
        // 3. Add Team Member Template
        string[] memory memberFields = new string[](3);
        memberFields[0] = "memberAddress";
        memberFields[1] = "role";
        memberFields[2] = "salary";
        
        string[] memory memberTypes = new string[](3);
        memberTypes[0] = "address";
        memberTypes[1] = "string";
        memberTypes[2] = "uint256";
        
        string[] memory memberDescriptions = new string[](3);
        memberDescriptions[0] = "Member wallet address";
        memberDescriptions[1] = "Role (Developer, Designer, etc.)";
        memberDescriptions[2] = "Monthly salary in tokens";
        
        _createTemplate(
            "Add Team Member",
            "Onboard a new team member with salary allocation",
            TemplateCategory.MEMBER,
            memberFields,
            memberTypes,
            memberDescriptions
        );
        
        // 4. Emergency Pause Template
        string[] memory emergencyFields = new string[](1);
        emergencyFields[0] = "reason";
        
        string[] memory emergencyTypes = new string[](1);
        emergencyTypes[0] = "string";
        
        string[] memory emergencyDescriptions = new string[](1);
        emergencyDescriptions[0] = "Reason for emergency pause";
        
        _createTemplate(
            "Emergency Pause",
            "Pause contract operations in case of emergency",
            TemplateCategory.EMERGENCY,
            emergencyFields,
            emergencyTypes,
            emergencyDescriptions
        );
        
        // 5. Contract Upgrade Template
        string[] memory upgradeFields = new string[](2);
        upgradeFields[0] = "newImplementation";
        upgradeFields[1] = "upgradeReason";
        
        string[] memory upgradeTypes = new string[](2);
        upgradeTypes[0] = "address";
        upgradeTypes[1] = "string";
        
        string[] memory upgradeDescriptions = new string[](2);
        upgradeDescriptions[0] = "New implementation contract address";
        upgradeDescriptions[1] = "Reason for upgrade";
        
        _createTemplate(
            "Contract Upgrade",
            "Upgrade contract to new implementation",
            TemplateCategory.UPGRADE,
            upgradeFields,
            upgradeTypes,
            upgradeDescriptions
        );
        
        // 6. Change Quorum Template
        string[] memory quorumFields = new string[](1);
        quorumFields[0] = "newQuorum";
        
        string[] memory quorumTypes = new string[](1);
        quorumTypes[0] = "uint256";
        
        string[] memory quorumDescriptions = new string[](1);
        quorumDescriptions[0] = "New quorum percentage (0-100)";
        
        _createTemplate(
            "Change Quorum",
            "Modify the quorum required for proposal passage",
            TemplateCategory.PARAMETER,
            quorumFields,
            quorumTypes,
            quorumDescriptions
        );
        
        // 7. Grant Allocation Template
        string[] memory grantFields = new string[](4);
        grantFields[0] = "grantee";
        grantFields[1] = "amount";
        grantFields[2] = "purpose";
        grantFields[3] = "milestones";
        
        string[] memory grantTypes = new string[](4);
        grantTypes[0] = "address";
        grantTypes[1] = "uint256";
        grantTypes[2] = "string";
        grantTypes[3] = "string";
        
        string[] memory grantDescriptions = new string[](4);
        grantDescriptions[0] = "Grant recipient address";
        grantDescriptions[1] = "Grant amount in tokens";
        grantDescriptions[2] = "Purpose of the grant";
        grantDescriptions[3] = "Delivery milestones";
        
        _createTemplate(
            "Grant Allocation",
            "Allocate funds for community grants",
            TemplateCategory.TREASURY,
            grantFields,
            grantTypes,
            grantDescriptions
        );
    }

    function _createTemplate(
        string memory _name,
        string memory _description,
        TemplateCategory _category,
        string[] memory _fieldNames,
        string[] memory _fieldTypes,
        string[] memory _fieldDescriptions
    ) internal {
        uint256 templateId = templateCount++;
        
        Template storage template = templates[templateId];
        template.id = templateId;
        template.name = _name;
        template.description = _description;
        template.category = _category;
        template.fieldNames = _fieldNames;
        template.fieldTypes = _fieldTypes;
        template.fieldDescriptions = _fieldDescriptions;
        template.isActive = true;
        template.createdBy = msg.sender;
        template.createdAt = block.timestamp;
        
        templateByName[_name] = templateId;
        activeTemplateIds.push(templateId);
        templatesByCategory[_category].push(templateId);
    }
}
