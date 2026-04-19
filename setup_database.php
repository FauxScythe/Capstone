<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillBridge AI - Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .container { background: #f9f9f9; padding: 30px; border-radius: 10px; }
        .success { color: #27ae60; }
        .error { color: #e74c3c; }
        .info { color: #3498db; }
        pre { background: #f0f0f0; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .btn { background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <h1> SkillBridge AI - Database Setup</h1>
        <p>This script will create the necessary database and tables for SkillBridge AI.</p>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            echo "<h3>Setting up database...</h3>";
            
            // Connect to MySQL without database first
            $conn = new mysqli("localhost", "root", "");
            
            if ($conn->connect_error) {
                die("<p class='error'>Connection failed: " . $conn->connect_error . "</p>");
            }
            
            // Create database
            $sql = "CREATE DATABASE IF NOT EXISTS capstone";
            if ($conn->query($sql)) {
                echo "<p class='success'>Database 'capstone' created successfully!</p>";
            } else {
                echo "<p class='error'>Error creating database: " . $conn->error . "</p>";
            }
            
            // Select the database
            $conn->select_db("capstone");
            
            // Create tables
            $tables = [
                "CREATE TABLE IF NOT EXISTS jobseekers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    first_name VARCHAR(100) NOT NULL,
                    last_name VARCHAR(100) NOT NULL,
                    middle_name VARCHAR(100),
                    email VARCHAR(100) UNIQUE NOT NULL,
                    mobile VARCHAR(20) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    birth_date DATE,
                    sex VARCHAR(30),
                    civil_status VARCHAR(30),
                    address TEXT,
                    disability_type VARCHAR(100),
                    education VARCHAR(100),
                    course VARCHAR(150),
                    skills TEXT,
                    experience TEXT,
                    is_pwd TINYINT(1) DEFAULT 0,
                    pwd_id VARCHAR(100),
                    accessibility_needs TEXT,
                    id_file_path VARCHAR(255),
                    id_image_hash VARCHAR(64),
                    id_verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )",
                
                "CREATE TABLE IF NOT EXISTS employers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    company_name VARCHAR(255) NOT NULL,
                    contact_person VARCHAR(100) NOT NULL,
                    contact_email VARCHAR(100) UNIQUE NOT NULL,
                    contact_number VARCHAR(20) NOT NULL,
                    business_address TEXT NOT NULL,
                    business_permit VARCHAR(255),
                    password VARCHAR(255) NOT NULL,
                    accessibility_features TEXT,
                    inclusive_hiring ENUM('yes', 'no', 'planning') DEFAULT 'planning',
                    industry VARCHAR(100),
                    company_size VARCHAR(50),
                    position VARCHAR(100),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )",
                
                "CREATE TABLE IF NOT EXISTS peso_officers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    email VARCHAR(100) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    office VARCHAR(100),
                    position VARCHAR(100),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )",
                
                "CREATE TABLE IF NOT EXISTS job_postings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    employer_id INT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    description TEXT NOT NULL,
                    slots INT NOT NULL DEFAULT 1,
                    employment_type ENUM('Full-time', 'Part-time', 'Contract', 'Seasonal') NOT NULL,
                    required_skills TEXT NOT NULL,
                    pwd_friendly TINYINT(1) DEFAULT 0,
                    accessibility TEXT,
                    status ENUM('active', 'filled', 'expired') DEFAULT 'active',
                    posted_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (employer_id) REFERENCES employers(id) ON DELETE CASCADE
                )",
                
                "CREATE TABLE IF NOT EXISTS job_applications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    job_id INT NOT NULL,
                    jobseeker_id INT NOT NULL,
                    status ENUM('pending', 'reviewed', 'interview', 'hired', 'rejected') DEFAULT 'pending',
                    applied_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (job_id) REFERENCES job_postings(id) ON DELETE CASCADE,
                    FOREIGN KEY (jobseeker_id) REFERENCES jobseekers(id) ON DELETE CASCADE
                )"
            ];
            
            foreach ($tables as $sql) {
                if ($conn->query($sql)) {
                    echo "<p class='success'>Table created successfully!</p>";
                } else {
                    echo "<p class='error'>Error creating table: " . $conn->error . "</p>";
                }
            }
            
            // Create indexes
            $indexes = [
                "CREATE INDEX IF NOT EXISTS idx_job_postings_employer ON job_postings(employer_id)",
                "CREATE INDEX IF NOT EXISTS idx_job_postings_status ON job_postings(status)",
                "CREATE INDEX IF NOT EXISTS idx_applications_job ON job_applications(job_id)",
                "CREATE INDEX IF NOT EXISTS idx_applications_jobseeker ON job_applications(jobseeker_id)"
            ];
            
            foreach ($indexes as $sql) {
                $conn->query($sql);
            }
            
            $conn->close();
            
            echo "<h3 class='success'>Database setup completed!</h3>";
            echo "<p class='info'>You can now use the SkillBridge AI system.</p>";
            echo "<p><a href='index.php' class='btn'>Go to SkillBridge AI</a></p>";
        }
        ?>
        
        <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
            <form method="post">
                <h3>Database Configuration:</h3>
                <pre>
Host: localhost
Username: root
Password: (empty)
Database: capstone
                </pre>
                
                <p><strong>Requirements:</strong></p>
                <ul>
                    <li>XAMPP with MySQL running</li>
                    <li>phpMyAdmin accessible</li>
                    <li>MySQL user 'root' with no password</li>
                </ul>
                
                <p><button type="submit" class="btn">Create Database & Tables</button></p>
            </form>
        <?php endif; ?>
        
        <div class="info">
            <h3>Next Steps:</h3>
            <ol>
                <li>Run this setup script to create the database</li>
                <li>Access phpMyAdmin at: <a href="http://localhost/phpmyadmin" target="_blank">http://localhost/phpmyadmin</a></li>
                <li>Verify the 'skillbridge_ai' database was created</li>
                <li>Test the SkillBridge AI system</li>
            </ol>
        </div>
    </div>
</body>
</html>
