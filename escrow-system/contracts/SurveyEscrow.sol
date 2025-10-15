// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

/**
 * @title SurveyEscrow
 * @dev Contrato de escrow para encuestas pagadas con token SPHE
 * @notice Los usuarios depositan SPHE para participar en encuestas
 * @notice El owner distribuye los fondos a los ganadores cuando finaliza la encuesta
 */
contract SurveyEscrow is ReentrancyGuard, Ownable {
    using SafeERC20 for IERC20;

    // Token SPHE en Polygon
    IERC20 public immutable spheToken;

    // Estructura de una encuesta
    struct Survey {
        uint256 surveyId;
        uint256 totalDeposited;
        uint256 totalPaidOut;
        bool finalized;
        mapping(address => uint256) deposits; // Depósitos por participante
        address[] participants;
    }

    // Mapping de surveys
    mapping(uint256 => Survey) public surveys;

    // Eventos
    event Deposit(
        uint256 indexed surveyId,
        address indexed participant,
        uint256 amount,
        uint256 timestamp
    );

    event SurveyFinalized(
        uint256 indexed surveyId,
        uint256 totalDistributed,
        uint256 winnersCount,
        uint256 timestamp
    );

    event Payout(
        uint256 indexed surveyId,
        address indexed recipient,
        uint256 amount,
        uint256 timestamp
    );

    event EmergencyWithdraw(
        address indexed to,
        uint256 amount,
        uint256 timestamp
    );

    /**
     * @dev Constructor
     * @param _spheToken Dirección del contrato SPHE ERC-20
     */
    constructor(address _spheToken) Ownable(msg.sender) {
        require(_spheToken != address(0), "Invalid token address");
        spheToken = IERC20(_spheToken);
    }

    /**
     * @dev Deposita tokens SPHE para participar en una encuesta
     * @param surveyId ID de la encuesta
     * @param amount Cantidad de SPHE a depositar
     * @notice El usuario debe haber aprobado (approve) los tokens antes
     */
    function deposit(uint256 surveyId, uint256 amount) external nonReentrant {
        require(amount > 0, "Amount must be greater than 0");
        require(!surveys[surveyId].finalized, "Survey already finalized");

        Survey storage survey = surveys[surveyId];

        // Transferir tokens del usuario al contrato
        spheToken.safeTransferFrom(msg.sender, address(this), amount);

        // Registrar depósito
        if (survey.deposits[msg.sender] == 0) {
            survey.participants.push(msg.sender);
        }

        survey.deposits[msg.sender] += amount;
        survey.totalDeposited += amount;

        emit Deposit(surveyId, msg.sender, amount, block.timestamp);
    }

    /**
     * @dev Finaliza una encuesta y distribuye fondos a los ganadores
     * @param surveyId ID de la encuesta
     * @param winners Array de direcciones ganadoras
     * @param amounts Array de cantidades a pagar a cada ganador
     * @notice Solo el owner puede llamar esta función
     * @notice Los arrays winners y amounts deben tener el mismo tamaño
     */
    function finalizeSurvey(
        uint256 surveyId,
        address[] calldata winners,
        uint256[] calldata amounts
    ) external onlyOwner nonReentrant {
        require(winners.length == amounts.length, "Arrays length mismatch");
        require(winners.length > 0, "No winners provided");
        require(!surveys[surveyId].finalized, "Survey already finalized");

        Survey storage survey = surveys[surveyId];
        uint256 totalToPay = 0;

        // Calcular total a pagar
        for (uint256 i = 0; i < amounts.length; i++) {
            totalToPay += amounts[i];
        }

        require(totalToPay <= survey.totalDeposited, "Insufficient balance");

        // Distribuir fondos
        for (uint256 i = 0; i < winners.length; i++) {
            require(winners[i] != address(0), "Invalid winner address");
            require(amounts[i] > 0, "Amount must be greater than 0");

            spheToken.safeTransfer(winners[i], amounts[i]);
            survey.totalPaidOut += amounts[i];

            emit Payout(surveyId, winners[i], amounts[i], block.timestamp);
        }

        survey.finalized = true;

        emit SurveyFinalized(
            surveyId,
            totalToPay,
            winners.length,
            block.timestamp
        );
    }

    /**
     * @dev Distribuye pagos en batch (para muchos ganadores)
     * @param surveyId ID de la encuesta
     * @param winners Array de direcciones ganadoras
     * @param amounts Array de cantidades
     * @param start Índice de inicio
     * @param end Índice de fin (exclusivo)
     * @notice Útil para evitar gas limit en distribuciones grandes
     */
    function payoutBatch(
        uint256 surveyId,
        address[] calldata winners,
        uint256[] calldata amounts,
        uint256 start,
        uint256 end
    ) external onlyOwner nonReentrant {
        require(winners.length == amounts.length, "Arrays length mismatch");
        require(start < end, "Invalid range");
        require(end <= winners.length, "End out of bounds");
        require(!surveys[surveyId].finalized, "Survey already finalized");

        Survey storage survey = surveys[surveyId];

        for (uint256 i = start; i < end; i++) {
            require(winners[i] != address(0), "Invalid winner address");
            require(amounts[i] > 0, "Amount must be greater than 0");

            spheToken.safeTransfer(winners[i], amounts[i]);
            survey.totalPaidOut += amounts[i];

            emit Payout(surveyId, winners[i], amounts[i], block.timestamp);
        }
    }

    /**
     * @dev Obtiene el depósito de un participante en una encuesta
     * @param surveyId ID de la encuesta
     * @param participant Dirección del participante
     * @return Cantidad depositada
     */
    function getDeposit(uint256 surveyId, address participant)
        external
        view
        returns (uint256)
    {
        return surveys[surveyId].deposits[participant];
    }

    /**
     * @dev Obtiene información general de una encuesta
     * @param surveyId ID de la encuesta
     * @return totalDeposited Total depositado
     * @return totalPaidOut Total pagado
     * @return finalized Si la encuesta está finalizada
     * @return participantsCount Número de participantes
     */
    function getSurveyInfo(uint256 surveyId)
        external
        view
        returns (
            uint256 totalDeposited,
            uint256 totalPaidOut,
            bool finalized,
            uint256 participantsCount
        )
    {
        Survey storage survey = surveys[surveyId];
        return (
            survey.totalDeposited,
            survey.totalPaidOut,
            survey.finalized,
            survey.participants.length
        );
    }

    /**
     * @dev Obtiene la lista de participantes de una encuesta
     * @param surveyId ID de la encuesta
     * @return Array de direcciones de participantes
     */
    function getParticipants(uint256 surveyId)
        external
        view
        returns (address[] memory)
    {
        return surveys[surveyId].participants;
    }

    /**
     * @dev Retiro de emergencia (solo owner)
     * @param to Dirección destino
     * @param amount Cantidad a retirar
     * @notice Solo usar en caso de emergencia
     */
    function emergencyWithdraw(address to, uint256 amount)
        external
        onlyOwner
        nonReentrant
    {
        require(to != address(0), "Invalid address");
        require(amount > 0, "Amount must be greater than 0");

        uint256 balance = spheToken.balanceOf(address(this));
        require(amount <= balance, "Insufficient balance");

        spheToken.safeTransfer(to, amount);

        emit EmergencyWithdraw(to, amount, block.timestamp);
    }

    /**
     * @dev Obtiene el balance total de SPHE en el contrato
     * @return Balance total
     */
    function getTotalBalance() external view returns (uint256) {
        return spheToken.balanceOf(address(this));
    }
}
