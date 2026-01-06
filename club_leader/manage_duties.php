<?php
require_once '../config.php';
requireRole('CLUB_LEADER');

$user_id = $_SESSION['user_id'];

// Handle Assign Duty
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_duty'])) {
    $club_id = $_POST['club_id'];
    $assigned_to = $_POST['assigned_to'];
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $due = $_POST['due_date'];
    $points = $_POST['reward_points'];
    
    $stmt = $pdo->prepare("INSERT INTO club_duties (club_id, assigned_to, title, description, due_date, reward_points) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$club_id, $assigned_to, $title, $desc, $due, $points]);
    
    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")
        ->execute([$assigned_to, "New Duty Assigned: $title"]);
    
    $success = "Duty assigned successfully.";
}

// Fetch Members
$stmt = $pdo->prepare("
    SELECT cm.user_id, u.full_name, c.id as club_id, c.name as club_name
    FROM club_memberships cm
    JOIN users u ON cm.user_id = u.id
    JOIN clubs c ON cm.club_id = c.id
    WHERE c.leader_id = ?
");
$stmt->execute([$user_id]);
$members = $stmt->fetchAll();

// Group members by club for easier selection logic if needed, but flat list with club name works for simple UI.

// Fetch Existing Duties
$stmt = $pdo->prepare("
    SELECT d.*, u.full_name, c.name as club_name 
    FROM club_duties d 
    JOIN users u ON d.assigned_to = u.id 
    JOIN clubs c ON d.club_id = c.id
    WHERE c.leader_id = ?
    ORDER BY d.created_at DESC
");
$stmt->execute([$user_id]);
$duties = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Duties</title>
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
        <h2>Assign Club Duties</h2>
        
        <?php if (isset($success)) echo "<div class='alert success'>$success</div>"; ?>

        <div class="card">
            <h3>Assign New Duty</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Select Member</label>
                    <select name="assigned_to" required id="memberSelect">
                        <option value="">-- Select Member --</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?php echo $m['user_id']; ?>" data-club="<?php echo $m['club_id']; ?>">
                                <?php echo htmlspecialchars($m['full_name']); ?> (<?php echo htmlspecialchars($m['club_name']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <!-- Hidden input to store club_id, populated by JS or just simple loop hack -->
                    <!-- Simpler: Just make the value a composite or use JS. Let's use a workaround: Loop again to find club_id in PHP backend? No, let's put club_id in value as "club_id|user_id" or handle in backend. -->
                    <!-- I will add a hidden input for club_id and use JS to update it, or just use a combined value. -->
                </div>
                <!-- Workaround for simplicity without JS: Ask user to select Club first? No. 
                     Let's use a simple JS snippet. -->
                <input type="hidden" name="club_id" id="clubIdField">
                
                <div class="form-group">
                    <label>Task Title</label>
                    <input type="text" name="title" required placeholder="e.g. Plan Venue">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" required></textarea>
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="datetime-local" name="due_date" required>
                </div>
                <div class="form-group">
                    <label>Reward Points</label>
                    <input type="number" name="reward_points" value="10">
                </div>
                <button type="submit" name="assign_duty" class="button">Assign</button>
            </form>
            
            <script>
                document.getElementById('memberSelect').addEventListener('change', function() {
                    var selected = this.options[this.selectedIndex];
                    document.getElementById('clubIdField').value = selected.getAttribute('data-club');
                });
            </script>
        </div>

        <div class="card">
            <h3>Assigned Duties</h3>
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Task</th>
                        <th>Due</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($duties as $d): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($d['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($d['title']); ?></td>
                        <td><?php echo $d['due_date']; ?></td>
                        <td><?php echo $d['status']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>