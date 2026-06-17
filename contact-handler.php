<?php
/**
 * Contact Form Handler
 * Verwerkt contactformulier en stuurt email naar info@jewelste.nl
 */

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ==================== SPAM DETECTION ====================

// 1. Honeypot check - if the hidden field is filled, it's spam
if (!empty($_POST['website_url'] ?? '')) {
    // Silently return success to confuse spambots
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Bericht verzonden!']);
    exit;
}

// 2. Timing check - must take at least 2 seconds to fill form
$formStartTime = (int) ($_POST['form_start_time'] ?? 0);
if ($formStartTime > 0) {
    $fillTime = time() - $formStartTime;
    if ($fillTime < 2) {
        http_response_code(400);
        echo json_encode(['error' => 'Formulier te snel ingevuld. Probeer het opnieuw.']);
        exit;
    }
}

// 3. Rate limiting - max 5 messages per IP per hour
$clientIP = $_SERVER['REMOTE_ADDR'];
$rateLimitKey = 'contact_form_' . $clientIP;
$maxSubmissions = 5;
$timeWindow = 3600; // 1 hour

if (!isset($_SESSION[$rateLimitKey])) {
    $_SESSION[$rateLimitKey] = [];
}

// Remove old submissions outside the time window
$currentTime = time();
$_SESSION[$rateLimitKey] = array_filter($_SESSION[$rateLimitKey], function($timestamp) use ($currentTime, $timeWindow) {
    return ($currentTime - $timestamp) < $timeWindow;
});

// Check if limit exceeded
if (count($_SESSION[$rateLimitKey]) >= $maxSubmissions) {
    http_response_code(429);
    echo json_encode(['error' => 'Te veel berichten verzonden. Probeer het later opnieuw.']);
    exit;
}

// Add current submission to session
$_SESSION[$rateLimitKey][] = $currentTime;

// Validate required fields
$naam = trim($_POST['naam'] ?? '');
$email = trim($_POST['email'] ?? '');
$bericht = trim($_POST['bericht'] ?? '');

if (!$naam || !$email || !$bericht) {
    http_response_code(400);
    echo json_encode(['error' => 'Verplichte velden ontbreken']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldig e-mailadres']);
    exit;
}

// Sanitize inputs
$naam = htmlspecialchars($naam, ENT_QUOTES, 'UTF-8');
$email = filter_var($email, FILTER_SANITIZE_EMAIL);
$telefoon = htmlspecialchars($_POST['telefoon'] ?? '', ENT_QUOTES, 'UTF-8');
$type = htmlspecialchars($_POST['type'] ?? '', ENT_QUOTES, 'UTF-8');
$datum = htmlspecialchars($_POST['datum'] ?? '', ENT_QUOTES, 'UTF-8');
$locatie = htmlspecialchars($_POST['locatie'] ?? '', ENT_QUOTES, 'UTF-8');
$bericht = htmlspecialchars($bericht, ENT_QUOTES, 'UTF-8');

// Build email body
$email_body = "=== Nieuw contactformulier jeWelste ===\n\n";
$email_body .= "Naam: $naam\n";
$email_body .= "E-mail: $email\n";
if ($telefoon) $email_body .= "Telefoon: $telefoon\n";
if ($type) $email_body .= "Type gelegenheid: $type\n";
if ($datum) $email_body .= "Gewenste datum: $datum\n";
if ($locatie) $email_body .= "Locatie: $locatie\n";
$email_body .= "\nBericht:\n$bericht\n";
$email_body .= "\n---\nVerzonden op: " . date('Y-m-d H:i:s') . "\n";

// Email headers
$to = 'info@jewelste.nl';
$subject = "Nieuw bericht van $naam | jeWelste";
$headers = "From: $email\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// Send email
$success = mail($to, $subject, $email_body, $headers);

if ($success) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Bericht verzonden!']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Kon e-mail niet verzenden']);
}
exit;
