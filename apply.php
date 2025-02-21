<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tutorial_jobsite";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

// Check if job_id is set
if (isset($_GET['job_id'])) {
    $job_id = $_GET['job_id'];
    $student_id = $_SESSION['user_id'];

    // Fetch the student's information
    $student_sql = "SELECT name, email FROM users WHERE id = ?";
    $student_stmt = $conn->prepare($student_sql);
    $student_stmt->bind_param("i", $student_id);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    $student = $student_result->fetch_assoc();

    // Fetch the tutee's name based on the job posting
    $tutee_sql = "SELECT tutee_name FROM tutoring_jobs WHERE id = ?";
    $tutee_stmt = $conn->prepare($tutee_sql);
    $tutee_stmt->bind_param("i", $job_id);
    $tutee_stmt->execute();
    $tutee_result = $tutee_stmt->get_result();
    $tutee = $tutee_result->fetch_assoc();

    // Insert application into the applications table
    $sql = "INSERT INTO applications (job_id, student_id, status) VALUES (?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $job_id, $student_id);

    if ($stmt->execute() === TRUE) {
        // Fetch the parent's user_id and email based on the job posting
        $parent_sql = "SELECT u.id AS parent_id, u.email 
                       FROM users u 
                       JOIN tutoring_jobs tj ON u.id = tj.user_id 
                       WHERE tj.id = ?";
        $parent_stmt = $conn->prepare($parent_sql);
        $parent_stmt->bind_param("i", $job_id);
        $parent_stmt->execute();
        $parent_result = $parent_stmt->get_result();
        $parent = $parent_result->fetch_assoc();

        if ($parent) {
            $parent_id = $parent['parent_id'];
            $parent_email = $parent['email'];

            // Insert notification into the notifications table
            $notification_message = "A student, " . $student['name'] . " (" . $student['email'] . "), has applied to tutor " . $tutee['tutee_name'] . ".";
            $notification_sql = "INSERT INTO notifications (user_id, job_id, message) VALUES (?, ?, ?)";
            $notification_stmt = $conn->prepare($notification_sql);
            $notification_stmt->bind_param("iis", $parent_id, $job_id, $notification_message);
            $notification_stmt->execute();

            // Send email notification to the parent
            $to = $parent_email;
            $subject = "New Job Application Notification";
            $message = "Your job posting for " . $tutee['tutee_name'] . " has received a new application from " . $student['name'] . " (" . $student['email'] . ").";
            $headers = "From: no-reply@yourdomain.com";

            mail($to, $subject, $message, $headers); // Send email
        }

        $_SESSION['success_message'] = "You have successfully applied for the job!";
        header("Location: browse_jobs.php");
        exit;
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "No job ID provided.";
}

$conn->close();
?>