<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$user = require_role('student');
$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id'])) {
    $courseId = (int) $_POST['course_id'];

    $courseCheck = $pdo->prepare('SELECT id FROM courses WHERE id = :id');
    $courseCheck->execute(['id' => $courseId]);

    if (!$courseCheck->fetch()) {
        add_flash($errors, 'Selected course no longer exists.');
    }

    if (empty($errors)) {
        $duplicate = $pdo->prepare(
            'SELECT id FROM join_requests WHERE student_id = :student_id AND course_id = :course_id'
        );
        $duplicate->execute([
            'student_id' => $user['id'],
            'course_id' => $courseId,
        ]);

        if ($duplicate->fetch()) {
            add_flash($errors, 'You have already requested to join this course.');
        }
    }

    if (empty($errors)) {
        $requestInsert = $pdo->prepare(
            'INSERT INTO join_requests (course_id, student_id) VALUES (:course_id, :student_id)'
        );
        $requestInsert->execute([
            'course_id' => $courseId,
            'student_id' => $user['id'],
        ]);

        $successMessage = 'Your request has been submitted. Wait for faculty approval.';
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
  'SELECT c.title, c.description, u.name AS instructor, jr.updated_at
   FROM join_requests jr
   JOIN courses c ON c.id = jr.course_id
   JOIN users u ON u.id = c.instructor_id
   WHERE jr.student_id = :student_id AND jr.status = "approved"
   ORDER BY jr.updated_at DESC'
);
$approvedCourses->execute(['student_id' => $user['id']]);
$enrolled = $approvedCourses->fetchAll(PDO::FETCH_ASSOC);
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
      <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
          <?php foreach ($errors as $error): ?>
            <p><?php echo escape($error); ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($successMessage): ?>
        <div class="alert alert-success">
          <p><?php echo escape($successMessage); ?></p>
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
                <input type="hidden" name="course_id" value="<?php echo escape($course['id']); ?>" />
                <button type="submit">Request to join</button>
              </form>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
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
