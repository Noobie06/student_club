<?php
require_once '../config.php';
requireRole('CLUB_LEADER');

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$event_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Verify this event belongs to a club led by this user
$stmt = $pdo->prepare("SELECT e.*, c.leader_id FROM events e JOIN clubs c ON e.club_id = c.id WHERE e.id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event || $event['leader_id'] != $user_id) {
    die("Access Denied: You do not manage this event.");
}

// Handle Attendance Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_participation'])) {
    $p_id = $_POST['participation_id'];
    $status = $_POST['attendance_status'];
    $hours = $_POST['contribution_hours'];
    $task = $_POST['task_completion_status'];
    
    // Calculate Rewards
    // Logic: 10 points per hour + 50 points if task completed
    $points = ($hours * 10) + ($task == 'Completed' ? 50 : 0);
    if ($status == 'Absent') $points = 0;

    $stmt = $pdo->prepare("UPDATE participations SET attendance_status = ?, contribution_hours = ?, task_completion_status = ?, reward_points = ? WHERE id = ?");
    $stmt->execute([$status, $hours, $task, $points, $p_id]);

    // Send Notification
    $p_user_id = $_POST['user_id'];
    $msg = "Your participation for event '{$event['title']}' has been updated. You earned $points points.";
    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$p_user_id, $msg]);

    $success = "Participation updated.";
}

// Fetch Participants
$stmt = $pdo->prepare("SELECT p.*, u.full_name, u.email FROM participations p JOIN users u ON p.user_id = u.id WHERE p.event_id = ?");
$stmt->execute([$event_id]);
$participants = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Event</title>
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
        <h2>Manage Event: <?php echo htmlspecialchars($event['title']); ?></h2>
        
        <?php if (isset($success)) echo "<div class='alert success'>$success</div>"; ?>

        <div class="card">
            <h3>Participants</h3>
            <?php if (empty($participants)): ?>
                <p>No participants yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Status</th>
                            <th>Hours</th>
                            <th>Task</th>
                            <th>Points</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $p): ?>
                        <tr>
                            <form method="POST" action="">
                                <input type="hidden" name="participation_id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>">
                                <td><?php echo htmlspecialchars($p['full_name']); ?></td>
                                <td>
                                    <select name="attendance_status">
                                        <option value="Registered" <?php if($p['attendance_status']=='Registered') echo 'selected'; ?>>Registered</option>
                                        <option value="Attended" <?php if($p['attendance_status']=='Attended') echo 'selected'; ?>>Attended</option>
                                        <option value="Absent" <?php if($p['attendance_status']=='Absent') echo 'selected'; ?>>Absent</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.5" name="contribution_hours" value="<?php echo $p['contribution_hours']; ?>" style="width: 60px;">
                                </td>
                                <td>
                                    <select name="task_completion_status">
                                        <option value="Pending" <?php if($p['task_completion_status']=='Pending') echo 'selected'; ?>>Pending</option>
                                        <option value="Completed" <?php if($p['task_completion_status']=='Completed') echo 'selected'; ?>>Completed</option>
                                    </select>
                                </td>
                                <td><?php echo $p['reward_points']; ?></td>
                                <td>
                                    <button type="submit" name="update_participation" class="button">Update</button>
                                </td>
                            </form>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>