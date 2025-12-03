<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$teachingRoles = ['faculty', 'intern'];
$user = require_roles($teachingRoles);
$errors = [];
$successMessage = '';

if ($user['role'] === 'faculty') {
  $coursesStmt = $pdo->prepare(
    'SELECT id, title FROM ' . TABLE_COURSES . ' WHERE instructor_id = :instructor_id ORDER BY title'
  );
  $coursesStmt->execute(['instructor_id' => $user['id']]);
} else {
  $coursesStmt = $pdo->prepare(
    'SELECT c.id, c.title
     FROM ' . TABLE_COURSE_STAFF . ' cs
     JOIN ' . TABLE_COURSES . ' c ON c.id = cs.course_id
     WHERE cs.staff_id = :staff_id
     ORDER BY c.title'
  );
  $coursesStmt->execute(['staff_id' => $user['id']]);
}
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
$courseIds = array_map('intval', array_column($courses, 'id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_session') {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $sessionDateInput = $_POST['session_date'] ?? '';
        $formattedDate = '';

        if (!in_array($courseId, $courseIds, true)) {
            add_flash($errors, 'Select one of your courses before creating a session.');
        }

        if ($title === '' || mb_strlen($title) < 4) {
            add_flash($errors, 'Provide a session title with at least 4 characters.');
        }

        if ($sessionDateInput === '') {
            add_flash($errors, 'Provide the session date and time.');
        } else {
          try {
            $formattedDate = (new DateTime($sessionDateInput))->format('Y-m-d H:i:s');
          } catch (Exception $e) {
            add_flash($errors, 'Provide a valid date and time.');
          }
        }

        if (empty($errors)) {
            $code = '';
            do {
                $code = generate_session_code();
            $existingCode = $pdo->prepare('SELECT 1 FROM ' . TABLE_COURSE_SESSIONS . ' WHERE access_code = :code');
                $existingCode->execute(['code' => $code]);
            } while ($existingCode->fetchColumn());

            $insert = $pdo->prepare(
            'INSERT INTO ' . TABLE_COURSE_SESSIONS . ' (course_id, title, session_date, access_code, created_by)
                 VALUES (:course_id, :title, :session_date, :code, :created_by)'
            );
            $insert->execute([
                'course_id' => $courseId,
                'title' => $title,
                'session_date' => $formattedDate,
                'code' => $code,
                'created_by' => $user['id'],
            ]);

            $successMessage = 'Session created with attendance code ' . $code . '.';
        }
    }

    if ($action === 'close_session') {
        $sessionId = (int) ($_POST['session_id'] ?? 0);
        $availability = $pdo->prepare(
          'SELECT cs.id, cs.course_id
           FROM ' . TABLE_COURSE_SESSIONS . ' cs
           JOIN ' . TABLE_COURSES . ' c ON c.id = cs.course_id
           WHERE cs.id = :session_id AND (
            c.instructor_id = :user_id OR
            EXISTS (
              SELECT 1 FROM ' . TABLE_COURSE_STAFF . ' cs2
              WHERE cs2.course_id = cs.course_id AND cs2.staff_id = :user_id
            )
           )'
        );
        $availability->execute([
          'session_id' => $sessionId,
          'user_id' => $user['id'],
        ]);

        if ($availability->fetch()) {
          $close = $pdo->prepare('UPDATE ' . TABLE_COURSE_SESSIONS . ' SET status = "closed" WHERE id = :id');
          $close->execute(['id' => $sessionId]);
          $successMessage = 'Session closed; students can no longer self-check in.';
        } else {
          add_flash($errors, 'Unable to close that session.');
        }
    }

    if ($action === 'mark_attendance') {
        $sessionId = (int) ($_POST['session_id'] ?? 0);
        $studentId = (int) ($_POST['student_id'] ?? 0);
        $statusChoice = $_POST['status'] ?? 'present';

        $sessionStmt = $pdo->prepare(
          'SELECT cs.id, cs.course_id, cs.status
           FROM ' . TABLE_COURSE_SESSIONS . ' cs
           JOIN ' . TABLE_COURSES . ' c ON c.id = cs.course_id
           WHERE cs.id = :session_id AND (
            c.instructor_id = :user_id OR
            EXISTS (
              SELECT 1 FROM ' . TABLE_COURSE_STAFF . ' cs2
              WHERE cs2.course_id = cs.course_id AND cs2.staff_id = :user_id
            )
           )'
        );
        $sessionStmt->execute([
          'session_id' => $sessionId,
          'user_id' => $user['id'],
        ]);
        $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            add_flash($errors, 'That session is not available.');
        } elseif (!is_student_enrolled($pdo, $studentId, (int) $session['course_id'])) {
            add_flash($errors, 'That student is not approved for this course.');
        }

        if (empty($errors)) {
            $record = $pdo->prepare(
              'INSERT INTO ' . TABLE_ATTENDANCE_RECORDS . ' (session_id, student_id, status, method, marked_by)
               VALUES (:session_id, :student_id, :status, "staff", :marked_by)
               ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by), method = "staff", marked_at = CURRENT_TIMESTAMP'
            );
            $record->execute([
                'session_id' => $sessionId,
                'student_id' => $studentId,
                'status' => in_array($statusChoice, ['present', 'absent', 'excused'], true) ? $statusChoice : 'present',
                'marked_by' => $user['id'],
            ]);

            $successMessage = 'Attendance updated for the selected student.';
        }
    }
}

$sessionsStmt = $pdo->prepare(
  'SELECT cs.id, cs.title, cs.session_date, cs.access_code, cs.status,
      c.title AS course_title,
      (
        SELECT COUNT(*) FROM ' . TABLE_ATTENDANCE_RECORDS . ' ar WHERE ar.session_id = cs.id AND ar.status = "present"
      ) AS present_count,
      (
        SELECT COUNT(*) FROM ' . TABLE_JOIN_REQUESTS . ' jr
        WHERE jr.course_id = cs.course_id AND jr.status = "approved"
      ) AS enrolled_count
   FROM ' . TABLE_COURSE_SESSIONS . ' cs
   JOIN ' . TABLE_COURSES . ' c ON c.id = cs.course_id
   WHERE c.instructor_id = :user_id OR EXISTS (
    SELECT 1 FROM ' . TABLE_COURSE_STAFF . ' cs2 WHERE cs2.course_id = cs.course_id AND cs2.staff_id = :user_id
   )
   ORDER BY cs.session_date DESC'
);
$sessionsStmt->execute(['user_id' => $user['id']]);
$sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);

$studentsStmt = $pdo->prepare(
  'SELECT jr.student_id, jr.course_id, u.name, c.title AS course_title
   FROM ' . TABLE_JOIN_REQUESTS . ' jr
   JOIN ' . TABLE_COURSES . ' c ON c.id = jr.course_id
   JOIN ' . TABLE_USERS . ' u ON u.id = jr.student_id
   WHERE jr.status = "approved" AND (
    c.instructor_id = :user_id OR EXISTS (
      SELECT 1 FROM ' . TABLE_COURSE_STAFF . ' cs2 WHERE cs2.course_id = c.id AND cs2.staff_id = :user_id
    )
   )
   ORDER BY c.title, u.name'
);
$studentsStmt->execute(['user_id' => $user['id']]);
$approvedStudents = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SmartRegister – Attendance Manager</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <nav class="primary-nav">
    <div class="logo">SmartRegister</div>
    <div class="nav-links">
      <a href="dashboard.php">Dashboard</a>
      <a href="attendance_manage.php" class="active">Attendance</a>
      <a href="faculty.php">Teaching Hub</a>
      <a href="logout.php">Log out</a>
    </div>
  </nav>

  <main class="attendance-wrapper">
    <section class="attendance-card">
      <header>
        <p class="eyebrow">Plan sessions</p>
        <h1>Create a class session</h1>
        <p class="muted">Generate an attendance code and keep a record for every lesson.</p>
      </header>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error" aria-live="polite">
          <?php foreach ($errors as $error): ?>
            <p><?php echo escape($error); ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($successMessage): ?>
        <div class="alert alert-success" aria-live="polite">
          <p><?php echo escape($successMessage); ?></p>
        </div>
      <?php endif; ?>

      <?php if (empty($courses)): ?>
        <p class="empty-state">No teaching assignments yet. Ask a faculty member to add you as an assistant.</p>
      <?php else: ?>
        <form method="POST" class="session-form">
          <input type="hidden" name="action" value="create_session" />
          <label for="course_id">Course</label>
          <select id="course_id" name="course_id" required>
            <option value="" disabled selected>Select a course</option>
            <?php foreach ($courses as $course): ?>
              <option value="<?php echo (int) $course['id']; ?>"><?php echo escape($course['title']); ?></option>
            <?php endforeach; ?>
          </select>

          <label for="title">Session title</label>
          <input type="text" id="title" name="title" placeholder="E.g. Week 5 Lab" required />

          <label for="session_date">Date & time</label>
          <input type="datetime-local" id="session_date" name="session_date" required />

          <button class="primary-btn" type="submit">Generate session & code</button>
        </form>
      <?php endif; ?>
    </section>

    <section class="attendance-table">
      <h2>Upcoming & past sessions</h2>
      <?php if (empty($sessions)): ?>
        <p class="empty-state">No sessions created yet. Start by adding one above.</p>
      <?php else: ?>
        <div class="session-list">
          <?php foreach ($sessions as $session): ?>
            <article class="session-card">
              <div class="session-card__header">
                <div>
                  <h3><?php echo escape($session['course_title']); ?></h3>
                  <p class="muted"><?php echo escape($session['title']); ?></p>
                </div>
                <span class="badge badge--<?php echo $session['status'] === 'open' ? 'pending' : 'approved'; ?>">
                  <?php echo ucfirst($session['status']); ?>
                </span>
              </div>
              <p class="muted">Code: <strong><?php echo escape($session['access_code']); ?></strong></p>
              <p><?php echo date('M j, Y g:i A', strtotime($session['session_date'])); ?></p>
              <p><?php echo (int) $session['present_count']; ?> present out of <?php echo (int) $session['enrolled_count']; ?></p>
              <?php if ($session['status'] === 'open'): ?>
                <form method="POST" class="inline-form">
                  <input type="hidden" name="action" value="close_session" />
                  <input type="hidden" name="session_id" value="<?php echo (int) $session['id']; ?>" />
                  <button type="submit">Close session</button>
                </form>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="attendance-card">
      <h2>Mark attendance manually</h2>
      <?php if (empty($approvedStudents) || empty($sessions)): ?>
        <p class="empty-state">No approved students or sessions yet.</p>
      <?php else: ?>
        <form method="POST" class="session-form">
          <input type="hidden" name="action" value="mark_attendance" />
          <label for="session_id">Session</label>
          <select id="session_id" name="session_id" required>
            <option value="" disabled selected>Select a session</option>
            <?php foreach ($sessions as $session): ?>
              <option value="<?php echo (int) $session['id']; ?>">
                <?php echo escape($session['course_title'] . ' • ' . $session['title']); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <label for="student_id">Student</label>
          <select id="student_id" name="student_id" required>
            <option value="" disabled selected>Select a student</option>
            <?php foreach ($approvedStudents as $student): ?>
              <option value="<?php echo (int) $student['student_id']; ?>">
                <?php echo escape($student['course_title'] . ' • ' . $student['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="present" selected>Present</option>
            <option value="absent">Absent</option>
            <option value="excused">Excused</option>
          </select>

          <button class="primary-btn" type="submit">Save attendance</button>
        </form>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
