<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$user = require_roles(['faculty', 'intern']);
$errors = [];
$successMessage = '';
$assistantMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create_course') {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');

        if ($title === '' || mb_strlen($title) < 5) {
          add_flash($errors, 'Course title must be at least 5 characters.');
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'INSERT INTO courses (title, description, instructor_id) VALUES (:title, :description, :instructor_id)'
            );
            $stmt->execute([
                'title' => $title,
                'description' => $description,
                'instructor_id' => $user['id'],
            ]);

            $successMessage = 'Course created successfully.';
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_request' && isset($_POST['request_id'])) {
        $requestId = (int) $_POST['request_id'];
        $status = in_array($_POST['status'] ?? '', ['approved', 'rejected'], true)
            ? $_POST['status']
            : 'pending';

        $update = $pdo->prepare(
            'UPDATE join_requests SET status = :status, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND course_id IN (SELECT id FROM courses WHERE instructor_id = :instructor_id)'
        );
        $update->execute([
            'status' => $status,
            'id' => $requestId,
            'instructor_id' => $user['id'],
        ]);

        if ($update->rowCount() > 0) {
          $successMessage = 'Request status updated.';
        } else {
          add_flash($errors, 'Unable to update that request.');
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'add_staff' && $user['role'] === 'faculty') {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $email = strtolower(trim($_POST['staff_email'] ?? ''));

        if (!user_has_course_access($pdo, $user, $courseId)) {
            add_flash($errors, 'You can only add assistants to your own courses.');
        }

        if ($email === '') {
            add_flash($errors, 'Provide an email to add as an assistant.');
        }

        if (empty($errors)) {
            $staffLookup = $pdo->prepare('SELECT id, role FROM users WHERE email = :email');
            $staffLookup->execute(['email' => $email]);
            $staff = $staffLookup->fetch(PDO::FETCH_ASSOC);

            if (!$staff || !in_array($staff['role'], ['intern', 'faculty'], true)) {
                add_flash($errors, 'User must exist and be an intern or faculty member.');
            } else {
                $assign = $pdo->prepare(
                    'INSERT OR IGNORE INTO course_staff (course_id, staff_id, role) VALUES (:course_id, :staff_id, :role)'
                );
                $assign->execute([
                    'course_id' => $courseId,
                    'staff_id' => $staff['id'],
                    'role' => $staff['role'],
                ]);

                $assistantMessage = 'Assistant added to the course.';
            }
        }
    }
}

$coursesQuery = 'SELECT id, title, description, created_at FROM courses WHERE instructor_id = :instructor_id ORDER BY created_at DESC';
$courses = $pdo->prepare($coursesQuery);
$courses->execute(['instructor_id' => $user['id']]);
$courseList = $courses->fetchAll(PDO::FETCH_ASSOC);

$assistantsByCourse = [];
if (!empty($courseList)) {
  $courseIds = array_column($courseList, 'id');
  $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
  $assistantStmt = $pdo->prepare(
    "SELECT cs.course_id, u.name, u.email, cs.role
     FROM course_staff cs
     JOIN users u ON u.id = cs.staff_id
     WHERE cs.course_id IN ($placeholders)
     ORDER BY u.name"
  );
  $assistantStmt->execute($courseIds);
  foreach ($assistantStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $assistantsByCourse[$row['course_id']][] = $row;
  }
}

$requests = $pdo->prepare(
    'SELECT jr.id, jr.status, jr.created_at, c.title, s.name AS student_name
     FROM join_requests jr
     JOIN courses c ON c.id = jr.course_id
     JOIN users s ON s.id = jr.student_id
     WHERE c.instructor_id = :instructor_id
     ORDER BY jr.created_at DESC'
);
$requests->execute(['instructor_id' => $user['id']]);
$requestList = $requests->fetchAll(PDO::FETCH_ASSOC);

$requestCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($requestList as $request) {
    $status = $request['status'] ?? 'pending';
    if (!isset($requestCounts[$status])) {
        $requestCounts[$status] = 0;
    }
    $requestCounts[$status]++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SmartRegister – Faculty Hub</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <nav class="primary-nav">
    <div class="logo">SmartRegister</div>
    <div class="nav-links">
      <a href="dashboard.php">Dashboard</a>
      <a href="faculty.php" class="active">Teaching Hub</a>
      <a href="attendance_manage.php">Attendance</a>
      <a href="logout.php">Log out</a>
    </div>
  </nav>

  <main class="faculty-page">
    <section class="faculty-hero">
      <div class="hero-copy">
        <p class="eyebrow">Faculty hub</p>
        <h1>Guide students into meaningful attendance experiences</h1>
        <p class="muted">Create courses, approve requests, and keep everything documented so every class has a clear owner.</p>
        <div class="hero-actions">
          <a class="primary-btn" href="#course-form">Create a course</a>
          <a class="text-link" href="#requests">Check requests</a>
        </div>
      </div>
      <div class="faculty-stats">
        <article class="stat-card">
          <p class="label">Published courses</p>
          <h2><?php echo count($courseList); ?></h2>
        </article>
        <article class="stat-card">
          <p class="label">Pending requests</p>
          <h2><?php echo $requestCounts['pending']; ?></h2>
        </article>
        <article class="stat-card">
          <p class="label">Approved so far</p>
          <h2><?php echo $requestCounts['approved']; ?></h2>
        </article>
      </div>
    </section>

    <div class="faculty-layout">
      <section class="faculty-card" id="course-form">
        <div class="section-head">
          <h2>Create a new course</h2>
          <p class="muted">Let students know what to expect before they request to join.</p>
        </div>

        <div class="message-stack">
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
          <?php if ($assistantMessage): ?>
            <div class="alert alert-success" aria-live="polite">
              <p><?php echo escape($assistantMessage); ?></p>
            </div>
          <?php endif; ?>
        </div>

        <form method="POST" class="course-form" novalidate>
          <input type="hidden" name="action" value="create_course" />

          <div class="form-grid">
            <label for="title">Course title</label>
            <input type="text" id="title" name="title" placeholder="E.g. Systems Analysis" required minlength="5" />
          </div>

          <div class="form-grid">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3" placeholder="Short course overview."></textarea>
          </div>

          <div class="guidelines">
            <p class="muted">Tips for a clear listing:</p>
            <ul>
              <li>Use at least 5 characters for the title.</li>
              <li>Explain what students will attend and accomplish.</li>
              <li>Keep descriptions short so students can scan multiple options.</li>
            </ul>
          </div>

          <button class="primary-btn" type="submit">Create course</button>
        </form>
      </section>

      <section class="request-board" id="requests">
        <header>
          <h2>Student requests</h2>
          <p class="muted">Approve or reject, and remind students of next steps.</p>
        </header>
        <?php if (empty($requestList)): ?>
          <p class="empty-state">No pending requests yet.</p>
        <?php else: ?>
          <div class="request-list">
            <?php foreach ($requestList as $request): ?>
              <article class="request-card">
                <div class="request-card__header">
                  <div>
                    <h3><?php echo escape($request['student_name']); ?></h3>
                    <p class="muted">Course: <?php echo escape($request['title']); ?></p>
                  </div>
                  <span class="badge badge--<?php echo escape($request['status']); ?>"><?php echo escape(ucfirst($request['status'])); ?></span>
                </div>
                <p class="muted">Requested on <?php echo date('M j, Y', strtotime($request['created_at'])); ?></p>
                <form method="POST" class="request-actions">
                  <input type="hidden" name="action" value="update_request" />
                  <input type="hidden" name="request_id" value="<?php echo escape($request['id']); ?>" />
                  <select name="status">
                    <option value="pending" <?php echo $request['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $request['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $request['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                  </select>
                  <button type="submit">Save</button>
                </form>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="course-table">
        <h2>Your published courses</h2>
        <?php if (empty($courseList)): ?>
          <p class="empty-state">You haven’t created any courses yet.</p>
        <?php else: ?>
          <div class="course-list">
            <?php foreach ($courseList as $course): ?>
              <article class="course-card">
                <div class="course-card__top">
                  <strong><?php echo escape($course['title']); ?></strong>
                  <span class="muted"><?php echo date('M j, Y', strtotime($course['created_at'])); ?></span>
                </div>
                <p><?php echo escape($course['description'] ?: 'No description provided.'); ?></p>
                <?php if (!empty($assistantsByCourse[$course['id']])): ?>
                  <div class="assistant-list">
                    <p class="muted">Assistants</p>
                    <ul>
                      <?php foreach ($assistantsByCourse[$course['id']] as $assistant): ?>
                        <li>
                          <strong><?php echo escape($assistant['name']); ?></strong>
                          <span><?php echo escape($assistant['email']); ?></span>
                          <span class="badge badge--approved"><?php echo ucfirst($assistant['role']); ?></span>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>
                <?php if ($user['role'] === 'faculty'): ?>
                  <form method="POST" class="assistant-form">
                    <input type="hidden" name="action" value="add_staff" />
                    <input type="hidden" name="course_id" value="<?php echo (int) $course['id']; ?>" />
                    <label for="assistant-<?php echo (int) $course['id']; ?>">Add assistant (email)</label>
                    <input type="email" id="assistant-<?php echo (int) $course['id']; ?>" name="staff_email" placeholder="intern@example.com" required />
                    <button type="submit">Add assistant</button>
                  </form>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>

</body>
</html>
