<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    http_response_code(204);
    exit;
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

ini_set("log_errors", "1");
ini_set("error_log", __DIR__ . "/../../form-errors.log");
error_reporting(E_ALL);

$admin_bootstrap_email = "info@smdoniya.com";
$admin_bootstrap_password = "ChangeNow@2026";
$admin_bootstrap_name = "SM SOLUTIONS ADMIN";

function admin_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_json_body(): array
{
    $raw = file_get_contents("php://input");
    if (!is_string($raw) || trim($raw) === "") {
        return $_POST ?: [];
    }
    $json = json_decode($raw, true);
    return is_array($json) ? $json : [];
}

function admin_db_connection(): mysqli
{
    $db_host = "localhost";
    $db_name = "u744895116_smdoniya_db";
    $db_user = "u744895116_u744895116";
    $db_pass = "Kareddy@2026";

    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($mysqli->connect_error) {
        admin_json(500, ["ok" => false, "error" => "Database connection failed"]);
    }
    $mysqli->set_charset("utf8mb4");
    return $mysqli;
}

function admin_bootstrap_tables(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS admin_users (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS admin_sessions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_id BIGINT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admin_sessions_admin_id (admin_id),
        INDEX idx_admin_sessions_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        uploaded_files TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS appointment_requests (
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
}

function admin_bootstrap_default_user(mysqli $db): void
{
    global $admin_bootstrap_email, $admin_bootstrap_password, $admin_bootstrap_name;

    $email = strtolower(trim((string)$admin_bootstrap_email));
    $password = (string)$admin_bootstrap_password;
    $name = trim((string)$admin_bootstrap_name);

    if ($email === "" || $password === "") {
        return;
    }
    if ($name === "") {
        $name = "SM SOLUTIONS ADMIN";
    }

    $check = $db->prepare("SELECT id FROM admin_users WHERE email = ? LIMIT 1");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();
    $exists = $result ? $result->fetch_assoc() : null;
    $check->close();

    if ($exists) {
        return;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $db->prepare("INSERT INTO admin_users (full_name, email, password_hash) VALUES (?, ?, ?)");
    $insert->bind_param("sss", $name, $email, $hash);
    $insert->execute();
    $insert->close();
}

function admin_issue_session(mysqli $db, int $admin_id): string
{
    $token = bin2hex(random_bytes(32));
    $token_hash = hash("sha256", $token);
    $expires_at = (new DateTimeImmutable("now", new DateTimeZone("UTC")))
        ->modify("+24 hours")
        ->format("Y-m-d H:i:s");

    $stmt = $db->prepare("INSERT INTO admin_sessions (admin_id, token_hash, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $admin_id, $token_hash, $expires_at);
    $stmt->execute();
    $stmt->close();

    return $token;
}

function admin_bearer_token(): string
{
    $header = $_SERVER["HTTP_AUTHORIZATION"] ?? "";
    if (!is_string($header) || stripos($header, "Bearer ") !== 0) {
        return "";
    }
    return trim(substr($header, 7));
}

function admin_require_user(mysqli $db): array
{
    $token = admin_bearer_token();
    if ($token === "") {
        admin_json(401, ["ok" => false, "error" => "Missing token"]);
    }

    $token_hash = hash("sha256", $token);
    $stmt = $db->prepare(
        "SELECT a.id, a.full_name, a.email
         FROM admin_sessions s
         INNER JOIN admin_users a ON a.id = s.admin_id
         WHERE s.token_hash = ? AND s.expires_at > UTC_TIMESTAMP()
         LIMIT 1"
    );
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        admin_json(401, ["ok" => false, "error" => "Session expired or invalid"]);
    }

    return [
        "id" => (int)$row["id"],
        "full_name" => $row["full_name"],
        "email" => $row["email"],
    ];
}

function admin_parse_uploads(?string $raw): array
{
    if (!is_string($raw) || trim($raw) === "") {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $items = [];
    foreach ($decoded as $entry) {
        if (is_array($entry)) {
            $original = trim((string)($entry["original"] ?? ""));
            $stored = trim((string)($entry["stored"] ?? ""));
            $path = trim((string)($entry["path"] ?? ""));
            if ($path === "" && $stored !== "") {
                $path = "uploads/" . $stored;
            }
            if ($original === "" && $stored !== "") {
                $original = $stored;
            }
            if ($original !== "") {
                $items[] = [
                    "original" => $original,
                    "stored" => $stored,
                    "path" => $path,
                ];
            }
        } elseif (is_string($entry) && trim($entry) !== "") {
            $name = trim($entry);
            $items[] = [
                "original" => $name,
                "stored" => $name,
                "path" => "uploads/" . $name,
            ];
        }
    }
    return $items;
}
