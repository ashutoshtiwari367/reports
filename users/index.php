<?php
require_once __DIR__ . '/../includes/header.php';

if (!isSuperAdmin()) {
    setFlash('error', 'Access Denied.');
    header('Location: /dashboard.php');
    exit;
}

$stmt = $pdo->query("
    SELECT u.*, s.name as shop_name 
    FROM users u 
    LEFT JOIN shops s ON u.shop_id = s.id 
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">System Users</h1>
        <p class="page-subtitle">Manage Shop Admins and Staff</p>
    </div>
    <a href="/users/add.php" class="btn btn-primary">
        + Create User
    </a>
</div>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Assigned Shop</th>
                    <th>Created</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="text-bold"><?= htmlspecialchars($u['name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge badge-received">Super Admin</span>
                            <?php else: ?>
                                <span class="badge badge-partial">Shop Admin</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $u['shop_id'] ? htmlspecialchars($u['shop_name']) : '<span class="text-muted" style="font-size:11px;">All Shops</span>' ?>
                        </td>
                        <td class="text-muted"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td class="text-right">
                            <div class="flex gap-2" style="justify-content: flex-end;">
                                <a href="/users/edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                                <a href="/users/change_password.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline">Pass</a>
                                <?php if ($u['id'] != $user['id']): ?>
                                    <a href="/users/delete.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-ghost text-danger" onclick="return confirm('Are you sure?')">Del</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                    <tr><td colspan="6" class="p-8 text-center text-gray-400">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

