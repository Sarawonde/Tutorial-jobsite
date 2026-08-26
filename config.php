<?php
declare(strict_types=1);

const APP_NAME = 'TutorLink';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $driver = getenv('DB_DRIVER') ?: 'sqlite';
    if ($driver === 'sqlite') {
        $dataDir = rtrim(getenv('DATA_DIR') ?: __DIR__, '/\\');
        if (!is_dir($dataDir) && !mkdir($dataDir, 0700, true) && !is_dir($dataDir)) {
            throw new RuntimeException("Unable to create data directory: {$dataDir}");
        }
        $pdo = new PDO('sqlite:' . $dataDir . '/tutorlink.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        initialize_sqlite($pdo);
        return $pdo;
    }
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'tutorial_jobsite';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
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
    $sessionPath = rtrim(getenv('DATA_DIR') ?: __DIR__, '/\\') . '/sessions';
    if (!is_dir($sessionPath)) mkdir($sessionPath, 0700, true);
    session_save_path($sessionPath);
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => isset($_SERVER['HTTPS'])]);
    session_start();
}
