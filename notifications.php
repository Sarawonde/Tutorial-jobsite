<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tutorial_jobsite";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check if the user is logged in and is a parent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header('Location: login.php ');
    exit;
}

// Fetch notifications for the logged-in parent
$parent_id = $_SESSION['user_id'];
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications</title>
    <link rel="stylesheet" href="dash.css">
</head>
<body>
    <h2>Your Notifications</h2>
    <?php if ($result->num_rows > 0): ?>
        <ul>
            <?php while ($notification = $result->fetch_assoc()): ?>
                <li>
                    <strong><?php echo htmlspecialchars($notification['created_at']); ?>:</strong>
                    <?php echo htmlspecialchars($notification['message']); ?>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <em> <p>No notifications.</p> </em>
    <?php endif; ?>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>