<?php
require_once '../config.php';
requireRole('ADMIN');

// Handle Club Creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_club'])) {
    $name = $_POST['club_name'];
    $desc = $_POST['description'];
    $leader_id = $_POST['leader_id']; // Can be empty

    try {
        $stmt = $pdo->prepare("INSERT INTO clubs (name, description, leader_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $desc, $leader_id ?: null]);
        $success = "Club created successfully!";
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Users for Leader Selection
$users = $pdo->query("SELECT * FROM users")->fetchAll();

// Fetch Users with Phones
$users_list = $pdo->query("
    SELECT u.*, GROUP_CONCAT(up.phone_number SEPARATOR ', ') as phones 
    FROM users u 
    LEFT JOIN user_phones up ON u.id = up.user_id 
    GROUP BY u.id
")->fetchAll();

// Fetch Clubs
$clubs = $pdo->query("SELECT c.*, u.full_name as leader_name FROM clubs c LEFT JOIN users u ON c.leader_id = u.id")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <div id="branding">
                <h1>UniClubs Admin</h1>
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
        <h2>Admin Dashboard</h2>
        
        <?php if (isset($success)) echo "<div class='alert success'>$success</div>"; ?>
        <?php if (isset($error)) echo "<div class='alert'>$error</div>"; ?>

        <div class="card">
            <h3>Create New Club</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Club Name</label>
                    <input type="text" name="club_name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" required></textarea>
                </div>
                <div class="form-group">
                    <label>Assign Leader (Optional)</label>
                    <select name="leader_id">
                        <option value="">-- Select Leader --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?> (<?php echo $u['role']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="create_club" class="button">Create Club</button>
            </form>
        </div>

        <div class="card">
            <h3>Existing Clubs</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Leader</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clubs as $c): ?>
                    <tr>
                        <td><?php echo $c['id']; ?></td>
                        <td><?php echo htmlspecialchars($c['name']); ?></td>
                        <td><?php echo htmlspecialchars($c['leader_name'] ?? 'None'); ?></td>
                        <td><?php echo $c['created_at']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3>System Users</h3>
             <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phones</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users_list as $u): ?>
                    <tr>
                        <td><?php echo $u['id']; ?></td>
                        <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><?php echo htmlspecialchars($u['phones'] ?? 'N/A'); ?></td>
                        <td><?php echo $u['role']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>