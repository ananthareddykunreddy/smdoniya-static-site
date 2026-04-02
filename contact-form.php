<?php
header("Content-Type: text/html; charset=UTF-8");
ini_set("log_errors", "1");
ini_set("error_log", __DIR__ . "/form-errors.log");
error_reporting(E_ALL);

$db_host = "localhost";
$db_name = "u744895116_smdoniya_db";
$db_user = "u744895116_u744895116";
$db_pass = "Kareddy@2026";

$smtp_host = "smtp.hostinger.com";
$smtp_port = 465;
$smtp_user = "INFO@smdoniya.com";
$smtp_pass = "Subbu@2026";
$mail_from = "INFO@smdoniya.com";
$mail_to = "cafbixio5@gmail.com";

$recaptcha_secret = "6Ldhg6MsAAAAAAzuvFMmcR3kJZi4olXnT1RqnNpa";
$recaptcha_response = $_POST["g-recaptcha-response"] ?? "";
$recaptcha_action = $_POST["recaptcha_action"] ?? "";

$is_appointment = !empty($_POST["service_type"]) || !empty($_POST["city"]);

$full_name = trim($_POST["full_name"] ?? "");
$email = trim($_POST["email"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$message = trim($_POST["message"] ?? "");
$service_type = trim($_POST["service_type"] ?? "");
$preferred_date = trim($_POST["preferred_date"] ?? "");
$preferred_time = trim($_POST["preferred_time"] ?? "");
$city = trim($_POST["city"] ?? "");
$notes = trim($_POST["notes"] ?? "");

if (empty($_POST) && empty($_FILES) && !empty($_SERVER["CONTENT_LENGTH"])) {
    echo "Upload too large. Please reduce file size and try again.";
    exit;
}

if ($full_name === "" || $email === "" || $phone === "") {
    http_response_code(400);
    echo "Missing required fields.";
    exit;
}

if ($is_appointment) {
    if ($service_type === "" || $city === "") {
        http_response_code(400);
        echo "Missing required fields.";
        exit;
    }
} else {
    if ($message === "") {
        http_response_code(400);
        echo "Missing required fields.";
        exit;
    }
}

$recaptcha_note = "";
if ($recaptcha_secret !== "" && $recaptcha_response !== "") {
    $verify_url = "https://www.google.com/recaptcha/api/siteverify";
    $verify_data = http_build_query([
        "secret" => $recaptcha_secret,
        "response" => $recaptcha_response,
        "remoteip" => $_SERVER["REMOTE_ADDR"] ?? "",
    ]);

    $verify_result = false;
    if (function_exists("curl_init")) {
        $ch = curl_init($verify_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $verify_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $verify_result = curl_exec($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            "http" => [
                "method" => "POST",
                "header" => "Content-type: application/x-www-form-urlencoded\r\n",
                "content" => $verify_data,
                "timeout" => 8,
            ],
        ]);
        $verify_result = file_get_contents($verify_url, false, $context);
    }

    $verify_json = json_decode($verify_result, true);
    $expected_action = $is_appointment ? "appointment" : "contact";
    $score = $verify_json["score"] ?? 0;
    $action_ok = ($recaptcha_action === $expected_action) || (($verify_json["action"] ?? "") === $expected_action);
    if (!$verify_json || empty($verify_json["success"]) || !$action_ok || $score < 0.3) {
        $recaptcha_note = "reCAPTCHA not verified";
    }
} else {
    $recaptcha_note = "reCAPTCHA token missing";
}

$upload_dir = __DIR__ . "/uploads";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$stored_files = [];
if (!empty($_FILES["documents"])) {
    $doc_names = $_FILES["documents"]["name"];
    $doc_errors = $_FILES["documents"]["error"];
    $doc_tmp = $_FILES["documents"]["tmp_name"];

    if (!is_array($doc_names)) {
        $doc_names = [$doc_names];
        $doc_errors = [$doc_errors];
        $doc_tmp = [$doc_tmp];
    }

    $file_count = count($doc_names);
    for ($i = 0; $i < $file_count; $i++) {
        if ($doc_errors[$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        $original = basename($doc_names[$i]);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed = ["pdf", "png", "jpg", "jpeg", "doc", "docx"];
        if (!in_array($ext, $allowed, true)) {
            continue;
        }
        $safe_name = uniqid("upload_", true) . "_" . preg_replace("/[^a-zA-Z0-9._-]/", "_", $original);
        $target = $upload_dir . "/" . $safe_name;
        if (move_uploaded_file($doc_tmp[$i], $target)) {
            $stored_files[] = $safe_name;
        }
    }
}

function smtp_read_response($socket)
{
    $data = "";
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $data .= $line;
        if (preg_match("/^\d{3}\s/", $line)) {
            break;
        }
    }
    return $data;
}

function smtp_send_mail($host, $port, $username, $password, $from, $to, $subject, $body, $reply_to = "")
{
    $socket = fsockopen("ssl://" . $host, $port, $errno, $errstr, 10);
    if (!$socket) {
        error_log("SMTP connect failed: " . $errstr);
        return false;
    }

    $expect = smtp_read_response($socket);
    if (strpos($expect, "220") !== 0) {
        error_log("SMTP greeting failed: " . $expect);
        fclose($socket);
        return false;
    }

    fwrite($socket, "EHLO " . $host . "\r\n");
    $expect = smtp_read_response($socket);
    if (strpos($expect, "250") !== 0) {
        error_log("SMTP EHLO failed: " . $expect);
        fclose($socket);
        return false;
    }

    fwrite($socket, "AUTH LOGIN\r\n");
    $expect = smtp_read_response($socket);
    if (strpos($expect, "334") !== 0) {
        error_log("SMTP AUTH start failed: " . $expect);
        fclose($socket);
        return false;
    }

    fwrite($socket, base64_encode($username) . "\r\n");
    $expect = smtp_read_response($socket);
    if (strpos($expect, "334") !== 0) {
        error_log("SMTP AUTH user failed: " . $expect);
        fclose($socket);
        return false;
    }

    fwrite($socket, base64_encode($password) . "\r\n");
    $expect = smtp_read_response($socket);
    if (strpos($expect, "235") !== 0) {
        error_log("SMTP AUTH pass failed: " . $expect);
        fclose($socket);
        return false;
    }

    fwrite($socket, "MAIL FROM:<" . $from . ">\r\n");
    $expect = smtp_read_response($socket);
    if (strpos($expect, "250") !== 0) {
        error_log("SMTP MAIL FROM failed: " . $expect);
        fclose($socket);
        return false;
    }

    fwrite($socket, "RCPT TO:<" . $to . ">\r\n");
    $expect = smtp_read_response($socket);
    if (strpos($expect, "250") !== 0 && strpos($expect, "251") !== 0) {
        error_log("SMTP RCPT TO failed: " . $expect);
        fclose($socket);
        return false;
    }

    fwrite($socket, "DATA\r\n");
    $expect = smtp_read_response($socket);
    if (strpos($expect, "354") !== 0) {
        error_log("SMTP DATA failed: " . $expect);
        fclose($socket);
        return false;
    }

    $encoded_subject = function_exists("mb_encode_mimeheader")
        ? mb_encode_mimeheader($subject, "UTF-8")
        : $subject;

    $headers = [
        "From: " . $from,
        "To: " . $to,
        "Subject: " . $encoded_subject,
        "MIME-Version: 1.0",
        "Content-Type: text/plain; charset=UTF-8",
    ];
    if ($reply_to !== "") {
        $headers[] = "Reply-To: " . $reply_to;
    }

    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
    $message = str_replace(["\r\n.\r\n", "\n.\n"], "\r\n..\r\n", $message);

    fwrite($socket, $message . "\r\n.\r\n");
    $expect = smtp_read_response($socket);
    if (strpos($expect, "250") !== 0) {
        error_log("SMTP message failed: " . $expect);
        fclose($socket);
        return false;
    }

    fwrite($socket, "QUIT\r\n");
    fclose($socket);
    return true;
}

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) {
    echo "Database connection failed. Please contact support.";
    exit;
}

$mysqli->query("CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    uploaded_files TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$mysqli->query("CREATE TABLE IF NOT EXISTS appointment_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    service_type VARCHAR(255) NOT NULL,
    preferred_date VARCHAR(50),
    preferred_time VARCHAR(50),
    city VARCHAR(120),
    notes TEXT,
    message TEXT,
    uploaded_files TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stored_json = json_encode($stored_files);

if ($is_appointment) {
    if ($recaptcha_note !== "") {
        $notes = trim($notes . " | " . $recaptcha_note);
    }
    if ($message === "" && $notes !== "") {
        $message = $notes;
    }
    $stmt = $mysqli->prepare("INSERT INTO appointment_requests (full_name, email, phone, service_type, preferred_date, preferred_time, city, notes, message, uploaded_files) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo "Database error. Please try again later.";
        exit;
    }
    $stmt->bind_param(
        "ssssssssss",
        $full_name,
        $email,
        $phone,
        $service_type,
        $preferred_date,
        $preferred_time,
        $city,
        $notes,
        $message,
        $stored_json
    );
    $stmt->execute();
    $stmt->close();
} else {
    if ($recaptcha_note !== "") {
        $message = trim($message . " | " . $recaptcha_note);
    }
    $stmt = $mysqli->prepare("INSERT INTO contact_messages (full_name, email, phone, message, uploaded_files) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo "Database error. Please try again later.";
        exit;
    }
    $stmt->bind_param(
        "sssss",
        $full_name,
        $email,
        $phone,
        $message,
        $stored_json
    );
    $stmt->execute();
    $stmt->close();
}

$mysqli->close();

$file_list = empty($stored_files) ? "None" : implode(", ", $stored_files);
$mail_subject = $is_appointment ? "New Appointment Request" : "New Contact Request";
$mail_body = "New submission received.\n\n";
$mail_body .= "Full name: " . $full_name . "\n";
$mail_body .= "Email: " . $email . "\n";
$mail_body .= "Phone: " . $phone . "\n";
if ($is_appointment) {
    $mail_body .= "Service: " . $service_type . "\n";
    $mail_body .= "City: " . $city . "\n";
    if ($notes !== "") {
        $mail_body .= "Notes: " . $notes . "\n";
    }
} else {
    $mail_body .= "Message: " . $message . "\n";
}
$mail_body .= "Uploaded files: " . $file_list . "\n";
if ($recaptcha_note !== "") {
    $mail_body .= "reCAPTCHA: " . $recaptcha_note . "\n";
}
$mail_body .= "Submitted from: " . ($_SERVER["REMOTE_ADDR"] ?? "unknown") . "\n";

$mail_sent = smtp_send_mail(
    $smtp_host,
    $smtp_port,
    $smtp_user,
    $smtp_pass,
    $mail_from,
    $mail_to,
    $mail_subject,
    $mail_body,
    $email
);

if (!$mail_sent) {
    error_log("Email sending failed for " . $email);
}

echo "Thank you. Your request has been received.";
?>
