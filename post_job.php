<?php
session_start();

// Redirect if the user is not logged in or is not a parent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: dashboard.php");
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tutorial_jobsite";

$conn = new mysqli($servername, $username, $password, $dbname);



if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tutee_name = $_POST['tutee_name'];
    $age = $_POST['age'];
    $education_level = $_POST['education_level'];
    $schedule = $_POST['schedule'];
    $payment = $_POST['payment'];
    $user_id = $_SESSION['user_id']; // Get the logged-in parent's ID

    // Insert the job into the tutoring_jobs table
    $sql = "INSERT INTO tutoring_jobs (tutee_name, age, education_level, schedule, payment, user_id) VALUES ('$tutee_name', '$age', '$education_level', '$schedule', '$payment', '$user_id')";

    if ($conn->query($sql) === TRUE) {
        $_SESSION['success_message'] = "You have successfully posted a job for $tutee_name!";
        header("Location: dashboard.php"); // Redirect to dashboard after posting
        exit;
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error; // Check for errors
    }
}

$conn->close();
?>