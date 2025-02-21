<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tutorial_jobsite";

// Create a connection to the database
$conn = new mysqli($servername, $username, $password, $dbname);


// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
<?php
session_start(); // Start the session

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html"); // Redirect to login if not logged in
    exit;
}

// SQL query to fetch jobs posted by parents
$sql = "SELECT tutoring_jobs.*, users.name AS posted_by FROM tutoring_jobs 
        JOIN users ON tutoring_jobs.user_id = users.id";
$result = $conn->query($sql);

// Check if there are jobs available
if ($result->num_rows > 0) {
    echo "<h2>Available Tutoring Jobs</h2>";
    echo "<table border='1'>
            <tr>
                <th>Tutee Name</th>
                <th>Age</th>
                <th>Education Level</th>
                <th>Schedule</th>
                <th>Payment</th>
                <th>Posted By</th>
                <th>Action</th>
            </tr>";
    
    // Output data for each job
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>" . htmlspecialchars($row['tutee_name']) . "</td>
                <td>" . htmlspecialchars($row['age']) . "</td>
                <td>" . htmlspecialchars($row['education_level']) . "</td>
                <td>" . htmlspecialchars($row['schedule']) . "</td>
                <td>" . htmlspecialchars($row['payment']) . "</td>
                <td>" . htmlspecialchars($row['posted_by']) . "</td>
                <td><a href='apply.php?job_id=" . $row['id'] . "'>Apply</a></td>
              </tr>";
    }
    echo "</table>";
} else {
    echo "<p>No jobs available.</p>";
}

$conn->close(); // Close the database connection

?>

