<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
    $this->host = $_ENV['DB_HOST'] ?? 'localhost';
    $this->db_name = $_ENV['DB_NAME'] ?? 'esports_tournament';
    $this->username = $_ENV['DB_USER'] ?? 'root';
    $this->password = $_ENV['DB_PASSWORD'] ?? '';
    }

    public function getConnection() {
        $this->conn = null;

        try {
            // Use SQLite for development environment
            $dbPath = __DIR__ . '/../database/esports_tournament.db';
            $dbDir = dirname($dbPath);
            
            // Create directory if it doesn't exist
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            $this->conn = new PDO(
                "sqlite:" . $dbPath,
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            
            // Initialize database schema if tables don't exist
            $this->initializeSchema();
            
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
    
    private function initializeSchema() {
        if (!$this->conn) return;
        
        // Create tables if they don't exist
        $tables = [
            'users' => "
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    email VARCHAR(100) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    full_name VARCHAR(100) NOT NULL,
                    phone VARCHAR(20),
                    is_admin BOOLEAN DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ",
            'tournaments' => "
                CREATE TABLE IF NOT EXISTS tournaments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(200) NOT NULL,
                    description TEXT,
                    game_type VARCHAR(100) NOT NULL,
                    max_teams INTEGER NOT NULL,
                    entry_fee DECIMAL(10,2) DEFAULT 0,
                    prize_pool DECIMAL(10,2) DEFAULT 0,
                    start_date DATETIME,
                    end_date DATETIME,
                    status VARCHAR(20) DEFAULT 'upcoming',
                    thumbnail VARCHAR(255),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ",
            'tournament_registrations' => "
                CREATE TABLE IF NOT EXISTS tournament_registrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tournament_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    team_name VARCHAR(100) NOT NULL,
                    status VARCHAR(20) DEFAULT 'pending',
                    payment_status VARCHAR(20) DEFAULT 'pending',
                    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ",
            'matches' => "
                CREATE TABLE IF NOT EXISTS matches (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tournament_id INTEGER NOT NULL,
                    team1_id INTEGER NOT NULL,
                    team2_id INTEGER NOT NULL,
                    team1_name VARCHAR(100),
                    team2_name VARCHAR(100),
                    status VARCHAR(20) DEFAULT 'scheduled',
                    score1 INTEGER,
                    score2 INTEGER,
                    winner_id INTEGER,
                    scheduled_at DATETIME,
                    started_at DATETIME,
                    completed_at DATETIME,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
                    FOREIGN KEY (team1_id) REFERENCES users(id),
                    FOREIGN KEY (team2_id) REFERENCES users(id),
                    FOREIGN KEY (winner_id) REFERENCES users(id)
                )
            ",
            'match_screenshots' => "
                CREATE TABLE IF NOT EXISTS match_screenshots (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    match_id INTEGER NOT NULL,
                    team_id INTEGER NOT NULL,
                    screenshot_url VARCHAR(255) NOT NULL,
                    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
                    FOREIGN KEY (team_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE(match_id, team_id)
                )
            "
        ];
        
        foreach ($tables as $tableName => $sql) {
            try {
                $this->conn->exec($sql);
            } catch (PDOException $e) {
                error_log("Error creating table $tableName: " . $e->getMessage());
            }
        }
        
        // Insert default admin user if not exists
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE is_admin = 1");
            $stmt->execute();
            $adminCount = $stmt->fetchColumn();
            
            if ($adminCount == 0) {
                $stmt = $this->conn->prepare("
                    INSERT INTO users (username, email, password, full_name, is_admin) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute(['admin', 'admin@esports.com', password_hash('admin123', PASSWORD_DEFAULT), 'Administrator', 1]);
            }
        } catch (PDOException $e) {
            error_log("Error creating admin user: " . $e->getMessage());
        }
    }
}
?>
