<?php
require_once '../config.php';
requireRole('CLUB_LEADER');

$user_id = $_SESSION['user_id'];

// Add Role
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_role'])) {
    $membership_id = $_POST['membership_id'];
    $role = $_POST['role_name'];

    // Check if role exists
    $stmt = $pdo->prepare("SELECT id FROM club_member_roles WHERE membership_id = ? AND role_name = ?");
    $stmt->execute([$membership_id, $role]);
    if ($stmt->fetch()) {
        $error = "User already has this role.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO club_member_roles (membership_id, role_name) VALUES (?, ?)");
        $stmt->execute([$membership_id, $role]);
        $success = "Role added successfully.";
        
        // Notify user
        // Get user_id from membership
        $m_stmt = $pdo->prepare("SELECT user_id FROM club_memberships WHERE id = ?");
        $m_stmt->execute([$membership_id]);
        $m_user_id = $m_stmt->fetchColumn();
        
        $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")
            ->execute([$m_user_id, "You have been assigned a new role: $role"]);
    }
}

// Remove Member
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_member'])) {
    $membership_id = $_POST['membership_id'];

    // Verify this membership belongs to a club led by the current user
    $check_stmt = $pdo->prepare("
        SELECT cm.id 
        FROM club_memberships cm
        JOIN clubs c ON cm.club_id = c.id
        WHERE cm.id = ? AND c.leader_id = ?
    ");
    $check_stmt->execute([$membership_id, $user_id]);
    
    if ($check_stmt->fetch()) {
        // Delete membership
        $del_stmt = $pdo->prepare("DELETE FROM club_memberships WHERE id = ?");
        $del_stmt->execute([$membership_id]);
        $success = "Member removed successfully.";
    } else {
        $error = "Unauthorized action or member not found.";
    }
}

// Fetch Members of Clubs led by me
$stmt = $pdo->prepare("
    SELECT cm.id as membership_id, c.name as club_name, u.full_name, u.email, 
           GROUP_CONCAT(r.role_name SEPARATOR ', ') as roles
    FROM club_memberships cm
    JOIN clubs c ON cm.club_id = c.id
    JOIN users u ON cm.user_id = u.id
    LEFT JOIN club_member_roles r ON cm.id = r.membership_id
    WHERE c.leader_id = ?
    GROUP BY cm.id
    ORDER BY c.name, u.full_name
");
$stmt->execute([$user_id]);
$members = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Members</title>
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
        <h2>Manage Club Members</h2>
        
        <?php if (isset($success)) echo "<div class='alert success'>$success</div>"; ?>
        <?php if (isset($error)) echo "<div class='alert'>$error</div>"; ?>

        <div class="card">
            <?php if (empty($members)): ?>
                <p>No members in your clubs yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Club</th>
                            <th>Member</th>
                            <th>Current Roles</th>
                            <th>Add Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $m): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($m['club_name']); ?></td>
                            <td><?php echo htmlspecialchars($m['full_name']); ?> (<?php echo htmlspecialchars($m['email']); ?>)</td>
                            <td><?php echo htmlspecialchars($m['roles']); ?></td>
                            <td>
                                <form method="POST" action="" style="display:flex; gap:5px;">
                                    <input type="hidden" name="membership_id" value="<?php echo $m['membership_id']; ?>">
                                    <select name="role_name" required>
                                        <option value="MEMBER">Member</option>
                                        <option value="VOLUNTEER">Volunteer</option>
                                        <option value="ORGANIZER">Organizer</option>
                                        <option value="LEADER">Leader</option>
                                    </select>
                                    <button type="submit" name="add_role" class="button" style="padding: 5px 10px;">Add</button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" action="" onsubmit="return confirm('Are you sure you want to remove this member?');">
                                    <input type="hidden" name="membership_id" value="<?php echo $m['membership_id']; ?>">
                                    <button type="submit" name="remove_member" class="button" style="background-color: #dc3545; padding: 5px 10px;">Remove</button>
                                </form>
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