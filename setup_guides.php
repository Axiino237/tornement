<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Create guides table
    $sql = "CREATE TABLE IF NOT EXISTS guides (
        guide_id SERIAL PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) UNIQUE NOT NULL,
        content TEXT NOT NULL,
        category VARCHAR(50),
        image_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($sql);
    echo "Table 'guides' created or already exists.<br>";

    // Clear existing guides to avoid duplicates during setup
    $db->exec("DELETE FROM guides");

    $guides = [
        [
            'title' => 'Top 5 Free Fire Sensitivity Settings for Headshots',
            'slug' => 'top-5-free-fire-sensitivity-settings',
            'category' => 'Free Fire',
            'image_url' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?w=800',
            'content' => 'In Free Fire, sensitivity settings are crucial for landing those perfect headshots. Here are the top 5 configurations used by pro players... [Content continued for length]'
        ],
        [
            'title' => 'Mastering the Recoil in BGMI: A Complete Guide',
            'slug' => 'mastering-recoil-bgmi-guide',
            'category' => 'BGMI',
            'image_url' => 'https://images.unsplash.com/photo-1552824801-adc38448a05e?w=800',
            'content' => 'Recoil control is what separates average players from the elite in BGMI. Learn how to master different weapon patterns and attachments...'
        ],
        [
            'title' => 'How to Earn Money Playing Tournaments Online',
            'slug' => 'earn-money-playing-tournaments',
            'category' => 'General',
            'image_url' => 'https://images.unsplash.com/photo-1593305841991-05c297ba4575?w=800',
            'content' => 'The eSports industry is booming. Discover how you can turn your gaming passion into a source of income by joining FireCrown tournaments...'
        ],
        [
            'title' => 'Best Landing Spots in Free Fire Bermuda Map',
            'slug' => 'best-landing-spots-bermuda',
            'category' => 'Free Fire',
            'image_url' => 'https://images.unsplash.com/photo-1580234811497-9df7fd2f357e?w=800',
            'content' => 'Choosing the right landing spot can determine the outcome of your match. Here are the most strategic locations on Bermuda...'
        ],
        [
            'title' => 'Pro Tips for Among Us Impostors',
            'slug' => 'pro-tips-among-us-impostors',
            'category' => 'Among Us',
            'image_url' => 'https://images.unsplash.com/photo-1601987077677-5346c0c57d3f?w=800',
            'content' => 'Winning as an impostor requires deception and strategy. Learn how to fake tasks and use vents effectively...'
        ],
        [
            'title' => 'Minecraft Survival Guide: First Night Survival',
            'slug' => 'minecraft-survival-guide-first-night',
            'category' => 'Minecraft',
            'image_url' => 'https://images.unsplash.com/photo-1607988795691-3d0147b43231?w=800',
            'content' => 'Your first night in Minecraft can be dangerous. Here is everything you need to build your first shelter and survive...'
        ]
    ];

    // Adding more guides to reach 20+
    for ($i = 7; $i <= 22; $i++) {
        $guides[] = [
            'title' => "Advanced Gaming Strategy Guide #$i",
            'slug' => "advanced-strategy-guide-$i",
            'category' => 'General',
            'image_url' => 'https://images.unsplash.com/photo-1511512578047-dfb367046420?w=800',
            'content' => "This is an advanced strategy guide covering various aspects of competitive gaming, team coordination, and mental focus for tournament #$i."
        ];
    }

    $stmt = $db->prepare("INSERT INTO guides (title, slug, content, category, image_url) VALUES (?, ?, ?, ?, ?)");
    foreach ($guides as $guide) {
        $stmt->execute([$guide['title'], $guide['slug'], $guide['content'], $guide['category'], $guide['image_url']]);
    }

    echo "Successfully inserted " . count($guides) . " guides into the database.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
