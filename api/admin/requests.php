<?php
declare(strict_types=1);

require __DIR__ . "/config.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    admin_json(405, ["ok" => false, "error" => "Method not allowed"]);
}

$limit = intval($_GET["limit"] ?? 100);
if ($limit < 1) {
    $limit = 100;
}
if ($limit > 500) {
    $limit = 500;
}

$db = admin_db_connection();
admin_bootstrap_tables($db);
admin_bootstrap_default_user($db);
$admin = admin_require_user($db);

$appointments = [];
$appointments_stmt = $db->prepare(
    "SELECT id, full_name, email, phone, service_type, city, notes, message, uploaded_files, created_at
     FROM appointment_requests
     ORDER BY id DESC
     LIMIT ?"
);
$appointments_stmt->bind_param("i", $limit);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();
if ($appointments_result) {
    while ($row = $appointments_result->fetch_assoc()) {
        $appointments[] = [
            "id" => (int)$row["id"],
            "full_name" => $row["full_name"],
            "email" => $row["email"],
            "phone" => $row["phone"],
            "service_type" => $row["service_type"],
            "city" => $row["city"] ?? "",
            "notes" => $row["notes"] ?? "",
            "message" => $row["message"] ?? "",
            "created_at" => $row["created_at"],
            "uploaded_files" => admin_parse_uploads($row["uploaded_files"] ?? null),
        ];
    }
}
$appointments_stmt->close();

$contacts = [];
$contacts_stmt = $db->prepare(
    "SELECT id, full_name, email, phone, message, uploaded_files, created_at
     FROM contact_messages
     ORDER BY id DESC
     LIMIT ?"
);
$contacts_stmt->bind_param("i", $limit);
$contacts_stmt->execute();
$contacts_result = $contacts_stmt->get_result();
if ($contacts_result) {
    while ($row = $contacts_result->fetch_assoc()) {
        $contacts[] = [
            "id" => (int)$row["id"],
            "full_name" => $row["full_name"],
            "email" => $row["email"],
            "phone" => $row["phone"],
            "message" => $row["message"] ?? "",
            "created_at" => $row["created_at"],
            "uploaded_files" => admin_parse_uploads($row["uploaded_files"] ?? null),
        ];
    }
}
$contacts_stmt->close();
$db->close();

admin_json(200, [
    "ok" => true,
    "admin" => $admin,
    "appointments" => $appointments,
    "contacts" => $contacts,
]);
