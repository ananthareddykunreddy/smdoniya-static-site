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

function auth_db_connection(): mysqli
{
    $db_host = "localhost";
    $db_name = "u744895116_smdoniya_db";
    $db_user = "u744895116_u744895116";
    $db_pass = "Kareddy@2026";

    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($mysqli->connect_error) {
        auth_json(500, ["ok" => false, "error" => "Database connection failed"]);
    }
    $mysqli->set_charset("utf8mb4");
    return $mysqli;
}

function auth_bootstrap_tables(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS client_users (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        phone VARCHAR(50) DEFAULT '',
        password_hash VARCHAR(255) NOT NULL,
        preferred_language VARCHAR(10) DEFAULT 'en',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS client_sessions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_seen_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_client_sessions_user_id (user_id),
        INDEX idx_client_sessions_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function auth_json_body(): array
{
    $raw = file_get_contents("php://input");
    if (!is_string($raw) || trim($raw) === "") {
        return $_POST ?: [];
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return [];
    }
    return $json;
}

function auth_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function auth_bearer_token(): string
{
    $header = $_SERVER["HTTP_AUTHORIZATION"] ?? "";
    if (!is_string($header) || stripos($header, "Bearer ") !== 0) {
        return "";
    }
    return trim(substr($header, 7));
}

function auth_user_payload(array $row): array
{
    return [
        "id" => (int)$row["id"],
        "full_name" => $row["full_name"],
        "email" => $row["email"],
        "phone" => $row["phone"] ?? "",
        "preferred_language" => $row["preferred_language"] ?? "en",
        "created_at" => $row["created_at"] ?? null,
    ];
}

function auth_issue_session(mysqli $db, int $user_id): string
{
    $token = bin2hex(random_bytes(32));
    $token_hash = hash("sha256", $token);
    $expires_at = (new DateTimeImmutable("now", new DateTimeZone("UTC")))
        ->modify("+30 days")
        ->format("Y-m-d H:i:s");

    $stmt = $db->prepare("INSERT INTO client_sessions (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $token_hash, $expires_at);
    $stmt->execute();
    $stmt->close();

    return $token;
}

function auth_find_user_by_token(mysqli $db, string $token): ?array
{
    if ($token === "") {
        return null;
    }

    $token_hash = hash("sha256", $token);
    $stmt = $db->prepare(
        "SELECT u.id, u.full_name, u.email, u.phone, u.preferred_language, u.created_at
         FROM client_sessions s
         INNER JOIN client_users u ON u.id = s.user_id
         WHERE s.token_hash = ? AND s.expires_at > UTC_TIMESTAMP()
         LIMIT 1"
    );
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    $touch = $db->prepare("UPDATE client_sessions SET last_seen_at = CURRENT_TIMESTAMP WHERE token_hash = ?");
    $touch->bind_param("s", $token_hash);
    $touch->execute();
    $touch->close();

    return $row;
}
