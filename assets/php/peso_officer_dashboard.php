<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'peso_officer') {
    echo json_encode(['success' => false, 'message' => 'Please log in first.']);
    exit;
}
$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? '';
mysqli_query($conn, "UPDATE jobseekers SET id_verification='verified' WHERE id_verification IS NULL OR id_verification='pending'");
if ($action === 'verify_seeker') {
    $seeker_id = (int)($_POST['seeker_id'] ?? 0);
    $status    = $_POST['status'] ?? '';
    if (!in_array($status, ['verified','rejected'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status.']); exit;
    }
    $stmt = mysqli_prepare($conn, "UPDATE jobseekers SET id_verification=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'si', $status, $seeker_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true]);
    exit;
}
if ($action === 'get_seekers') {
    $col = mysqli_query($conn, "SHOW COLUMNS FROM jobseekers LIKE 'resume_status'");
    if (mysqli_num_rows($col) === 0) {
        mysqli_query($conn, "ALTER TABLE jobseekers ADD COLUMN resume_status ENUM('pending','approved','rejected') DEFAULT 'pending'");
    }
    $result = mysqli_query($conn,
        "SELECT id, first_name, last_name, email, mobile, is_pwd, disability_type,
                education, skills, experience,
                id_verification as id_verification_status,
                resume_status, created_at
         FROM jobseekers ORDER BY created_at DESC"
    );
    echo json_encode(['success' => true, 'seekers' => mysqli_fetch_all($result, MYSQLI_ASSOC)]);
    exit;
}
if ($action === 'get_seeker_resume') {
    $seeker_id = (int)($_POST['seeker_id'] ?? 0);
    $stmt = mysqli_prepare($conn,
        "SELECT id, first_name, last_name, email, mobile, birth_date, sex, civil_status,
                address, is_pwd, disability_type, accessibility_needs,
                education, course, skills, experience,
                id_verification as id_verification_status, resume_status
         FROM jobseekers WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $seeker_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) { echo json_encode(['success' => false, 'message' => 'Seeker not found.']); exit; }
    echo json_encode(['success' => true, 'seeker' => $row]);
    exit;
}
if ($action === 'update_resume_status') {
    $seeker_id = (int)($_POST['seeker_id'] ?? 0);
    $status    = $_POST['status'] ?? '';
    if (!in_array($status, ['pending','approved','rejected'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status.']); exit;
    }
    $stmt = mysqli_prepare($conn, "UPDATE jobseekers SET resume_status=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'si', $status, $seeker_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true]);
    exit;
}
if ($action === 'get_employers') {
    $result = mysqli_query($conn,
        "SELECT id, company_name, industry,
                COALESCE(NULLIF(contact_person,''), contact_name) as contact_person,
                contact_email, contact_number, inclusive_hiring, created_at
         FROM employers ORDER BY created_at DESC"
    );
    echo json_encode(['success' => true, 'employers' => mysqli_fetch_all($result, MYSQLI_ASSOC)]);
    exit;
}
$stmt = mysqli_prepare($conn, "SELECT name, email, office, position FROM peso_officers WHERE id=?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$officer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$officer) { echo json_encode(['success' => false, 'message' => 'Officer not found.']); exit; }
$q = function($sql) use ($conn) {
    $r = mysqli_query($conn, $sql);
    return $r ? (int)mysqli_fetch_assoc($r)['c'] : 0;
};
$stats = [
    'total_seekers'      => $q("SELECT COUNT(*) as c FROM jobseekers"),
    'pwd_seekers'        => $q("SELECT COUNT(*) as c FROM jobseekers WHERE is_pwd=1"),
    'total_employers'    => $q("SELECT COUNT(*) as c FROM employers"),
    'total_postings'     => $q("SELECT COUNT(*) as c FROM job_postings WHERE status='active'"),
    'total_applications' => $q("SELECT COUNT(*) as c FROM job_applications"),
    'total_hired'        => $q("SELECT COUNT(*) as c FROM job_applications WHERE status='hired'"),
    'pwd_hired'          => $q("SELECT COUNT(*) as c FROM job_applications ja JOIN jobseekers js ON js.id=ja.jobseeker_id WHERE ja.status='hired' AND js.is_pwd=1"),
];
$trend = [];
for ($i = 5; $i >= 0; $i--) {
    $month_label = date('M Y', strtotime("-$i months"));
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end   = date('Y-m-t',  strtotime("-$i months"));
    $apps  = $q("SELECT COUNT(*) as c FROM job_applications WHERE applied_date BETWEEN '$month_start' AND '$month_end 23:59:59'");
    $hired = $q("SELECT COUNT(*) as c FROM job_applications WHERE status='hired' AND applied_date BETWEEN '$month_start' AND '$month_end 23:59:59'");
    $trend[] = ['month' => $month_label, 'applications' => $apps, 'hired' => $hired];
}
$stats['monthly_trend'] = $trend;
$match_r = mysqli_query($conn,
    "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status='hired' THEN 1 ELSE 0 END) as hired,
        SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status='interview' THEN 1 ELSE 0 END) as interview,
        SUM(CASE WHEN status='reviewed' THEN 1 ELSE 0 END) as reviewed,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending
     FROM job_applications"
);
$match_data = mysqli_fetch_assoc($match_r) ?: ['total'=>0,'hired'=>0,'rejected'=>0,'interview'=>0,'reviewed'=>0,'pending'=>0];
$stats['match_rate'] = $match_data;
$ind_r = mysqli_query($conn,
    "SELECT e.industry, COUNT(ja.id) as hired_count
     FROM job_applications ja
     JOIN job_postings jp ON jp.id = ja.job_id
     JOIN employers e ON e.id = jp.employer_id
     WHERE ja.status = 'hired'
     GROUP BY e.industry ORDER BY hired_count DESC LIMIT 6"
);
$stats['placements_by_industry'] = mysqli_fetch_all($ind_r, MYSQLI_ASSOC);
$jobs_r = mysqli_query($conn,
    "SELECT jp.id, jp.title, jp.required_skills, e.company_name
     FROM job_postings jp JOIN employers e ON e.id=jp.employer_id
     WHERE jp.status='active' ORDER BY jp.posted_date DESC LIMIT 20"
);
$jobs_list = mysqli_fetch_all($jobs_r, MYSQLI_ASSOC);
$top_matches = [];
foreach ($jobs_list as $job) {
    $job_skills = array_map('trim', explode(',', strtolower($job['required_skills'])));
    $apps_r = mysqli_query($conn,
        "SELECT js.id, js.first_name, js.last_name, js.skills, js.education, js.is_pwd
         FROM job_applications ja
         JOIN jobseekers js ON js.id = ja.jobseeker_id
         WHERE ja.job_id = {$job['id']} AND js.resume_status='approved'"
    );
    $candidates = [];
    while ($c = mysqli_fetch_assoc($apps_r)) {
        $seeker_skills = array_map('trim', explode(',', strtolower($c['skills'] ?? '')));
        $matched = count(array_intersect($job_skills, $seeker_skills));
        $score   = $job_skills ? round(($matched / count($job_skills)) * 100) : 0;
        $candidates[] = ['name' => $c['first_name'].' '.$c['last_name'], 'score' => $score, 'education' => $c['education'], 'is_pwd' => $c['is_pwd']];
    }
    usort($candidates, fn($a,$b) => $b['score'] - $a['score']);
    $top_matches[] = ['job_id' => $job['id'], 'title' => $job['title'], 'company' => $job['company_name'], 'candidates' => array_slice($candidates, 0, 5)];
}
$stats['top_matches'] = $top_matches;
$demanded_raw = mysqli_query($conn, "SELECT required_skills FROM job_postings WHERE status='active'");
$demanded_count = [];
while ($row = mysqli_fetch_assoc($demanded_raw)) {
    foreach (array_map('trim', explode(',', strtolower($row['required_skills']))) as $sk) {
        if ($sk) $demanded_count[$sk] = ($demanded_count[$sk] ?? 0) + 1;
    }
}
arsort($demanded_count);
$top_demanded = array_slice($demanded_count, 0, 8, true);
$available_raw = mysqli_query($conn, "SELECT skills FROM jobseekers WHERE skills IS NOT NULL AND skills != ''");
$available_count = [];
while ($row = mysqli_fetch_assoc($available_raw)) {
    foreach (array_map('trim', explode(',', strtolower($row['skills']))) as $sk) {
        if ($sk) $available_count[$sk] = ($available_count[$sk] ?? 0) + 1;
    }
}
$skill_gap = [];
foreach ($top_demanded as $skill => $demand) {
    $skill_gap[] = ['skill' => $skill, 'demand' => $demand, 'supply' => $available_count[$skill] ?? 0];
}
$stats['skill_gap'] = $skill_gap;
$rec_r = mysqli_query($conn,
    "SELECT jp.id, jp.title, jp.required_skills, jp.employment_type, jp.pwd_friendly,
            e.company_name,
            COUNT(ja.id) as applicant_count,
            SUM(CASE WHEN ja.status='hired' THEN 1 ELSE 0 END) as hired_count
     FROM job_postings jp
     JOIN employers e ON e.id = jp.employer_id
     LEFT JOIN job_applications ja ON ja.job_id = jp.id
     WHERE jp.status = 'active'
     GROUP BY jp.id ORDER BY applicant_count DESC LIMIT 6"
);
$recommended = [];
while ($row = mysqli_fetch_assoc($rec_r)) {
    $apps = (int)$row['applicant_count'];
    $hired = (int)$row['hired_count'];
    $confidence = min(100, round(($apps * 5) + ($apps > 0 ? ($hired/$apps)*50 : 0)));
    $row['ai_confidence'] = $confidence;
    $recommended[] = $row;
}
$stats['recommended_jobs'] = $recommended;
$active_seekers = $q("SELECT COUNT(DISTINCT jobseeker_id) as c FROM job_applications WHERE applied_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stats['active_seekers']   = $active_seekers;
$stats['inactive_seekers'] = max(0, $stats['total_seekers'] - $active_seekers);
$stats['role_distribution'] = [
    ['role' => 'Job Seekers', 'count' => $stats['total_seekers']],
    ['role' => 'Employers',   'count' => $stats['total_employers']],
    ['role' => 'PESO Officers','count' => $q("SELECT COUNT(*) as c FROM peso_officers")],
];
$emp_part_r = mysqli_query($conn,
    "SELECT e.company_name, e.industry,
            COUNT(DISTINCT jp.id) as job_count,
            COUNT(DISTINCT ja.id) as app_count
     FROM employers e
     LEFT JOIN job_postings jp ON jp.employer_id = e.id
     LEFT JOIN job_applications ja ON ja.job_id = jp.id
     GROUP BY e.id ORDER BY job_count DESC, app_count DESC LIMIT 8"
);
$stats['employer_participation'] = mysqli_fetch_all($emp_part_r, MYSQLI_ASSOC);
$recent_reg_r = mysqli_query($conn,
    "SELECT first_name AS name, email, 'Job Seeker' AS role, created_at FROM jobseekers
     UNION ALL
     SELECT company_name AS name, contact_email AS email, 'Employer' AS role, created_at FROM employers
     ORDER BY created_at DESC LIMIT 10"
);
$stats['recently_registered'] = mysqli_fetch_all($recent_reg_r, MYSQLI_ASSOC);
$app_status_r = mysqli_query($conn,
    "SELECT status, COUNT(*) as c FROM job_applications GROUP BY status"
);
$app_status = ['pending'=>0,'reviewed'=>0,'interview'=>0,'hired'=>0,'rejected'=>0];
while ($row = mysqli_fetch_assoc($app_status_r)) $app_status[$row['status']] = (int)$row['c'];
$stats['app_status_breakdown'] = $app_status;
$stats['interview_stats'] = ['total'=>0,'completed'=>0,'noshow'=>0];
$r = mysqli_query($conn,
    "SELECT id, first_name, last_name, email FROM jobseekers
     WHERE id_verification='pending' ORDER BY created_at DESC LIMIT 5"
);
$stats['pending_seekers'] = mysqli_fetch_all($r, MYSQLI_ASSOC);
$r = mysqli_query($conn,
    "SELECT id, first_name, last_name, is_pwd,
            id_verification as id_verification_status, created_at
     FROM jobseekers ORDER BY created_at DESC LIMIT 5"
);
$stats['recent_seekers'] = mysqli_fetch_all($r, MYSQLI_ASSOC);
$r = mysqli_query($conn,
    "SELECT e.company_name, e.industry, e.inclusive_hiring,
            COUNT(j.id) as vacancy_count
     FROM employers e
     LEFT JOIN job_postings j ON j.employer_id = e.id AND j.status='active'
     GROUP BY e.id ORDER BY e.created_at DESC LIMIT 8"
);
$stats['employers'] = mysqli_fetch_all($r, MYSQLI_ASSOC);
echo json_encode(['success' => true, 'user' => $officer, 'stats' => $stats]);
?>