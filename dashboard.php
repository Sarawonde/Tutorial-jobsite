<?php
session_start(); // Start the session

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html"); // Redirect to login if not logged in
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="dash.css">
    <title>Dashboard</title>
</head>
<body>
    <header>
        <h1>Your Dashboard</h1>
    </header>
    <main>
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
        
        <?php
        // Display success message if set
        if (isset($_SESSION['success_message'])) {
            echo "<p style='color: green;'>" . htmlspecialchars($_SESSION['success_message']) . "</p>";
            unset($_SESSION['success_message']); // Clear the message after displaying
        }

        // Display error message if set
        if (isset($_SESSION['error_message'])) {
            echo "<p style='color: red;'>" . htmlspecialchars($_SESSION['error_message']) . "</p>";
            unset($_SESSION['error_message']); // Clear the message after displaying
        }
        ?>
        
        <nav>
            <?php if ($_SESSION['role'] == 'parent'): ?>
                <a href="post_job.html">Post a Job</a>
                <a href="logout.php">Logout</a>
                <a href="notifications.php">View Notifications</a>
            <?php elseif ($_SESSION['role'] == 'student'): ?>
                <a href="browse_jobs.php">Browse Jobs</a>
                <a href="logout.php">Logout</a>
                
            <?php endif; ?>
        </nav>
       
    </main>
</body>
</html>