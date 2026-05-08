<?php
require_once __DIR__ . '/../includes/header.php';
global $pdo;

$shops = $pdo->query("
    SELECT s.*, 
    (SELECT COUNT(*) FROM loans WHERE shop_id = s.id AND status='active') as active_loans
    FROM shops s 
    ORDER BY name ASC
")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Manage Shops</h1>
        <p class="page-subtitle">View and manage all your retail locations.</p>
    </div>
    <div class="flex gap-3">
        <div class="search-bar">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="search-input" data-table="shopsTable" placeholder="Search shops...">
        </div>
        <a href="add.php" class="btn btn-primary">Add Shop</a>
    </div>
</div>

<div class="card">
    <div class="table-wrapper">
        <table id="shopsTable">
            <thead>
                <tr>
                    <th>Shop Name</th>
                    <th>Owner</th>
                    <th>City</th>
                    <th>Active Loans</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($shops as $shop): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($shop['name']) ?></strong></td>
                    <td><?= htmlspecialchars($shop['owner_name']) ?></td>
                    <td><?= htmlspecialchars($shop['city']) ?></td>
                    <td><span class="badge badge-completed"><?= $shop['active_loans'] ?></span></td>
                    <td>
                        <?php if($shop['is_active']): ?>
                            <span class="badge badge-received">Active</span>
                        <?php else: ?>
                            <span class="badge badge-overdue">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-right">
                        <a href="edit.php?id=<?= $shop['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                        <a href="dashboard.php?id=<?= $shop['id'] ?>" class="btn btn-sm btn-primary">Dashboard</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
