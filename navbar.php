<nav>
    <ul>
        <li><a href="index.html">Home</a></li>
        <li><a href="about.html">About</a></li>
        <li><a href="browse_jobs.html">Browse Jobs</a></li>
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php if ($_SESSION['role'] == 'parent'): ?>
                <li><a href="post_job.html">Post a Job</a></li>
                <li><a href="notifications.php">Notifications</a></li>
            <?php elseif ($_SESSION['role'] == 'student'): ?>
                <li><a href="browse_jobs.php">Browse Jobs</a></li>
            <?php endif; ?>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="logout.php">Logout</a></li>
        <?php else: ?>
            <li><a href="login.html">Login</a></li>
            <li><a href="register.html">Register</a></li>
        <?php endif; ?>
    </ul>
</nav>
<style>
    nav {
        background-color: #333; /* Dark background for the navbar */
        color: white;
        padding: 10px 20px; /* Padding for the navbar */
        position: sticky; /* Make the navbar stick to the top */
        top: 0; /* Stick to the top */
        z-index: 1000; /* Ensure it stays above other content */
    }
    nav ul {
        list-style-type: none; /* Remove bullet points */
        padding: 0; /* Remove padding */
        margin: 0; /* Remove margin */
        display: flex; /* Use flexbox for horizontal layout */
        justify-content: space-between; /* Space out the items */
    }
    nav ul li {
        margin-right: 15px; /* Space between items */
    }
    nav ul li a {
        color: white; /* White text color */
        text-decoration: none; /* Remove underline */
        padding: 8px 15px; /* Padding for links */
        transition: background-color 0.3s; /* Smooth background color transition */
    }
    nav ul li a:hover {
        background-color: #575757; /* Darker background on hover */
        border-radius: 5px; /* Rounded corners on hover */
    }
</style>