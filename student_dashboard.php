<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$user = require_role('student');
$joinErrors = [];
$attendanceErrors = [];
$joinSuccess = '';
$attendanceSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'request_course' && isset($_POST['course_id'])) {
    $courseId = (int) $_POST['course_id'];

    $courseCheck = $pdo->prepare('SELECT id FROM courses WHERE id = :id');
    $courseCheck->execute(['id' => $courseId]);

    if (!$courseCheck->fetch()) {
      add_flash($joinErrors, 'Selected course no longer exists.');
    }

    if (empty($joinErrors)) {
      $duplicate = $pdo->prepare(
        'SELECT id FROM join_requests WHERE student_id = :student_id AND course_id = :course_id'
      );
      $duplicate->execute([
        'student_id' => $user['id'],
        'course_id' => $courseId,
      ]);

      if ($duplicate->fetch()) {
        add_flash($joinErrors, 'You have already requested to join this course.');
      }
    }

    if (empty($joinErrors)) {
      $requestInsert = $pdo->prepare(
        'INSERT INTO join_requests (course_id, student_id) VALUES (:course_id, :student_id)'
      );
      $requestInsert->execute([
        'course_id' => $courseId,
        'student_id' => $user['id'],
      ]);

      $joinSuccess = 'Your request has been submitted. Wait for faculty approval.';
    }
  }

  if ($action === 'mark_attendance') {
    $codeInput = strtoupper(trim($_POST['attendance_code'] ?? ''));

    if ($codeInput === '') {
      add_flash($attendanceErrors, 'Enter the attendance code shared in class.');
    }

    if (empty($attendanceErrors)) {
      $sessionStmt = $pdo->prepare(
        'SELECT cs.id, cs.course_id, cs.status, cs.session_date, c.title AS course_title
         FROM course_sessions cs
         JOIN courses c ON c.id = cs.course_id
         WHERE cs.access_code = :code'
      );
      $sessionStmt->execute(['code' => $codeInput]);
      $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);

      if (!$session || $session['status'] !== 'open') {
        add_flash($attendanceErrors, 'That attendance code is not available right now.');
      } elseif (!is_student_enrolled($pdo, $user['id'], (int) $session['course_id'])) {
        add_flash($attendanceErrors, 'You are not approved for the course that uses this code.');
      } else {
        $record = $pdo->prepare(
          'INSERT INTO attendance_records (session_id, student_id, status, method)
           VALUES (:session_id, :student_id, "present", "self")
           ON CONFLICT(session_id, student_id)
           DO UPDATE SET status = "present", method = "self", marked_at = CURRENT_TIMESTAMP'
        );
        $record->execute([
          'session_id' => $session['id'],
          'student_id' => $user['id'],
        ]);

        $attendanceSuccess = 'Attendance recorded for ' . $session['course_title'] . '.';
      }
    }
  }
}

$availableCourses = $pdo->query(
    'SELECT c.id, c.title, c.description, u.name AS instructor FROM courses c JOIN users u ON u.id = c.instructor_id ORDER BY c.created_at DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$requestHistory = $pdo->prepare(
    'SELECT jr.status, jr.created_at, c.title
     FROM join_requests jr
     JOIN courses c ON c.id = jr.course_id
     WHERE jr.student_id = :student_id
     ORDER BY jr.created_at DESC'
);
$requestHistory->execute(['student_id' => $user['id']]);
$requests = $requestHistory->fetchAll(PDO::FETCH_ASSOC);

$approvedCourses = $pdo->prepare(
  'SELECT c.id AS course_id, c.title, c.description, u.name AS instructor, jr.updated_at
   FROM join_requests jr
   JOIN courses c ON c.id = jr.course_id
   JOIN users u ON u.id = c.instructor_id
   WHERE jr.student_id = :student_id AND jr.status = "approved"
   ORDER BY jr.updated_at DESC'
);
$approvedCourses->execute(['student_id' => $user['id']]);
$enrolled = $approvedCourses->fetchAll(PDO::FETCH_ASSOC);

$today = date('Y-m-d');
$todaySessionsStmt = $pdo->prepare(
    'SELECT cs.id, cs.title, cs.session_date, c.title AS course_title,
            IFNULL(ar.status, "not_marked") AS attendance_status
     FROM course_sessions cs
     JOIN courses c ON c.id = cs.course_id
     JOIN join_requests jr ON jr.course_id = cs.course_id AND jr.student_id = :student_id AND jr.status = "approved"
     LEFT JOIN attendance_records ar ON ar.session_id = cs.id AND ar.student_id = :student_id
     WHERE DATE(cs.session_date) = :today
     ORDER BY cs.session_date DESC'
);
$todaySessionsStmt->execute([
    'student_id' => $user['id'],
    'today' => $today,
]);
$todaySessions = $todaySessionsStmt->fetchAll(PDO::FETCH_ASSOC);

$overallStmt = $pdo->prepare(
    'SELECT c.id, c.title,
            COUNT(DISTINCT cs.id) AS total_sessions,
            SUM(CASE WHEN ar.status = "present" THEN 1 ELSE 0 END) AS presents
     FROM join_requests jr
     JOIN courses c ON c.id = jr.course_id
     LEFT JOIN course_sessions cs ON cs.course_id = c.id
     LEFT JOIN attendance_records ar ON ar.session_id = cs.id AND ar.student_id = :student_id AND ar.status = "present"
     WHERE jr.student_id = :student_id AND jr.status = "approved"
     GROUP BY c.id, c.title'
);
$overallStmt->execute(['student_id' => $user['id']]);
$attendanceTotals = $overallStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SmartRegister – Student Portal</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <nav class="primary-nav">
    <div class="logo">SmartRegister</div>
    <div class="nav-links">
      <a href="dashboard.php">Dashboard</a>
      <a href="student_dashboard.php" class="active">Student Portal</a>
      <a href="logout.php">Log out</a>
    </div>
  </nav>

  <main class="student-wrapper" id="courses">
    <section class="student-card">
      <h2>Request to join a course</h2>
      <?php if (!empty($joinErrors)): ?>
        <div class="alert alert-error">
          <?php foreach ($joinErrors as $error): ?>
            <p><?php echo escape($error); ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($joinSuccess): ?>
        <div class="alert alert-success">
          <p><?php echo escape($joinSuccess); ?></p>
        </div>
      <?php endif; ?>
      <div class="course-offerings">
        <?php if (empty($availableCourses)): ?>
          <p class="empty-state">No courses are available. Check back later.</p>
        <?php else: ?>
          <?php foreach ($availableCourses as $course): ?>
            <article class="course-card compact">
              <h3><?php echo escape($course['title']); ?></h3>
              <p class="muted">Instructor: <?php echo escape($course['instructor']); ?></p>
              <p><?php echo escape($course['description'] ?: 'No description provided.'); ?></p>
              <form method="POST">
                <input type="hidden" name="action" value="request_course" />
                <input type="hidden" name="course_id" value="<?php echo escape($course['id']); ?>" />
                <button type="submit">Request to join</button>
              </form>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="student-card">
      <h2>Mark attendance with a code</h2>
      <?php if (!empty($attendanceErrors)): ?>
        <div class="alert alert-error">
          <?php foreach ($attendanceErrors as $error): ?>
            <p><?php echo escape($error); ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($attendanceSuccess): ?>
        <div class="alert alert-success">
          <p><?php echo escape($attendanceSuccess); ?></p>
        </div>
      <?php endif; ?>
      <form method="POST" class="attendance-code-form">
        <input type="hidden" name="action" value="mark_attendance" />
        <label for="attendance_code">Code</label>
        <input type="text" id="attendance_code" name="attendance_code" placeholder="Enter 6-character code" maxlength="6" required />
        <button type="submit">Submit attendance</button>
      </form>
    </section>

    <section class="approved-courses">
      <h2>Your approved courses</h2>
      <?php if (empty($enrolled)): ?>
        <p class="empty-state">You are not enrolled in any courses yet.</p>
      <?php else: ?>
        <div class="course-list">
          <?php foreach ($enrolled as $course): ?>
            <article class="course-card compact">
              <h3><?php echo escape($course['title']); ?></h3>
              <p class="muted">Instructor: <?php echo escape($course['instructor']); ?></p>
              <p><?php echo escape($course['description'] ?: 'No description provided.'); ?></p>
              <small>Joined on <?php echo date('M j, Y', strtotime($course['updated_at'])); ?></small>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="student-card">
      <h2>Today’s sessions</h2>
      <?php if (empty($todaySessions)): ?>
        <p class="empty-state">No sessions scheduled today.</p>
      <?php else: ?>
        <ul class="attendance-list">
          <?php foreach ($todaySessions as $session): ?>
            <li>
              <strong><?php echo escape($session['course_title']); ?></strong>
              <span><?php echo escape($session['title']); ?> • <?php echo date('g:i A', strtotime($session['session_date'])); ?></span>
              <span class="badge badge--<?php echo $session['attendance_status'] === 'present' ? 'approved' : ($session['attendance_status'] === 'not_marked' ? 'pending' : 'rejected'); ?>">
                <?php echo $session['attendance_status'] === 'not_marked' ? 'Not marked' : ucfirst($session['attendance_status']); ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="student-card">
      <h2>Attendance summary</h2>
      <?php if (empty($attendanceTotals)): ?>
        <p class="empty-state">No attendance records yet.</p>
      <?php else: ?>
        <div class="attendance-summary">
          <?php foreach ($attendanceTotals as $row): ?>
            <article class="course-card compact">
              <h3><?php echo escape($row['title']); ?></h3>
              <p>
                <?php
                  $totalSessions = (int) $row['total_sessions'];
                  $present = (int) ($row['presents'] ?? 0);
                  echo $totalSessions === 0
                      ? 'No sessions held yet.'
                      : $present . ' of ' . $totalSessions . ' marked present';
                ?>
              </p>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="request-history">
      <h2>Your requests</h2>
      <?php if (empty($requests)): ?>
        <p class="empty-state">You have not made any requests yet.</p>
      <?php else: ?>
        <ul>
          <?php foreach ($requests as $row): ?>
            <li>
              <div>
                <strong><?php echo escape($row['title']); ?></strong>
                <p><?php echo date('M j, Y', strtotime($row['created_at'])); ?> • <?php echo ucfirst($row['status']); ?></p>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </main>

</body>
</html>
