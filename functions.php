<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function url(string $page = 'home', array $params = []): string { return 'index.php?' . http_build_query(['page' => $page] + $params); }
function redirect(string $page, array $params = []): never { header('Location: ' . url($page, $params)); exit; }
function user(): ?array { return $_SESSION['user'] ?? null; }
function logged_in(): bool { return user() !== null; }
function require_login(): array { if (!logged_in()) { flash('error', 'Please sign in first.'); redirect('login'); } return user(); }
function require_role(string ...$roles): array { $u = require_login(); if (!in_array($u['role'], $roles, true)) { http_response_code(403); render_error('You do not have permission to open this page.'); } return $u; }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { http_response_code(419); render_error('Your form expired. Please go back and try again.'); } }
function flash(string $type, string $message): void { $_SESSION['flashes'][] = compact('type', 'message'); }
function flashes(): array { $items = $_SESSION['flashes'] ?? []; unset($_SESSION['flashes']); return $items; }
function notify(int $userId, string $message, ?int $jobId = null): void { $s=db()->prepare('INSERT INTO notifications (user_id,job_id,message) VALUES (?,?,?)'); $s->execute([$userId,$jobId,$message]); }
function post(string $key): string { return trim((string)($_POST[$key] ?? '')); }
function badge(string $status): string { return '<span class="badge badge-' . e($status) . '">' . e(ucwords(str_replace('_',' ',$status))) . '</span>'; }

function render_tutor_registration_fields(): void
{
    ?>
    <div id="tutor-registration-fields">
        <h3>Your tutor profile</h3>
        <p class="muted">Parents will use this information when reviewing your applications.</p>
        <label>Professional bio<textarea name="bio" minlength="20" placeholder="Describe your teaching approach and the learners you help."></textarea></label>
        <label>Subjects you teach<input name="subjects" placeholder="e.g. Mathematics, Physics, English"></label>
        <label>Qualifications<input name="qualifications" placeholder="e.g. BSc Mathematics, teaching certificate"></label>
        <div class="form-grid">
            <label>Years of experience<input type="number" min="0" name="experience_years" value="0"></label>
            <label>Rate per session (ETB)<input type="number" min="0" name="hourly_rate"></label>
            <label>Availability<input name="availability" placeholder="e.g. Weekday evenings"></label>
            <label>Teaching mode<select name="teaching_mode"><option value="both">Online and in person</option><option value="online">Online</option><option value="in_person">In person</option></select></label>
            <label>Location<input name="location" placeholder="e.g. Bole, Addis Ababa"></label>
            <label>Languages<input name="languages" placeholder="e.g. Amharic, English"></label>
        </div>
    </div>
    <?php
}

function render_header(string $title): void {
    $u=user(); $unread=0; $unreadMessages=0; $adminId=0;
    if ($u) { $s=db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0'); $s->execute([$u['id']]); $unread=(int)$s->fetchColumn(); $s=db()->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id=? AND is_read=0'); $s->execute([$u['id']]); $unreadMessages=(int)$s->fetchColumn(); if($u['role']!=='admin'){$adminId=(int)db()->query("SELECT id FROM users WHERE role='admin' AND is_suspended=0 ORDER BY id LIMIT 1")->fetchColumn();} }
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> · <?=APP_NAME?></title><link rel="stylesheet" href="styles.css"><?php if(($_GET['page']??'')==='admin'):?><link rel="stylesheet" href="assets/admin.css"><?php endif?></head><body>
    <header class="site-header"><a class="brand" href="<?=url()?>"><span>TL</span><?=APP_NAME?></a><button class="nav-toggle" aria-label="Toggle navigation" onclick="document.querySelector('.nav').classList.toggle('open')">☰</button><nav class="nav"><a href="<?=url('jobs')?>">Find jobs</a><?php if($u): ?><?php if(in_array($u['role'],['parent','admin'],true)):?><a href="<?=url('tutors')?>">Tutor profiles</a><?php endif?><a href="<?=url('dashboard')?>">Dashboard</a><?php if($u['role']==='parent'):?><a href="<?=url('my_jobs')?>">My jobs</a><?php elseif($u['role']==='student'):?><a href="<?=url('applications')?>">Applications</a><?php endif?><a href="<?=url('messages')?>">Messages<?php if($unreadMessages): ?><b class="count"><?=$unreadMessages?></b><?php endif?></a><?php if($adminId):?><a href="<?=url('messages',['with'=>$adminId])?>">Contact admin</a><?php endif?><a href="<?=url('notifications')?>">Notifications<?php if($unread): ?><b class="count"><?=$unread?></b><?php endif?></a><a href="<?=url('profile')?>">Profile</a><?php if($u['role']==='admin'): ?><a href="<?=url('admin')?>">Admin</a><?php endif?><form method="post" action="<?=url('logout')?>"><?=csrf_field()?><button class="link-button">Sign out</button></form><?php else: ?><a href="<?=url('login')?>">Sign in</a><a class="btn btn-small" href="<?=url('register')?>">Join free</a><?php endif?></nav></header>
    <main class="container"><?php foreach(flashes() as $f): ?><div class="alert alert-<?=e($f['type'])?>"><?=e($f['message'])?></div><?php endforeach?>
    <?php
}
function render_footer(): void { ?></main><footer>© <?=date('Y')?> <?=APP_NAME?> · Better learning starts with the right match.</footer><script src="assets/app.js"></script><?php if(($_GET['page']??'')==='admin'):?><script src="assets/admin.js"></script><?php endif?></body></html><?php }
function render_error(string $message): never { render_header('Something went wrong'); echo '<section class="empty"><h1>We could not complete that request</h1><p>'.e($message).'</p><a class="btn" href="'.url().'">Go home</a></section>'; render_footer(); exit; }
