<?php
declare(strict_types=1);

require __DIR__ . "/config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    auth_json(405, ["ok" => false, "error" => "Method not allowed"]);
}

$data = auth_json_body();
$credential = trim((string)($data["credential"] ?? ""));
$preferred_language = trim((string)($data["preferred_language"] ?? "en"));

if ($credential === "") {
    auth_json(400, ["ok" => false, "error" => "Missing Google credential"]);
}

$google_client_id = auth_google_client_id();
if ($google_client_id === "" || stripos($google_client_id, "REPLACE_WITH_") === 0) {
    auth_json(400, ["ok" => false, "error" => "Google login is not configured"]);
}

$verify_url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($credential);
$token_info = auth_fetch_json($verify_url);
if (!$token_info) {
    auth_json(401, ["ok" => false, "error" => "Google token validation failed"]);
}

$aud = trim((string)($token_info["aud"] ?? ""));
$email = strtolower(trim((string)($token_info["email"] ?? "")));
$email_verified = (string)($token_info["email_verified"] ?? "");
$name = trim((string)($token_info["name"] ?? ""));

if ($aud !== $google_client_id) {
    auth_json(401, ["ok" => false, "error" => "Google client mismatch"]);
}
if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    auth_json(401, ["ok" => false, "error" => "Google account email is invalid"]);
}
if (!in_array(strtolower($email_verified), ["true", "1"], true)) {
    auth_json(401, ["ok" => false, "error" => "Google email is not verified"]);
}

if ($preferred_language === "") {
    $preferred_language = "en";
}
if ($name === "") {
    $name = strstr($email, "@", true) ?: $email;
}

$db = auth_db_connection();
auth_bootstrap_tables($db);

$existing = $db->prepare(
    "SELECT id, full_name, email, phone, preferred_language, created_at
     FROM client_users
     WHERE email = ?
     LIMIT 1"
);
$existing->bind_param("s", $email);
$existing->execute();
$existing_result = $existing->get_result();
$user_row = $existing_result ? $existing_result->fetch_assoc() : null;
$existing->close();

if ($user_row) {
    $user_id = (int)$user_row["id"];
    $update = $db->prepare(
        "UPDATE client_users
         SET full_name = IF(full_name = '' OR full_name IS NULL, ?, full_name),
             preferred_language = IF(preferred_language = '' OR preferred_language IS NULL, ?, preferred_language)
         WHERE id = ?"
    );
    $update->bind_param("ssi", $name, $preferred_language, $user_id);
    $update->execute();
    $update->close();

    $refresh = $db->prepare(
        "SELECT id, full_name, email, phone, preferred_language, created_at
         FROM client_users
         WHERE id = ?
         LIMIT 1"
    );
    $refresh->bind_param("i", $user_id);
    $refresh->execute();
    $refreshed = $refresh->get_result();
    $user_row = $refreshed ? $refreshed->fetch_assoc() : $user_row;
    $refresh->close();
} else {
    $random_password = bin2hex(random_bytes(16));
    $password_hash = password_hash($random_password, PASSWORD_DEFAULT);
    $phone = "";

    $insert = $db->prepare(
        "INSERT INTO client_users (full_name, email, phone, password_hash, preferred_language)
         VALUES (?, ?, ?, ?, ?)"
    );
    $insert->bind_param("sssss", $name, $email, $phone, $password_hash, $preferred_language);
    $insert->execute();
    $user_id = (int)$insert->insert_id;
    $insert->close();

    $load = $db->prepare(
        "SELECT id, full_name, email, phone, preferred_language, created_at
         FROM client_users
         WHERE id = ?
         LIMIT 1"
    );
    $load->bind_param("i", $user_id);
    $load->execute();
    $loaded = $load->get_result();
    $user_row = $loaded ? $loaded->fetch_assoc() : null;
    $load->close();
}

if (!$user_row) {
    $db->close();
    auth_json(500, ["ok" => false, "error" => "Unable to load user data"]);
}

$token = auth_issue_session($db, (int)$user_row["id"]);
$db->close();

auth_json(200, [
    "ok" => true,
    "token" => $token,
    "user" => auth_user_payload($user_row),
]);
