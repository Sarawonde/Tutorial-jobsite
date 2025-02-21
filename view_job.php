<?php
session_start();
require 'db_connection.php'; // Include DB connection

// Check if the user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

// Fetch all jobs from the database
$stmt = $conn->prepare("SELECT jobs.id, jobs.title, jobs.description, jobs.created_at, users.username AS posted_by 
                        FROM jobs 
                        JOIN users ON jobs.posted_by = users.id");
$stmt->execute();
$result = $stmt->get_result();
$jobs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Jobs</title>
</head>
<body>
    <h1>Available Jobs</h1>
    <?php if (empty($jobs)): ?>
        <p>No jobs available.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($jobs as $job): ?>
                <li>
                    <h2><?php echo htmlspecialchars($job['title']); ?></h2>
                    <p><?php echo htmlspecialchars($job['description']); ?></p>
                    <p>Posted by: <?php echo htmlspecialchars($job['posted_by']); ?> on <?php echo htmlspecialchars($job['created_at']); ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <a href="dashboard.php">Back to Dashboard</a>
</body>
</html>
