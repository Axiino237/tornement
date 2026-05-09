<?php
require_once 'config/database.php';

echo "<h2>Starting Database Updates...</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // 1. Update users table
    echo "Updating users table...<br>";
    
    // Check and add role column
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE users ADD COLUMN role ENUM('user', 'admin') DEFAULT 'user'");
        echo "- Added role column<br>";
    } else {
        echo "- role column already exists<br>";
    }
    
    // Check and add is_email_verified column
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'is_email_verified'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE users ADD COLUMN is_email_verified BOOLEAN DEFAULT FALSE");
        echo "- Added is_email_verified column<br>";
        
        // Auto-verify existing users to not break current flow
        $db->exec("UPDATE users SET is_email_verified = TRUE");
        echo "- Auto-verified existing users<br>";
    } else {
        echo "- is_email_verified column already exists<br>";
    }
    
    // Check and add verification_token column
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'verification_token'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE users ADD COLUMN verification_token VARCHAR(255) NULL");
        echo "- Added verification_token column<br>";
    } else {
        echo "- verification_token column already exists<br>";
    }
    
    // 2. Create games table
    echo "<br>Setting up games table...<br>";
    $db->exec("CREATE TABLE IF NOT EXISTS games (
        game_id INT PRIMARY KEY AUTO_INCREMENT,
        game_name VARCHAR(100) UNIQUE NOT NULL,
        image_url VARCHAR(255) NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active'
    )");
    echo "- Ensured games table exists<br>";
    
    // Insert initial games
    $initial_games = [
        ['Among Us', 'https://www.innersloth.com/wp-content/uploads/2024/06/2024roles_nologo.png'],
        ['Minecraft', 'https://m.economictimes.com/thumb/msid-98433841,width-1600,height-900,resizemode-4,imgsize-12430/minecraft-mods-how-to-install.jpg'],
        ['Free Fire', 'https://static-cdn.jtvnw.net/jtv_user_pictures/43aa2943-730e-427c-bcec-6cdef4d4c4d1-profile_banner-480.jpeg'],
        ['BGMI', 'https://images.firstpost.com/wp-content/uploads/2022/07/Explained-Why-Google-and-Apple-removed-BGMI-from-their-respective-app-stores-2-years-after-PUBG-ban-2.jpg']
    ];
    
    $stmt = $db->prepare("INSERT IGNORE INTO games (game_name, image_url) VALUES (?, ?)");
    foreach ($initial_games as $game) {
        $stmt->execute([$game[0], $game[1]]);
    }
    echo "- Inserted default games<br>";
    
    // Make the first user an admin for convenience
    $stmt = $db->query("SELECT user_id FROM users ORDER BY user_id ASC LIMIT 1");
    if ($first_user = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $db->exec("UPDATE users SET role = 'admin' WHERE user_id = " . $first_user['user_id']);
        echo "- Made the first user an admin<br>";
    }
    
    echo "<br><strong>Updates completed successfully!</strong>";
    echo "<br><a href='index.php'>Go to Homepage</a>";
    
} catch(PDOException $e) {
    echo "<br><strong>Error:</strong> " . $e->getMessage();
}
?>
