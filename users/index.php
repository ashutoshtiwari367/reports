<?php
require_once __DIR__ . '/../includes/header.php';

if (!isSuperAdmin()) {
    setFlash('error', 'Access Denied.');
    header('Location: /emi/dashboard.php');
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

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">System Users</h1>
        <p class="text-sm text-gray-500">Manage Shop Admins and Staff</p>
    </div>
    <a href="/emi/users/add.php" class="btn btn-primary">
        + Create User
    </a>
</div>

<div class="card">
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider border-b border-gray-100">
                    <th class="p-4 font-semibold">User</th>
                    <th class="p-4 font-semibold">Email</th>
                    <th class="p-4 font-semibold">Role</th>
                    <th class="p-4 font-semibold">Assigned Shop</th>
                    <th class="p-4 font-semibold">Created</th>
                    <th class="p-4 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                <?php foreach ($users as $u): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="p-4 font-medium text-gray-800"><?= htmlspecialchars($u['name']) ?></td>
                        <td class="p-4 text-gray-600"><?= htmlspecialchars($u['email']) ?></td>
                        <td class="p-4">
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge badge-received">Super Admin</span>
                            <?php else: ?>
                                <span class="badge badge-partial">Shop Admin</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-gray-600">
                            <?= $u['shop_id'] ? htmlspecialchars($u['shop_name']) : '<span class="text-xs text-gray-400">All Shops (Super Admin)</span>' ?>
                        </td>
                        <td class="p-4 text-gray-500"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td class="p-4 text-right">
                            <div class="flex gap-2 justify-end">
                                <a href="/emi/users/edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline" title="Edit">Edit</a>
                                <a href="/emi/users/change_password.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline" title="Change Password">Pass</a>
                                <?php if ($u['id'] != $user['id']): ?>
                                    <a href="/emi/users/delete.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-ghost text-danger" onclick="return confirm('Are you sure you want to delete this user?')" title="Delete">Delete</a>
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
