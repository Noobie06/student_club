<?php
require_once '../config.php';
requireRole('CLUB_LEADER');

$user_id = $_SESSION['user_id'];

// Create Event
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_event'])) {
    $club_id = $_POST['club_id'];
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $date = $_POST['event_date'];
    $location = $_POST['location'];
    $category = $_POST['category'];
    $max = $_POST['max_participants'];

    try {
        $stmt = $pdo->prepare("INSERT INTO events (club_id, title, description, event_date, location, category, max_participants) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$club_id, $title, $desc, $date, $location, $category, $max]);
        $success = "Event created successfully!";
        
        // Notify all club members (Optional implementation)
        // For now, we skip auto-notification to keep it simple, or add a simple one.
        // Let's add a notification to the leader for confirmation.
        $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$user_id, "You created event: $title"]);

    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch My Clubs
$my_clubs = $pdo->prepare("SELECT * FROM clubs WHERE leader_id = ?");
$my_clubs->execute([$user_id]);
$clubs = $my_clubs->fetchAll();

// Fetch Events for My Clubs
$club_ids = array_column($clubs, 'id');
$events = [];
if (!empty($club_ids)) {
    $in  = str_repeat('?,', count($club_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT e.*, c.name as club_name FROM events e JOIN clubs c ON e.club_id = c.id WHERE e.club_id IN ($in) ORDER BY e.event_date DESC");
    $stmt->execute($club_ids);
    $events = $stmt->fetchAll();
}

// Check for inactive members (No reward points updated for 4 months)
$inactive_members = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.full_name, 
            u.email, 
            c.name as club_name,
            MAX(p.created_at) as last_reward_date,
            cm.joined_at
        FROM club_memberships cm
        JOIN clubs c ON cm.club_id = c.id
        JOIN users u ON cm.user_id = u.id
        LEFT JOIN events e ON e.club_id = c.id
        LEFT JOIN participations p ON p.event_id = e.id AND p.user_id = u.id AND p.reward_points > 0
        WHERE c.leader_id = ?
        GROUP BY cm.id
        HAVING 
            (last_reward_date IS NOT NULL AND last_reward_date < DATE_SUB(NOW(), INTERVAL 4 MONTH))
            OR 
            (last_reward_date IS NULL AND cm.joined_at < DATE_SUB(NOW(), INTERVAL 4 MONTH))
    ");
    $stmt->execute([$user_id]);
    $inactive_members = $stmt->fetchAll();
} catch (PDOException $e) {
    // Silent fail or log error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leader Dashboard</title>
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
        <h2>Club Leader Dashboard</h2>
        
        <?php if (!empty($inactive_members)): ?>
            <div class="alert" style="background-color: #fff3cd; color: #856404; border-color: #ffeeba;">
                <strong>Warning:</strong> The following members have not received reward points in over 4 months:
                <ul>
                    <?php foreach ($inactive_members as $im): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($im['full_name']); ?></strong> (<?php echo htmlspecialchars($im['club_name']); ?>) 
                            - Last Activity: <?php echo $im['last_reward_date'] ? $im['last_reward_date'] : 'Never (Joined: ' . $im['joined_at'] . ')'; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div style="margin-bottom: 20px;">
            <a href="manage_members.php" class="button">Manage Club Members</a>
            <a href="manage_viva.php" class="button">Manage Viva Applications</a>
            <a href="manage_duties.php" class="button">Assign Duties</a>
        </div>

        <?php if (isset($success)) echo "<div class='alert success'>$success</div>"; ?>
        <?php if (isset($error)) echo "<div class='alert'>$error</div>"; ?>

        <?php if (empty($clubs)): ?>
            <div class="alert">You are not leading any clubs yet.</div>
        <?php else: ?>

        <div class="card">
            <h3>Create New Event</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Select Club</label>
                    <select name="club_id" required>
                        <?php foreach ($clubs as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Event Title</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" required></textarea>
                </div>
                <div class="form-group">
                    <label>Date & Time</label>
                    <input type="datetime-local" name="event_date" required>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" required>
                        <option value="Tech">Tech</option>
                        <option value="Cultural">Cultural</option>
                        <option value="Sports">Sports</option>
                        <option value="Workshop">Workshop</option>
                        <option value="Seminar">Seminar</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Max Participants</label>
                    <input type="number" name="max_participants" value="50" required>
                </div>
                <button type="submit" name="create_event" class="button">Create Event</button>
            </form>
        </div>

        <div class="card">
            <h3>My Club Events</h3>
            <table>
                <thead>
                    <tr>
                        <th>Club</th>
                        <th>Title</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $e): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($e['club_name']); ?></td>
                        <td><?php echo htmlspecialchars($e['title']); ?></td>
                        <td><?php echo $e['event_date']; ?></td>
                        <td><?php echo $e['status']; ?></td>
                        <td>
                            <a href="manage_event.php?id=<?php echo $e['id']; ?>" class="button">Manage</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php endif; ?>
    </div>
</body>
</html>