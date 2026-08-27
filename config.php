<?php
declare(strict_types=1);

const APP_NAME = 'TutorLink';

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $file = __DIR__ . '/hosting-config.php';
        $config = is_file($file) ? (array) require $file : [];
    }
    return $config;
}

function config_value(string $name, string $default = ''): string
{
    $environment = getenv($name);
    return $environment !== false ? $environment : (string) (app_config()[$name] ?? $default);
}

function db_driver(): string { return config_value('DB_DRIVER', 'sqlite'); }

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $driver = db_driver();
    $databaseUrl = config_value('DATABASE_URL');
    if ($databaseUrl !== '') {
        $parts = parse_url($databaseUrl);
        if ($parts === false || empty($parts['host']) || empty($parts['path'])) throw new RuntimeException('DATABASE_URL is invalid.');
        $driver = in_array($parts['scheme'] ?? '', ['postgres', 'postgresql'], true) ? 'pgsql' : $driver;
        $host = $parts['host'];
        $port = (string)($parts['port'] ?? 5432);
        $name = ltrim($parts['path'], '/');
        $user = rawurldecode((string)($parts['user'] ?? ''));
        $pass = rawurldecode((string)($parts['pass'] ?? ''));
        $pdo = new PDO("pgsql:host={$host};port={$port};dbname={$name};sslmode=require", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        initialize_postgres($pdo);
        return $pdo;
    }
    if ($driver === 'sqlite') {
        $dataDir = rtrim(config_value('DATA_DIR', __DIR__), '/\\');
        if (!is_dir($dataDir) && !mkdir($dataDir, 0700, true) && !is_dir($dataDir)) {
            throw new RuntimeException("Unable to create data directory: {$dataDir}");
        }
        $pdo = new PDO('sqlite:' . $dataDir . '/tutorlink.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        initialize_sqlite($pdo);
        return $pdo;
    }
    $host = config_value('DB_HOST', '127.0.0.1');
    $port = config_value('DB_PORT', '3306');
    $name = config_value('DB_NAME', 'tutorial_jobsite');
    $user = config_value('DB_USER', 'root');
    $pass = config_value('DB_PASS');
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    initialize_mysql($pdo);
    return $pdo;
}

function initialize_postgres(PDO $pdo): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS users (id BIGSERIAL PRIMARY KEY,name VARCHAR(120) NOT NULL,email VARCHAR(190) NOT NULL UNIQUE,password VARCHAR(255) NOT NULL,role VARCHAR(20) NOT NULL DEFAULT 'student',bio TEXT,subjects TEXT,qualifications TEXT,experience_years INTEGER NOT NULL DEFAULT 0,hourly_rate NUMERIC(12,2),availability TEXT,teaching_mode VARCHAR(20) NOT NULL DEFAULT 'both',location VARCHAR(190),languages TEXT,education_level VARCHAR(120),learning_goals TEXT,is_verified SMALLINT NOT NULL DEFAULT 0,is_suspended SMALLINT NOT NULL DEFAULT 0,created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP)",
        "CREATE TABLE IF NOT EXISTS tutoring_jobs (id BIGSERIAL PRIMARY KEY,user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,title VARCHAR(255) NOT NULL,tutee_name VARCHAR(120) NOT NULL,age INTEGER,subject VARCHAR(120) NOT NULL,education_level VARCHAR(120) NOT NULL,description TEXT NOT NULL,schedule VARCHAR(255) NOT NULL,payment NUMERIC(12,2) NOT NULL,location VARCHAR(190),teaching_mode VARCHAR(20) NOT NULL DEFAULT 'both',status VARCHAR(20) NOT NULL DEFAULT 'open',created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP)",
        "CREATE TABLE IF NOT EXISTS applications (id BIGSERIAL PRIMARY KEY,job_id BIGINT NOT NULL REFERENCES tutoring_jobs(id) ON DELETE CASCADE,student_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,cover_note TEXT,status VARCHAR(20) NOT NULL DEFAULT 'pending',created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,UNIQUE(job_id,student_id))",
        "CREATE TABLE IF NOT EXISTS saved_jobs (user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,job_id BIGINT NOT NULL REFERENCES tutoring_jobs(id) ON DELETE CASCADE,created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(user_id,job_id))",
        "CREATE TABLE IF NOT EXISTS notifications (id BIGSERIAL PRIMARY KEY,user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,job_id BIGINT REFERENCES tutoring_jobs(id) ON DELETE SET NULL,message TEXT NOT NULL,is_read SMALLINT NOT NULL DEFAULT 0,created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP)",
        "CREATE TABLE IF NOT EXISTS messages (id BIGSERIAL PRIMARY KEY,sender_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,receiver_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,job_id BIGINT REFERENCES tutoring_jobs(id) ON DELETE SET NULL,body TEXT NOT NULL,is_read SMALLINT NOT NULL DEFAULT 0,created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP)",
        "CREATE TABLE IF NOT EXISTS reviews (id BIGSERIAL PRIMARY KEY,job_id BIGINT NOT NULL REFERENCES tutoring_jobs(id) ON DELETE CASCADE,reviewer_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,reviewee_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,rating INTEGER NOT NULL,comment TEXT,created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,UNIQUE(job_id,reviewer_id))",
        "CREATE TABLE IF NOT EXISTS reports (id BIGSERIAL PRIMARY KEY,reporter_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,job_id BIGINT REFERENCES tutoring_jobs(id) ON DELETE SET NULL,reason TEXT NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'open',created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP)",
        "CREATE TABLE IF NOT EXISTS web_sessions (id VARCHAR(128) PRIMARY KEY,payload TEXT NOT NULL,updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP)",
    ];
    foreach ($statements as $statement) $pdo->exec($statement);
    bootstrap_admin($pdo);
}

function bootstrap_admin(PDO $pdo): void
{
    $email = strtolower(trim(config_value('ADMIN_EMAIL')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return;

    $statement = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $statement->execute([$email]);
    $userId = $statement->fetchColumn();

    if ($userId !== false) {
        $statement = $pdo->prepare("UPDATE users SET role = 'admin', is_verified = 1, is_suspended = 0 WHERE id = ?");
        $statement->execute([$userId]);
        return;
    }

    $password = config_value('ADMIN_PASSWORD');
    if (strlen($password) < 12) return;

    $name = trim(config_value('ADMIN_NAME', 'TutorLink Admin')) ?: 'TutorLink Admin';
    $statement = $pdo->prepare("INSERT INTO users(name,email,password,role,is_verified,is_suspended) VALUES(?,?,?,'admin',1,0)");
    $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
}

final class DatabaseSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string|false { $s=db()->prepare('SELECT payload FROM web_sessions WHERE id=?'); $s->execute([$id]); return (string)($s->fetchColumn() ?: ''); }
    public function write(string $id, string $data): bool { $s=db()->prepare('INSERT INTO web_sessions(id,payload,updated_at) VALUES(?,?,CURRENT_TIMESTAMP) ON CONFLICT(id) DO UPDATE SET payload=EXCLUDED.payload,updated_at=CURRENT_TIMESTAMP'); return $s->execute([$id,$data]); }
    public function destroy(string $id): bool { $s=db()->prepare('DELETE FROM web_sessions WHERE id=?'); return $s->execute([$id]); }
    public function gc(int $max_lifetime): int|false { $s=db()->prepare("DELETE FROM web_sessions WHERE updated_at < CURRENT_TIMESTAMP - (? * INTERVAL '1 second')"); $s->execute([$max_lifetime]); return $s->rowCount(); }
}

function initialize_mysql(PDO $pdo): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL,email VARCHAR(190) NOT NULL UNIQUE,password VARCHAR(255) NOT NULL,role VARCHAR(20) NOT NULL DEFAULT 'student',bio TEXT NULL,subjects TEXT NULL,qualifications TEXT NULL,experience_years INT NOT NULL DEFAULT 0,hourly_rate DECIMAL(12,2) NULL,availability TEXT NULL,teaching_mode VARCHAR(20) NOT NULL DEFAULT 'both',location VARCHAR(190) NULL,languages TEXT NULL,education_level VARCHAR(120) NULL,learning_goals TEXT NULL,is_verified TINYINT(1) NOT NULL DEFAULT 0,is_suspended TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS tutoring_jobs (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,title VARCHAR(255) NOT NULL,tutee_name VARCHAR(120) NOT NULL,age INT NULL,subject VARCHAR(120) NOT NULL,education_level VARCHAR(120) NOT NULL,description TEXT NOT NULL,schedule VARCHAR(255) NOT NULL,payment DECIMAL(12,2) NOT NULL,location VARCHAR(190) NULL,teaching_mode VARCHAR(20) NOT NULL DEFAULT 'both',status VARCHAR(20) NOT NULL DEFAULT 'open',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_jobs_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS applications (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,job_id INT UNSIGNED NOT NULL,student_id INT UNSIGNED NOT NULL,cover_note TEXT NULL,status VARCHAR(20) NOT NULL DEFAULT 'pending',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_application(job_id,student_id),CONSTRAINT fk_app_job FOREIGN KEY(job_id) REFERENCES tutoring_jobs(id) ON DELETE CASCADE,CONSTRAINT fk_app_student FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS saved_jobs (user_id INT UNSIGNED NOT NULL,job_id INT UNSIGNED NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(user_id,job_id),CONSTRAINT fk_saved_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_saved_job FOREIGN KEY(job_id) REFERENCES tutoring_jobs(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS notifications (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,job_id INT UNSIGNED NULL,message TEXT NOT NULL,is_read TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_notice_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_notice_job FOREIGN KEY(job_id) REFERENCES tutoring_jobs(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS messages (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,sender_id INT UNSIGNED NOT NULL,receiver_id INT UNSIGNED NOT NULL,job_id INT UNSIGNED NULL,body TEXT NOT NULL,is_read TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_message_sender FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_message_receiver FOREIGN KEY(receiver_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_message_job FOREIGN KEY(job_id) REFERENCES tutoring_jobs(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS reviews (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,job_id INT UNSIGNED NOT NULL,reviewer_id INT UNSIGNED NOT NULL,reviewee_id INT UNSIGNED NOT NULL,rating INT NOT NULL,comment TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_review(job_id,reviewer_id),CONSTRAINT fk_review_job FOREIGN KEY(job_id) REFERENCES tutoring_jobs(id) ON DELETE CASCADE,CONSTRAINT fk_review_author FOREIGN KEY(reviewer_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_review_subject FOREIGN KEY(reviewee_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS reports (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,reporter_id INT UNSIGNED NOT NULL,job_id INT UNSIGNED NULL,reason TEXT NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'open',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_report_user FOREIGN KEY(reporter_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_report_job FOREIGN KEY(job_id) REFERENCES tutoring_jobs(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($statements as $statement) $pdo->exec($statement);
}

function initialize_sqlite(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,email TEXT NOT NULL UNIQUE,password TEXT NOT NULL,role TEXT NOT NULL DEFAULT 'student',bio TEXT,subjects TEXT,qualifications TEXT,experience_years INTEGER DEFAULT 0,hourly_rate REAL,availability TEXT,teaching_mode TEXT DEFAULT 'both',location TEXT,languages TEXT,education_level TEXT,learning_goals TEXT,is_verified INTEGER DEFAULT 0,is_suspended INTEGER DEFAULT 0,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS tutoring_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL,title TEXT NOT NULL,tutee_name TEXT NOT NULL,age INTEGER,subject TEXT NOT NULL,education_level TEXT NOT NULL,description TEXT NOT NULL,schedule TEXT NOT NULL,payment REAL NOT NULL,location TEXT,teaching_mode TEXT DEFAULT 'both',status TEXT DEFAULT 'open',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS applications (id INTEGER PRIMARY KEY AUTOINCREMENT,job_id INTEGER NOT NULL,student_id INTEGER NOT NULL,cover_note TEXT,status TEXT DEFAULT 'pending',created_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(job_id,student_id),FOREIGN KEY(job_id) REFERENCES tutoring_jobs(id) ON DELETE CASCADE,FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS saved_jobs (user_id INTEGER NOT NULL,job_id INTEGER NOT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(user_id,job_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(job_id) REFERENCES tutoring_jobs(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS notifications (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL,job_id INTEGER,message TEXT NOT NULL,is_read INTEGER DEFAULT 0,created_at TEXT DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(job_id) REFERENCES tutoring_jobs(id) ON DELETE SET NULL);
CREATE TABLE IF NOT EXISTS messages (id INTEGER PRIMARY KEY AUTOINCREMENT,sender_id INTEGER NOT NULL,receiver_id INTEGER NOT NULL,job_id INTEGER,body TEXT NOT NULL,is_read INTEGER DEFAULT 0,created_at TEXT DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(receiver_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(job_id) REFERENCES tutoring_jobs(id) ON DELETE SET NULL);
CREATE TABLE IF NOT EXISTS reviews (id INTEGER PRIMARY KEY AUTOINCREMENT,job_id INTEGER NOT NULL,reviewer_id INTEGER NOT NULL,reviewee_id INTEGER NOT NULL,rating INTEGER NOT NULL,comment TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(job_id,reviewer_id),FOREIGN KEY(job_id) REFERENCES tutoring_jobs(id) ON DELETE CASCADE,FOREIGN KEY(reviewer_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(reviewee_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS reports (id INTEGER PRIMARY KEY AUTOINCREMENT,reporter_id INTEGER NOT NULL,job_id INTEGER,reason TEXT NOT NULL,status TEXT DEFAULT 'open',created_at TEXT DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(reporter_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(job_id) REFERENCES tutoring_jobs(id) ON DELETE SET NULL);
SQL);
    if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        $hash = password_hash('Password123!', PASSWORD_DEFAULT);
        $adminHash = password_hash('123456789', PASSWORD_DEFAULT);
        $s=$pdo->prepare('INSERT INTO users(name,email,password,role,bio,subjects,qualifications,experience_years,hourly_rate,availability,teaching_mode,location,languages,is_verified) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $s->execute(['Meron Alemu','admin@school.com',$adminHash,'admin','Platform administrator',null,null,0,null,null,'both','Addis Ababa','Amharic, English',1]);
        $s->execute(['Selam Tesfaye','parent@gmail.com',$hash,'parent','Parent seeking a patient and dependable tutor',null,null,0,null,'Weekday evenings','both','Addis Ababa','Amharic, English',1]);
        $s->execute(['Dawit Bekele','student@gmail.com',$hash,'student','Mathematics graduate who makes difficult ideas feel simple.','Mathematics, Physics','BSc in Mathematics',3,1200,'Evenings and weekends','both','Addis Ababa','Amharic, English',1]);
        $pdo->exec("INSERT INTO tutoring_jobs(user_id,title,tutee_name,age,subject,education_level,description,schedule,payment,location,teaching_mode) VALUES(2,'Patient mathematics tutor for Grade 8','Hana',14,'Mathematics','Grade 8','Help with algebra fundamentals and weekly revision.','Tuesday and Thursday, 5 PM',1000,'Bole, Addis Ababa','both')");
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (config_value('DATABASE_URL') !== '') {
        session_set_save_handler(new DatabaseSessionHandler(), true);
    } else {
        $sessionPath = rtrim(config_value('DATA_DIR', __DIR__), '/\\') . '/sessions';
        if (!is_dir($sessionPath)) mkdir($sessionPath, 0700, true);
        session_save_path($sessionPath);
    }
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => isset($_SERVER['HTTPS'])]);
    session_start();
}
