<?php
require_once '../config.php';
requireRole('CLUB_LEADER');

$user_id = $_SESSION['user_id'];

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['schedule_viva'])) {
        $app_id = $_POST['app_id'];
        $date = $_POST['scheduled_at'];
        $interviewer = $_POST['interviewer_id']; // Optional member ID
        
        $stmt = $pdo->prepare("UPDATE viva_applications SET status = 'Scheduled', scheduled_at = ?, interviewer_id = ? WHERE id = ?");
        $stmt->execute([$date, $interviewer ?: null, $app_id]);
        
        // Notify Student
        $s_stmt = $pdo->prepare("SELECT user_id, club_id FROM viva_applications WHERE id = ?");
        $s_stmt->execute([$app_id]);
        $app = $s_stmt->fetch();
        $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")
            ->execute([$app['user_id'], "Your Viva has been scheduled for $date."]);

        // If interviewer assigned, add as Duty
        if ($interviewer) {
            $pdo->prepare("INSERT INTO club_duties (club_id, assigned_to, title, description, due_date) VALUES (?, ?, ?, ?, ?)")
                ->execute([$app['club_id'], $interviewer, "Conduct Viva", "Interview student for club entry", $date]);
             $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")
            ->execute([$interviewer, "You have been assigned to conduct a Viva on $date."]);
        }
        
        $success = "Viva scheduled successfully.";
    } 
    elseif (isset($_POST['update_status'])) {
        $app_id = $_POST['app_id'];
        $status = $_POST['status']; // Passed or Rejected
        $feedback = $_POST['feedback'];
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE viva_applications SET status = ?, feedback = ? WHERE id = ?");
            $stmt->execute([$status, $feedback, $app_id]);
            
            // Get App Details
            $s_stmt = $pdo->prepare("SELECT user_id, club_id FROM viva_applications WHERE id = ?");
            $s_stmt->execute([$app_id]);
            $app = $s_stmt->fetch();

            if ($status == 'Passed') {
                // Add to Club Membership
                $stmt = $pdo->prepare("INSERT INTO club_memberships (club_id, user_id) VALUES (?, ?)");
                $stmt->execute([$app['club_id'], $app['user_id']]);
                $membership_id = $pdo->lastInsertId();
                
                // Add Default Role
                $stmt = $pdo->prepare("INSERT INTO club_member_roles (membership_id, role_name) VALUES (?, 'MEMBER')");
                $stmt->execute([$membership_id]);
                
                $msg = "Congratulations! You passed the Viva and are now a member.";
            } else {
                $msg = "Viva Update: Your application was rejected. Feedback: $feedback";
            }
            
            $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")
                ->execute([$app['user_id'], $msg]);
            
            $pdo->commit();
            $success = "Viva status updated.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch Applications for My Clubs
$stmt = $pdo->prepare("
    SELECT v.*, u.full_name, u.email, c.name as club_name 
    FROM viva_applications v
    JOIN clubs c ON v.club_id = c.id
    JOIN users u ON v.user_id = u.id
    WHERE c.leader_id = ?
    ORDER BY v.created_at DESC
");
$stmt->execute([$user_id]);
$applications = $stmt->fetchAll();

// Fetch Members for Interviewer Selection
$m_stmt = $pdo->prepare("
    SELECT u.id, u.full_name, c.id as club_id
    FROM club_memberships cm
    JOIN users u ON cm.user_id = u.id
    JOIN clubs c ON cm.club_id = c.id
    WHERE c.leader_id = ?
");
$m_stmt->execute([$user_id]);
$members = $m_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Viva</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <div id="branding">
                <h1>UniClubs Leader</h1>
            </div>
            <nav>
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="../logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <h2>Manage Viva Applications</h2>
        
        <?php if (isset($success)) echo "<div class='alert success'>$success</div>"; ?>
        <?php if (isset($error)) echo "<div class='alert'>$error</div>"; ?>

        <div class="card">
            <?php if (empty($applications)): ?>
                <p>No applications yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Club</th>
                            <th>Student</th>
                            <th>Status</th>
                            <th>Scheduled At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($app['club_name']); ?></td>
                            <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                            <td><?php echo $app['status']; ?></td>
                            <td><?php echo $app['scheduled_at']; ?></td>
                            <td>
                                <?php if ($app['status'] == 'Pending'): ?>
                                    <form method="POST" action="">
                                        <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                                        <input type="datetime-local" name="scheduled_at" required>
                                        <select name="interviewer_id">
                                            <option value="">-- Assign Interviewer (Optional) --</option>
                                            <?php foreach ($members as $m): ?>
                                                <?php if($m['club_id'] == $app['club_id']): ?>
                                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['full_name']); ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="schedule_viva" class="button">Schedule</button>
                                    </form>
                                <?php elseif ($app['status'] == 'Scheduled'): ?>
                                    <form method="POST" action="">
                                        <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                                        <input type="text" name="feedback" placeholder="Feedback/Comments" required>
                                        <button type="submit" name="status" value="Passed" class="button success">Pass</button>
                                        <button type="submit" name="status" value="Rejected" class="button" style="background:red;">Reject</button>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                <?php else: ?>
                                    Completed
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>