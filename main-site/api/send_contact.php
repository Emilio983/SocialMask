<?php
// ============================================
// SEND CONTACT - Envío de formulario de contacto
// ============================================

require_once '../config/connection.php';
require_once '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once '../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once '../vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use TheSocialMask\Config\Env;

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Validar y limpiar datos del formulario
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $wallet = trim($_POST['wallet'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $privacy = isset($_POST['privacy']);

    // Validaciones
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        throw new Exception('Todos los campos marcados con * son obligatorios');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido');
    }

    if (!$privacy) {
        throw new Exception('Debes aceptar la política de privacidad');
    }

    // Validar wallet address si se proporciona
    if (!empty($wallet) && !preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {
        throw new Exception('Dirección de wallet inválida');
    }

    // Mapear tipos de asunto
    $subject_types = [
        'soporte' => 'Soporte Técnico',
        'sugerencias' => 'Sugerencias',
        'partnerships' => 'Partnerships',
        'prensa' => 'Prensa y Medios',
        'general' => 'Consulta General',
        'otros' => 'Otros'
    ];

    $subject_text = $subject_types[$subject] ?? 'Consulta General';

    // Guardar en base de datos
    $stmt = $pdo->prepare("
        INSERT INTO contact_messages
        (name, email, subject_type, wallet_address, message, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmt->execute([
        $name,
        $email,
        $subject,
        $wallet ?: null,
        $message,
        $ip_address,
        $user_agent
    ]);

    $contact_id = $pdo->lastInsertId();

    // Configurar PHPMailer
    $mail = new PHPMailer(true);

    try {
        // ✅ SEGURO: Configuración desde variables de entorno
        $mail->isSMTP();
        $mail->Host       = Env::get('SMTP_HOST', 'smtp.hostinger.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = Env::require('SMTP_USERNAME');
        $mail->Password   = Env::require('SMTP_PASSWORD');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = (int) Env::get('SMTP_PORT', 465);

        // Configuración del remitente y destinatario
        $fromEmail = Env::get('SMTP_FROM_EMAIL', 'hi@thesocialmask.org');
        $fromName = Env::get('SMTP_FROM_NAME', 'thesocialmask Contact Form');
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($fromEmail, 'thesocialmask Team');
        $mail->addReplyTo($email, $name);

        // Configuración del contenido
        $mail->isHTML(true);
        $mail->Subject = "[$subject_text] Mensaje de $name - #$contact_id";

        // Crear contenido HTML del email
        $email_body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #9945FF, #14F195, #00D4FF); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; }
                .field { margin-bottom: 15px; }
                .label { font-weight: bold; color: #555; }
                .value { margin-top: 5px; padding: 10px; background: white; border-radius: 4px; border-left: 4px solid #3B82F6; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>📧 Nuevo mensaje de contacto - The Social Mask</h2>
                    <p>ID del mensaje: #$contact_id</p>
                </div>
                <div class='content'>
                    <div class='field'>
                        <div class='label'>👤 Nombre:</div>
                        <div class='value'>$name</div>
                    </div>

                    <div class='field'>
                        <div class='label'>📧 Email:</div>
                        <div class='value'>$email</div>
                    </div>

                    <div class='field'>
                        <div class='label'>📋 Tipo de consulta:</div>
                        <div class='value'>$subject_text</div>
                    </div>";

        if (!empty($wallet)) {
            $email_body .= "
                    <div class='field'>
                        <div class='label'>👛 Wallet Address:</div>
                        <div class='value'><code>$wallet</code></div>
                    </div>";
        }

        $email_body .= "
                    <div class='field'>
                        <div class='label'>💬 Mensaje:</div>
                        <div class='value'>" . nl2br(htmlspecialchars($message)) . "</div>
                    </div>

                    <div class='field'>
                        <div class='label'>🌐 IP Address:</div>
                        <div class='value'>$ip_address</div>
                    </div>

                    <div class='field'>
                        <div class='label'>🕒 Fecha:</div>
                        <div class='value'>" . date('Y-m-d H:i:s') . "</div>
                    </div>
                </div>

                <div class='footer'>
                    <p>Este mensaje fue enviado desde el formulario de contacto de thesocialmask.org</p>
                    <p>Para responder, simplemente replica a este email.</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->Body = $email_body;

        // Enviar email
        $mail->send();

        // Enviar email de confirmación al usuario
        $confirmation_mail = new PHPMailer(true);

        // ✅ SEGURO: Configuración desde variables de entorno
        $confirmation_mail->isSMTP();
        $confirmation_mail->Host       = Env::get('SMTP_HOST', 'smtp.hostinger.com');
        $confirmation_mail->SMTPAuth   = true;
        $confirmation_mail->Username   = Env::require('SMTP_USERNAME');
        $confirmation_mail->Password   = Env::require('SMTP_PASSWORD');
        $confirmation_mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $confirmation_mail->Port       = (int) Env::get('SMTP_PORT', 465);

        $confirmation_mail->setFrom($fromEmail, $fromName);
        $confirmation_mail->addAddress($email, $name);

        $confirmation_mail->isHTML(true);
        $confirmation_mail->Subject = "✅ Mensaje recibido - The Social Mask Support #$contact_id";

        $confirmation_body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #9945FF, #14F195, #00D4FF); color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; }
                .highlight { background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #14F195; margin: 15px 0; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🎉 ¡Mensaje recibido!</h2>
                    <p>Gracias por contactar con thesocialmask</p>
                </div>
                <div class='content'>
                    <p>Hola <strong>$name</strong>,</p>

                    <p>Hemos recibido tu mensaje correctamente y nuestro equipo lo revisará lo antes posible.</p>

                    <div class='highlight'>
                        <strong>📋 Detalles de tu consulta:</strong><br>
                        <strong>ID:</strong> #$contact_id<br>
                        <strong>Tipo:</strong> $subject_text<br>
                        <strong>Fecha:</strong> " . date('d/m/Y H:i') . "
                    </div>

                    <p><strong>⏰ Tiempo de respuesta:</strong> Normalmente respondemos en un plazo de 24 horas durante días laborables.</p>

                    <p><strong>💡 Mientras tanto:</strong></p>
                    <ul>
                        <li>Revisa nuestras <a href='https://thesocialmask.org/membership/membership.php'>membresías premium</a></li>
                        <li>Explora el <a href='https://thesocialmask.org/pages/token.php'>token SPHE</a></li>
                        <li>Síguenos en nuestras redes sociales para las últimas actualizaciones</li>
                    </ul>

                    <p>¡Gracias por formar parte de la comunidad thesocialmask! 🚀</p>

                    <p>Saludos,<br>
                    <strong>El equipo de thesocialmask</strong></p>
                </div>

                <div class='footer'>
                    <p>Este es un mensaje automático. No respondas a este email.</p>
                    <p>Si tienes más preguntas, envía un nuevo mensaje desde nuestro formulario de contacto.</p>
                </div>
            </div>
        </body>
        </html>";

        $confirmation_mail->Body = $confirmation_body;
        $confirmation_mail->send();

        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => '¡Mensaje enviado exitosamente! Te responderemos dentro de 24 horas.',
            'contact_id' => $contact_id
        ]);

    } catch (Exception $e) {
        // Error al enviar email, pero se guardó en BD
        echo json_encode([
            'success' => false,
            'message' => 'Tu mensaje se guardó correctamente, pero hubo un problema al enviarlo. Nos pondremos en contacto contigo pronto.',
            'contact_id' => $contact_id
        ]);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>