<?php
session_start(); // Start the session

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tutorial_jobsite";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $input_password = $_POST['password']; // Password entered by the user

    // Default password for all users
    $default_password = "password123"; // Change this to your desired default password

    // Prepare the SQL statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if a user with the provided email exists
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc(); // Fetch the user data

        // Since we're using a default password, we don't need to verify the password
        // We can directly log in the user
        // Set session variables
        $_SESSION['user_id'] = $row['id'];
       
        $_SESSION['user_name'] = $row['name'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['success_message'] = "Login successful!";

        // Redirect to dashboard
        header("Location: dashboard.php");
        exit;
    } else {
        echo "No user found with that email."; // No user found
    }

    $stmt->close(); // Close the statement
}

$conn->close(); // Close the database connection
?>