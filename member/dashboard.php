<?php
require_once '../config.php';
requireRole('MEMBER');

$user_id = $_SESSION['user_id'];

// Handle Join Event
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['join_event'])) {
    $event_id = $_POST['event_id'];
    
    // Check if already joined
    $stmt = $pdo->prepare("SELECT id FROM participations WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$event_id, $user_id]);
    if ($stmt->fetch()) {
        $error = "You are already registered for this event.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO participations (event_id, user_id, attendance_status) VALUES (?, ?, 'Registered')");
            $stmt->execute([$event_id, $user_id]);
            $success = "Successfully registered for event!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Handle Join Club (Apply for Viva)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['join_club'])) {
    $club_id = $_POST['club_id'];
    
    // Check if already applied or member
    $stmt = $pdo->prepare("SELECT id FROM club_memberships WHERE club_id = ? AND user_id = ?");
    $stmt->execute([$club_id, $user_id]);
    if ($stmt->fetch()) {
        $error = "You are already a member of this club.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM viva_applications WHERE club_id = ? AND user_id = ? AND status != 'Rejected'");
        $stmt->execute([$club_id, $user_id]);
        if ($stmt->fetch()) {
            $error = "You have already applied for this club.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO viva_applications (club_id, user_id, status) VALUES (?, ?, 'Pending')");
                $stmt->execute([$club_id, $user_id]);
                $success = "Application submitted! You will be scheduled for a Viva soon.";
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Handle Event Feedback
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    $event_id = $_POST['event_id'];
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];
    
    // Check if already submitted
    $stmt = $pdo->prepare("SELECT id FROM event_feedbacks WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$event_id, $user_id]);
    if ($stmt->fetch()) {
        $error = "You have already submitted feedback for this event.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO event_feedbacks (event_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->execute([$event_id, $user_id, $rating, $comment]);
            $success = "Feedback submitted successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch All Clubs
$all_clubs = $pdo->query("SELECT * FROM clubs")->fetchAll();

// Fetch My Clubs
$my_clubs_list = $pdo->prepare("SELECT c.*, GROUP_CONCAT(r.role_name SEPARATOR ', ') as my_roles 
    FROM clubs c 
    JOIN club_memberships cm ON c.id = cm.club_id 
    JOIN club_member_roles r ON cm.id = r.membership_id
    WHERE cm.user_id = ?
    GROUP BY c.id");
$my_clubs_list->execute([$user_id]);
$my_clubs = $my_clubs_list->fetchAll();

// Fetch My Viva Applications
$my_vivas = $pdo->prepare("SELECT v.*, c.name as club_name FROM viva_applications v JOIN clubs c ON v.club_id = c.id WHERE v.user_id = ?");
$my_vivas->execute([$user_id]);
$my_vivas_list = $my_vivas->fetchAll();

// Fetch My Duties (If I am a club member)
$my_duties = $pdo->prepare("SELECT d.*, c.name as club_name FROM club_duties d JOIN clubs c ON d.club_id = c.id WHERE d.assigned_to = ? ORDER BY d.due_date ASC");
$my_duties->execute([$user_id]);
$my_duties_list = $my_duties->fetchAll();

// Fetch Upcoming Events
$stmt = $pdo->prepare("SELECT e.*, c.name as club_name FROM events e JOIN clubs c ON e.club_id = c.id WHERE e.status = 'Upcoming' AND e.event_date >= NOW() ORDER BY e.event_date ASC");
$stmt->execute();
$upcoming_events = $stmt->fetchAll();

// Fetch My Participations
$stmt = $pdo->prepare("SELECT p.*, e.title, e.event_date, c.name as club_name FROM participations p JOIN events e ON p.event_id = e.id JOIN clubs c ON e.club_id = c.id WHERE p.user_id = ? ORDER BY e.event_date DESC");
$stmt->execute([$user_id]);
$my_participations = $stmt->fetchAll();

// Fetch Notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

// Calculate Total Rewards
$total_rewards = 0;
foreach ($my_participations as $p) {
    $total_rewards += $p['reward_points'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <div id="branding">
                <h1>UniClubs Member</h1>
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
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h2>
        
        <div class="card" style="background: #e8f5e9;">
            <h3>Total Reward Points: <?php echo $total_rewards; ?> 🏆</h3>
        </div>

        <?php if (isset($success)) echo "<div class='alert success'>$success</div>"; ?>
        <?php if (isset($error)) echo "<div class='alert'>$error</div>"; ?>

        <div class="card">
            <h3>Notifications</h3>
            <?php if (empty($notifications)): ?>
                <p>No notifications.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($notifications as $n): ?>
                        <li style="<?php echo $n['is_read'] ? 'color:gray;' : 'font-weight:bold;'; ?>">
                            <?php echo htmlspecialchars($n['message']); ?> 
                            <small>(<?php echo $n['created_at']; ?>)</small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>My Viva Applications</h3>
            <?php if (empty($my_vivas_list)): ?>
                <p>No active applications.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($my_vivas_list as $v): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($v['club_name']); ?></strong>: 
                            <?php echo $v['status']; ?>
                            <?php if ($v['scheduled_at']) echo " - Scheduled for: " . $v['scheduled_at']; ?>
                            <?php if ($v['status'] == 'Passed') echo " (Welcome to the club!)"; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>My Duties</h3>
            <?php if (empty($my_duties_list)): ?>
                <p>No assigned duties.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Club</th>
                            <th>Task</th>
                            <th>Description</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_duties_list as $d): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d['club_name']); ?></td>
                            <td><?php echo htmlspecialchars($d['title']); ?></td>
                            <td><?php echo htmlspecialchars($d['description']); ?></td>
                            <td><?php echo $d['due_date']; ?></td>
                            <td><?php echo $d['status']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>My Clubs</h3>
            <?php if (empty($my_clubs)): ?>
                <p>You have not joined any clubs yet.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($my_clubs as $c): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($c['name']); ?></strong> 
                            - Roles: <?php echo htmlspecialchars($c['my_roles']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Join a Club</h3>
            <form method="POST" action="" style="display:inline;">
                <select name="club_id" required style="width: auto; padding: 5px;">
                    <option value="">-- Select Club to Apply --</option>
                    <?php foreach ($all_clubs as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="join_club" class="button">Apply for Viva</button>
            </form>
        </div>

        <div class="card">
            <h3>Upcoming Events</h3>
            <?php if (empty($upcoming_events)): ?>
                <p>No upcoming events found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Club</th>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming_events as $e): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($e['club_name']); ?></td>
                            <td><?php echo htmlspecialchars($e['title']); ?></td>
                            <td><?php echo $e['event_date']; ?></td>
                            <td><?php echo $e['category']; ?></td>
                            <td>
                                <form method="POST" action="">
                                    <input type="hidden" name="event_id" value="<?php echo $e['id']; ?>">
                                    <button type="submit" name="join_event" class="button">Join</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>My Participation History</h3>
            <?php if (empty($my_participations)): ?>
                <p>You haven't participated in any events yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Hours</th>
                            <th>Task</th>
                            <th>Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_participations as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['title']); ?></td>
                            <td><?php echo $p['event_date']; ?></td>
                            <td><?php echo $p['attendance_status']; ?></td>
                            <td><?php echo $p['contribution_hours']; ?></td>
                            <td><?php echo $p['task_completion_status']; ?></td>
                            <td><?php echo $p['reward_points']; ?></td>
                            <td>
                                <?php if($p['attendance_status'] == 'Attended'): ?>
                                <form method="POST" action="">
                                    <input type="hidden" name="event_id" value="<?php echo $p['event_id']; ?>">
                                    <input type="number" name="rating" min="1" max="5" placeholder="Rate 1-5" required style="width: 80px;">
                                    <input type="text" name="comment" placeholder="Feedback" required>
                                    <button type="submit" name="submit_feedback" class="button" style="padding: 2px 5px; font-size: 12px;">Submit</button>
                                </form>
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