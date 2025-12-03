<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$user = require_login();

$totalCourses = (int) $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();

if ($user['role'] === 'faculty') {
    $pendingRequests = $pdo->prepare(
        'SELECT COUNT(*) FROM join_requests jr
         JOIN courses c ON jr.course_id = c.id
         WHERE c.instructor_id = :instructor_id AND jr.status = "pending"'
    );
    $pendingRequests->execute(['instructor_id' => $user['id']]);
    $pendingRequestsCount = (int) $pendingRequests->fetchColumn();
} else {
    $pendingRequests = $pdo->prepare(
        'SELECT COUNT(*) FROM join_requests WHERE student_id = :student_id AND status = "pending"'
    );
    $pendingRequests->execute(['student_id' => $user['id']]);
    $pendingRequestsCount = (int) $pendingRequests->fetchColumn();
}

$courseList = $pdo->query(
    'SELECT c.id, c.title, c.description, c.created_at, u.name AS instructor
     FROM courses c
     JOIN users u ON u.id = c.instructor_id
     ORDER BY c.created_at DESC'
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SmartRegister – Dashboard</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <nav class="primary-nav">
    <div class="logo">SmartRegister</div>
    <div class="nav-links">
      <a href="dashboard.php" class="active">Dashboard</a>
      <?php if (in_array($user['role'], ['faculty', 'intern'], true)): ?>
        <a href="faculty.php">Teaching Hub</a>
        <a href="attendance_manage.php">Attendance</a>
      <?php endif; ?>
      <?php if ($user['role'] === 'student'): ?>
        <a href="student_dashboard.php">Student Dashboard</a>
      <?php endif; ?>
      <a href="logout.php">Log out</a>
    </div>
  </nav>

  <main class="dashboard-main">
    <section class="hero">
      <p class="eyebrow">Welcome back</p>
      <h1><?php echo escape($user['name']); ?> • <?php echo escape(ucfirst($user['role'])); ?></h1>
      <p class="subtitle">Create courses, review requests, and keep attendance on track.</p>
      <div class="actions">
        <?php if (in_array($user['role'], ['faculty', 'intern'], true)): ?>
          <a class="primary" href="attendance_manage.php">Open Attendance Hub</a>
        <?php elseif ($user['role'] === 'student'): ?>
          <a class="primary" href="student_dashboard.php">Go to Student Portal</a>
        <?php else: ?>
          <a class="primary" href="dashboard.php">View overview</a>
        <?php endif; ?>
      </div>
    </section>

    <section class="stats">
      <article>
        <p class="label">Courses</p>
        <h2><?php echo $totalCourses; ?></h2>
        <small><?php echo $totalCourses === 1 ? 'course created' : 'courses created'; ?></small>
      </article>
      <article>
        <p class="label">Pending requests</p>
        <h2><?php echo $pendingRequestsCount; ?></h2>
        <small>waiting for <?php echo $user['role'] === 'faculty' ? 'approval' : 'a response'; ?></small>
      </article>
    </section>

    <section id="courseGrid" class="course-grid" aria-label="Available courses">
      <?php if (empty($courseList)): ?>
        <p class="empty-state">No courses have been created yet.</p>
      <?php else: ?>
        <?php foreach ($courseList as $course): ?>
          <article class="course-card">
            <div class="course-card__header">
              <h3><?php echo escape($course['title']); ?></h3>
              <span><?php echo date('M j, Y', strtotime($course['created_at'])); ?></span>
            </div>
            <p><?php echo escape($course['description'] ?: 'No description yet.'); ?></p>
            <p class="muted">Instructor: <?php echo escape($course['instructor']); ?></p>
            <div class="course-card__actions">
              <?php if ($user['role'] === 'student'): ?>
                <a class="secondary" href="student_dashboard.php#courses">Request to join</a>
              <?php elseif ($user['role'] === 'faculty' && $course['instructor'] === $user['name']): ?>
                <a class="secondary" href="faculty.php#requests">Manage requests</a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </main>

</body>
</html>
