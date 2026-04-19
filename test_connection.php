<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .container { background: #f9f9f9; padding: 30px; border-radius: 10px; text-align: center; }
        .success { color: #27ae60; font-size: 18px; }
        .error { color: #e74c3c; font-size: 18px; }
        .btn { background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 10px; }
        .btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Connection Test</h1>
        
        <?php
        require_once 'assets/php/config.php';
        
        if ($conn->connect_error) {
            echo "<p class='error'>Connection failed: " . $conn->connect_error . "</p>";
        } else {
            echo "<p class='success'>Connected successfully to 'capstone' database!</p>";
            
            // Test if tables exist
            $tables = ['jobseekers', 'employers', 'peso_officers', 'job_postings', 'applications'];
            $existing_tables = [];
            
            foreach ($tables as $table) {
                $result = $conn->query("SHOW TABLES LIKE '$table'");
                if ($result->num_rows > 0) {
                    $existing_tables[] = $table;
                }
            }
            
            if (count($existing_tables) > 0) {
                echo "<p style='color: #3498db;'>Found " . count($existing_tables) . " tables: " . implode(', ', $existing_tables) . "</p>";
            } else {
                echo "<p style='color: #f39c12;'>No tables found yet. Run setup script first.</p>";
            }
        }
        ?>
        
        <div style="margin-top: 30px;">
            <a href="setup_database.php" class="btn">Run Setup Script</a>
            <a href="index.php" class="btn">Go to SkillBridge AI</a>
        </div>
    </div>
</body>
</html>
