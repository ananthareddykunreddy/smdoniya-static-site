<?php
declare(strict_types=1);

require __DIR__ . "/config.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    auth_json(405, ["ok" => false, "error" => "Method not allowed"]);
}

$token = auth_bearer_token();
if ($token === "") {
    auth_json(401, ["ok" => false, "error" => "Missing token"]);
}

$db = auth_db_connection();
auth_bootstrap_tables($db);
$user_row = auth_find_user_by_token($db, $token);
$db->close();

if (!$user_row) {
    auth_json(401, ["ok" => false, "error" => "Session expired or invalid"]);
}

auth_json(200, [
    "ok" => true,
    "user" => auth_user_payload($user_row),
]);
