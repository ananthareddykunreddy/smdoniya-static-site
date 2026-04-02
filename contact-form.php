<?php
header("Content-Type: text/html; charset=UTF-8");

$db_host = "localhost";
$db_name = "u744895116_smdoniya_db";
$db_user = "u744895116";
$db_pass = "Kareddy@2026";

$recaptcha_secret = "6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe";
$recaptcha_response = $_POST["g-recaptcha-response"] ?? "";

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

if ($recaptcha_secret !== "" && $recaptcha_response !== "") {
    $verify_url = "https://www.google.com/recaptcha/api/siteverify";
    $verify_data = http_build_query([
        "secret" => $recaptcha_secret,
        "response" => $recaptcha_response,
        "remoteip" => $_SERVER["REMOTE_ADDR"] ?? "",
    ]);
    $context = stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => "Content-type: application/x-www-form-urlencoded\r\n",
            "content" => $verify_data,
            "timeout" => 8,
        ],
    ]);
    $verify_result = file_get_contents($verify_url, false, $context);
    $verify_json = json_decode($verify_result, true);
    if (!$verify_json || empty($verify_json["success"])) {
        http_response_code(400);
        echo "Captcha verification failed. Please try again.";
        exit;
    }
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

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo "Database connection failed.";
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
    if ($message === "" && $notes !== "") {
        $message = $notes;
    }
    $stmt = $mysqli->prepare("INSERT INTO appointment_requests (full_name, email, phone, service_type, preferred_date, preferred_time, city, notes, message, uploaded_files) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
    $stmt = $mysqli->prepare("INSERT INTO contact_messages (full_name, email, phone, message, uploaded_files) VALUES (?, ?, ?, ?, ?)");
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

echo "Thank you. Your request has been received.";
?>
