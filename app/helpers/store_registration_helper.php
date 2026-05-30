<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';

function store_registration_submit(PDO $pdo, array $data): int
{
    $storeName = trim((string) ($data['store_name'] ?? ''));
    $adminUsername = trim((string) ($data['admin_username'] ?? ''));
    $referralCodeInput = strtoupper(trim((string) ($data['referral_code'] ?? '')));

    if ($storeName === '') {
        throw new RuntimeException('Nama toko wajib diisi.');
    }
    if ($adminUsername === '') {
        throw new RuntimeException('Username admin toko wajib diisi.');
    }
    if ($referralCodeInput === '') {
        throw new RuntimeException('Kode referral wajib diisi.');
    }
    if (store_registration_username_exists($pdo, $adminUsername)) {
        throw new RuntimeException('Username admin toko sudah digunakan.');
    }

    $storeCode = store_registration_generate_code($storeName);
    while (store_registration_code_exists($pdo, $storeCode)) {
        $storeCode = store_registration_generate_code($storeName);
    }

    $pdo->beginTransaction();

    try {
        $referral = store_referral_find_by_code($pdo, $referralCodeInput, true);
        if (!$referral) {
            throw new RuntimeException('Kode referral tidak ditemukan.');
        }

        store_referral_assert_available($referral);

        $stmt = $pdo->prepare(
            'INSERT INTO stores (
                store_code, store_name, store_tagline, store_address, store_phone, store_email, receipt_footer,
                owner_name, owner_email, owner_phone,
                admin_name, admin_username, admin_password_hash,
                referral_code_id, referral_code, status
             ) VALUES (
                :store_code, :store_name, :store_tagline, :store_address, :store_phone, :store_email, :receipt_footer,
                :owner_name, :owner_email, :owner_phone,
                :admin_name, :admin_username, :admin_password_hash,
                :referral_code_id, :referral_code, :status
             )'
        );

        $stmt->execute([
            ':store_code' => $storeCode,
            ':store_name' => $storeName,
            ':store_tagline' => trim((string) ($data['store_tagline'] ?? '')),
            ':store_address' => trim((string) ($data['store_address'] ?? '')),
            ':store_phone' => trim((string) ($data['store_phone'] ?? '')),
            ':store_email' => trim((string) ($data['store_email'] ?? '')),
            ':receipt_footer' => trim((string) ($data['receipt_footer'] ?? 'Terima kasih!')),
            ':owner_name' => trim((string) ($data['owner_name'] ?? '')),
            ':owner_email' => trim((string) ($data['owner_email'] ?? '')),
            ':owner_phone' => trim((string) ($data['owner_phone'] ?? '')),
            ':admin_name' => trim((string) ($data['admin_name'] ?? '')),
            ':admin_username' => $adminUsername,
            ':admin_password_hash' => (string) ($data['admin_password_hash'] ?? ''),
            ':referral_code_id' => (int) ($referral['id'] ?? 0),
            ':referral_code' => $referralCodeInput,
            ':status' => 'pending',
        ]);

        $storeId = (int) $pdo->lastInsertId();
        store_referral_redeem($pdo, (int) ($referral['id'] ?? 0));

        $pdo->commit();

        return $storeId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function store_registration_all(PDO $pdo, string $status = 'all', string $search = ''): array
{
    $sql = 'SELECT stores.*,
                   approver.name AS approved_by_name,
                   referral.label AS referral_label,
                   suspender.name AS suspended_by_name
            FROM stores
            LEFT JOIN users AS approver ON approver.id = stores.approved_by
            LEFT JOIN store_referral_codes AS referral ON referral.id = stores.referral_code_id
            LEFT JOIN users AS suspender ON suspender.id = stores.suspended_by';
    $conditions = [];
    $params = [];

    if ($status !== 'all') {
        $conditions[] = 'stores.status = :status';
        $params[':status'] = $status;
    }

    $search = trim($search);
    if ($search !== '') {
        $conditions[] = '(stores.store_name LIKE :search
            OR stores.store_code LIKE :search_code
            OR stores.owner_name LIKE :search_owner
            OR stores.admin_username LIKE :search_admin
            OR stores.store_phone LIKE :search_phone
            OR stores.store_email LIKE :search_email
            OR stores.referral_code LIKE :search_referral)';
        $searchValue = '%' . $search . '%';
        $params[':search'] = $searchValue;
        $params[':search_code'] = $searchValue;
        $params[':search_owner'] = $searchValue;
        $params[':search_admin'] = $searchValue;
        $params[':search_phone'] = $searchValue;
        $params[':search_email'] = $searchValue;
        $params[':search_referral'] = $searchValue;
    }

    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY FIELD(stores.status, \'pending\', \'approved\', \'rejected\'), stores.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function store_registration_recent(PDO $pdo, string $status = 'all', int $limit = 5): array
{
    $limit = max(1, min($limit, 50));
    $sql = 'SELECT stores.*,
                   approver.name AS approved_by_name,
                   referral.label AS referral_label,
                   suspender.name AS suspended_by_name
            FROM stores
            LEFT JOIN users AS approver ON approver.id = stores.approved_by
            LEFT JOIN store_referral_codes AS referral ON referral.id = stores.referral_code_id
            LEFT JOIN users AS suspender ON suspender.id = stores.suspended_by';
    $params = [];

    if ($status !== 'all') {
        $sql .= ' WHERE stores.status = :status';
        $params[':status'] = $status;
    }

    $sql .= ' ORDER BY stores.created_at DESC LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function store_registration_counts(PDO $pdo): array
{
    $counts = [
        'all' => 0,
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
    ];

    $stmt = $pdo->query('SELECT status, COUNT(*) AS total FROM stores GROUP BY status');
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $status = (string) ($row['status'] ?? '');
        if ($status !== '' && array_key_exists($status, $counts)) {
            $counts[$status] = (int) ($row['total'] ?? 0);
            $counts['all'] += (int) ($row['total'] ?? 0);
        }
    }

    return $counts;
}

function store_registration_dashboard_stats(PDO $pdo): array
{
    $today = date('Y-m-d');
    $weekStart = date('Y-m-d', strtotime('-6 days'));
    $overdueThreshold = date('Y-m-d H:i:s', strtotime('-2 days'));

    $stats = [
        'pending_today' => 0,
        'approved_this_week' => 0,
        'pending_overdue' => 0,
        'latest_submitted_at' => null,
        'latest_submitted_store' => null,
        'latest_approved_at' => null,
        'latest_approved_store' => null,
        'oldest_pending_at' => null,
        'oldest_pending_store' => null,
        'active_stores' => 0,
        'suspended_stores' => 0,
        'stores_using_referral' => 0,
    ];

    $pendingTodayStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM stores
         WHERE status = 'pending'
           AND DATE(created_at) = :today"
    );
    $pendingTodayStmt->execute([':today' => $today]);
    $stats['pending_today'] = (int) $pendingTodayStmt->fetchColumn();

    $approvedWeekStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM stores
         WHERE status = 'approved'
           AND DATE(approved_at) BETWEEN :start_date AND :end_date"
    );
    $approvedWeekStmt->execute([
        ':start_date' => $weekStart,
        ':end_date' => $today,
    ]);
    $stats['approved_this_week'] = (int) $approvedWeekStmt->fetchColumn();

    $pendingOverdueStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM stores
         WHERE status = 'pending'
           AND created_at < :threshold"
    );
    $pendingOverdueStmt->execute([':threshold' => $overdueThreshold]);
    $stats['pending_overdue'] = (int) $pendingOverdueStmt->fetchColumn();

    $stats['active_stores'] = (int) $pdo->query("SELECT COUNT(*) FROM stores WHERE status = 'approved' AND COALESCE(operational_status, 'active') = 'active'")->fetchColumn();
    $stats['suspended_stores'] = (int) $pdo->query("SELECT COUNT(*) FROM stores WHERE status = 'approved' AND COALESCE(operational_status, 'active') = 'suspended'")->fetchColumn();
    $stats['stores_using_referral'] = (int) $pdo->query('SELECT COUNT(*) FROM stores WHERE referral_code_id IS NOT NULL')->fetchColumn();

    $latestSubmittedStmt = $pdo->query(
        'SELECT store_name, created_at
         FROM stores
         ORDER BY created_at DESC
         LIMIT 1'
    );
    $latestSubmitted = $latestSubmittedStmt->fetch();
    if ($latestSubmitted) {
        $stats['latest_submitted_at'] = $latestSubmitted['created_at'] ?? null;
        $stats['latest_submitted_store'] = $latestSubmitted['store_name'] ?? null;
    }

    $latestApprovedStmt = $pdo->query(
        "SELECT store_name, approved_at
         FROM stores
         WHERE approved_at IS NOT NULL
         ORDER BY approved_at DESC
         LIMIT 1"
    );
    $latestApproved = $latestApprovedStmt->fetch();
    if ($latestApproved) {
        $stats['latest_approved_at'] = $latestApproved['approved_at'] ?? null;
        $stats['latest_approved_store'] = $latestApproved['store_name'] ?? null;
    }

    $oldestPendingStmt = $pdo->query(
        "SELECT store_name, created_at
         FROM stores
         WHERE status = 'pending'
         ORDER BY created_at ASC
         LIMIT 1"
    );
    $oldestPending = $oldestPendingStmt->fetch();
    if ($oldestPending) {
        $stats['oldest_pending_at'] = $oldestPending['created_at'] ?? null;
        $stats['oldest_pending_store'] = $oldestPending['store_name'] ?? null;
    }

    return $stats;
}

function store_registration_find(PDO $pdo, int $storeId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT stores.*,
                referral.label AS referral_label,
                suspender.name AS suspended_by_name
         FROM stores
         LEFT JOIN store_referral_codes AS referral ON referral.id = stores.referral_code_id
         LEFT JOIN users AS suspender ON suspender.id = stores.suspended_by
         WHERE stores.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $storeId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function store_registration_operational_status_values(): array
{
    return ['active', 'suspended'];
}

function store_registration_operational_status(array $store): string
{
    $status = strtolower(trim((string) ($store['operational_status'] ?? 'active')));
    return in_array($status, store_registration_operational_status_values(), true) ? $status : 'active';
}

function store_registration_operational_status_label(string $status): string
{
    return $status === 'suspended' ? 'Suspended' : 'Active';
}

function store_registration_access_issue(PDO $pdo, array $user): ?array
{
    $role = (string) ($user['role'] ?? '');
    if ($role === 'superadmin') {
        return null;
    }

    $storeId = (int) ($user['store_id'] ?? 0);
    if ($storeId <= 0) {
        return null;
    }

    $store = store_registration_find($pdo, $storeId);
    if (!$store) {
        return [
            'code' => 'store_not_found',
            'message' => 'Toko untuk akun ini tidak ditemukan.',
        ];
    }

    if ((string) ($store['status'] ?? '') !== 'approved') {
        return [
            'code' => 'store_not_ready',
            'message' => 'Toko ini belum aktif digunakan.',
        ];
    }

    if (store_registration_operational_status($store) === 'suspended') {
        $message = 'Akses toko sedang ditangguhkan oleh super admin.';
        $note = trim((string) ($store['operational_note'] ?? ''));
        if ($note !== '') {
            $message .= ' Catatan: ' . $note;
        }

        return [
            'code' => 'store_suspended',
            'message' => $message,
        ];
    }

    return null;
}

function store_registration_touch_login(PDO $pdo, int $storeId): void
{
    if ($storeId <= 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE stores
         SET last_login_at = NOW()
         WHERE id = :id'
    );
    $stmt->execute([':id' => $storeId]);
}

function store_registration_set_operational_status(PDO $pdo, int $storeId, string $status, int $actorUserId, string $note = ''): array
{
    $store = store_registration_find($pdo, $storeId);
    if (!$store) {
        throw new RuntimeException('Toko tidak ditemukan.');
    }

    if ((string) ($store['status'] ?? '') !== 'approved') {
        throw new RuntimeException('Hanya toko yang sudah approved yang bisa diatur operasionalnya.');
    }

    $normalizedStatus = strtolower(trim($status));
    if (!in_array($normalizedStatus, store_registration_operational_status_values(), true)) {
        throw new RuntimeException('Status operasional toko tidak valid.');
    }

    $note = trim($note);

    $stmt = $pdo->prepare(
        'UPDATE stores
         SET operational_status = :operational_status,
             operational_note = :operational_note,
             suspended_at = :suspended_at,
             suspended_by = :suspended_by
         WHERE id = :id'
    );
    $stmt->execute([
        ':operational_status' => $normalizedStatus,
        ':operational_note' => $note !== '' ? $note : null,
        ':suspended_at' => $normalizedStatus === 'suspended' ? date('Y-m-d H:i:s') : null,
        ':suspended_by' => $normalizedStatus === 'suspended' ? $actorUserId : null,
        ':id' => $storeId,
    ]);

    $updated = store_registration_find($pdo, $storeId);
    if (!$updated) {
        throw new RuntimeException('Toko gagal dimuat ulang setelah update.');
    }

    return $updated;
}

function store_registration_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table'
    );
    $stmt->execute([':table' => $table]);

    return (int) $stmt->fetchColumn() > 0;
}

function store_registration_support_map(PDO $pdo): array
{
    if (!store_registration_support_enabled()) {
        return [];
    }

    if (!store_registration_table_exists($pdo, 'support_threads') || !store_registration_table_exists($pdo, 'support_messages')) {
        return [];
    }

    $stmt = $pdo->query(
        "SELECT support_threads.store_id,
                support_threads.id AS thread_id,
                support_threads.status AS thread_status,
                support_threads.chat_mode,
                support_threads.priority,
                support_threads.last_message_at,
                support_threads.last_message_preview,
                (
                    SELECT COUNT(*)
                    FROM support_messages
                    WHERE support_messages.thread_id = support_threads.id
                      AND support_messages.id > support_threads.support_last_read_message_id
                      AND support_messages.sender_role = 'admin'
                ) AS unread_count
         FROM support_threads"
    );

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int) ($row['store_id'] ?? 0)] = $row;
    }

    return $map;
}

function store_registration_support_enabled(): bool
{
    return function_exists('app_env_bool') ? app_env_bool('APP_SUPPORT_ENABLED', false) : false;
}

function store_registration_health_badge(array $store): array
{
    $operationalStatus = store_registration_operational_status($store);
    $supportEnabled = store_registration_support_enabled();
    $supportPriority = strtolower(trim((string) ($store['support_priority'] ?? 'normal')));
    $supportStatus = strtolower(trim((string) ($store['support_status'] ?? 'closed')));
    $supportUnread = (int) ($store['support_unread_count'] ?? 0);
    $lastLoginAt = trim((string) ($store['last_login_at'] ?? ''));

    $badge = [
        'health_level' => 'healthy',
        'health_label' => 'Sehat',
        'health_note' => 'Toko aktif dan tidak ada sinyal masalah penting.',
        'health_rank' => 3,
    ];

    if ($operationalStatus === 'suspended') {
        $badge['health_level'] = 'suspended';
        $badge['health_label'] = 'Suspended';
        $badge['health_note'] = trim((string) ($store['operational_note'] ?? '')) !== ''
            ? trim((string) ($store['operational_note'] ?? ''))
            : 'Akses toko sedang ditangguhkan oleh super admin.';
        $badge['health_rank'] = 0;
        return $badge;
    }

    if ($supportEnabled && $supportStatus === 'open' && $supportPriority === 'urgent') {
        $badge['health_level'] = 'critical';
        $badge['health_label'] = 'Urgent';
        $badge['health_note'] = 'Ada thread support urgent yang perlu ditangani.';
        $badge['health_rank'] = 1;
        return $badge;
    }

    if ($lastLoginAt === '') {
        $badge['health_level'] = 'attention';
        $badge['health_label'] = 'Belum Login';
        $badge['health_note'] = 'Toko belum pernah login sejak approval.';
        $badge['health_rank'] = 2;
        return $badge;
    }

    try {
        $diff = (new DateTimeImmutable($lastLoginAt))->diff(new DateTimeImmutable('now'));
        $daysSinceLogin = (int) $diff->format('%a');
        if ($daysSinceLogin >= 7) {
            $badge['health_level'] = 'attention';
            $badge['health_label'] = 'Pasif';
            $badge['health_note'] = 'Tidak ada login toko dalam ' . $daysSinceLogin . ' hari terakhir.';
            $badge['health_rank'] = 2;
            return $badge;
        }
    } catch (Throwable $e) {
        // fall through to support state below
    }

    if ($supportEnabled && $supportStatus === 'open' && $supportUnread > 0) {
        $badge['health_level'] = 'attention';
        $badge['health_label'] = 'Perlu Follow Up';
        $badge['health_note'] = 'Ada ' . $supportUnread . ' pesan support yang belum dibalas super admin.';
        $badge['health_rank'] = 2;
    }

    return $badge;
}

function store_registration_directory(PDO $pdo, string $search = ''): array
{
    $stores = store_registration_all($pdo, 'approved', $search);
    $supportEnabled = store_registration_support_enabled();
    $supportMap = store_registration_support_map($pdo);

    foreach ($stores as &$store) {
        $storeId = (int) ($store['id'] ?? 0);
        $support = $supportMap[$storeId] ?? null;
        $operationalStatus = store_registration_operational_status($store);

        $store['operational_status'] = $operationalStatus;
        $store['operational_status_label'] = store_registration_operational_status_label($operationalStatus);
        $store['support_status'] = strtolower(trim((string) ($support['thread_status'] ?? 'closed')));
        $store['support_priority'] = strtolower(trim((string) ($support['priority'] ?? 'normal')));
        $store['support_chat_mode'] = strtolower(trim((string) ($support['chat_mode'] ?? 'bot')));
        $store['support_last_message_at'] = $support['last_message_at'] ?? null;
        $store['support_last_message_preview'] = $support['last_message_preview'] ?? '';
        $store['support_unread_count'] = (int) ($support['unread_count'] ?? 0);
        $store['support_thread_id'] = (int) ($support['thread_id'] ?? 0);
        $store['support_state_label'] = !$supportEnabled
            ? 'NONAKTIF'
            : ($store['support_thread_id'] > 0
            ? strtoupper((string) ($store['support_chat_mode'] === 'live' ? 'LIVE' : 'BOT')) . ' / ' . strtoupper((string) ($store['support_status'] === 'open' ? 'OPEN' : 'CLOSED'))
            : 'BELUM ADA ROOM');
        $store['support_note'] = !$supportEnabled
            ? 'Fitur support chat dinonaktifkan.'
            : ($store['support_thread_id'] > 0
            ? ((int) $store['support_unread_count'] > 0
                ? 'Unread support: ' . (int) $store['support_unread_count']
                : 'Tidak ada unread support.')
            : 'Belum ada aktivitas support.');

        $health = store_registration_health_badge($store);
        $store = array_merge($store, $health);
    }
    unset($store);

    usort($stores, static function (array $a, array $b): int {
        $rankA = (int) ($a['health_rank'] ?? 99);
        $rankB = (int) ($b['health_rank'] ?? 99);
        if ($rankA !== $rankB) {
            return $rankA <=> $rankB;
        }

        $timeA = strtotime((string) ($a['last_login_at'] ?? '')) ?: 0;
        $timeB = strtotime((string) ($b['last_login_at'] ?? '')) ?: 0;
        return $timeB <=> $timeA;
    });

    return $stores;
}

function store_registration_health_summary(array $stores): array
{
    $summary = [
        'total' => count($stores),
        'active' => 0,
        'suspended' => 0,
        'healthy' => 0,
        'needs_attention' => 0,
        'critical' => 0,
        'offline' => 0,
        'support_unread' => 0,
    ];

    foreach ($stores as $store) {
        $operationalStatus = store_registration_operational_status($store);
        if ($operationalStatus === 'suspended') {
            $summary['suspended']++;
        } else {
            $summary['active']++;
        }

        $healthLevel = (string) ($store['health_level'] ?? 'healthy');
        if ($healthLevel === 'healthy') {
            $summary['healthy']++;
        } else {
            $summary['needs_attention']++;
        }
        if (in_array($healthLevel, ['critical', 'suspended'], true)) {
            $summary['critical']++;
        }

        $lastLoginAt = trim((string) ($store['last_login_at'] ?? ''));
        if ($lastLoginAt === '') {
            $summary['offline']++;
        } else {
            try {
                $daysSinceLogin = (int) (new DateTimeImmutable($lastLoginAt))->diff(new DateTimeImmutable('now'))->format('%a');
                if ($daysSinceLogin >= 7) {
                    $summary['offline']++;
                }
            } catch (Throwable $e) {
                $summary['offline']++;
            }
        }

        $summary['support_unread'] += (int) ($store['support_unread_count'] ?? 0);
    }

    return $summary;
}

function store_registration_username_exists(PDO $pdo, string $username, ?int $excludeStoreId = null): bool
{
    $normalized = trim($username);
    if ($normalized === '') {
        return false;
    }

    if (User::usernameExists($pdo, $normalized)) {
        return true;
    }

    $sql = 'SELECT COUNT(*) FROM stores WHERE admin_username = :username';
    $params = [':username' => $normalized];
    if ($excludeStoreId !== null) {
        $sql .= ' AND id <> :id';
        $params[':id'] = $excludeStoreId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn() > 0;
}

function store_registration_approve(PDO $pdo, int $storeId, int $approvedByUserId): array
{
    $store = store_registration_find($pdo, $storeId);
    if (!$store) {
        throw new RuntimeException('Pendaftaran toko tidak ditemukan.');
    }

    if (($store['status'] ?? '') === 'approved') {
        throw new RuntimeException('Toko ini sudah disetujui sebelumnya.');
    }

    if (User::usernameExists($pdo, (string) ($store['admin_username'] ?? ''))) {
        throw new RuntimeException('Username admin toko sudah dipakai user lain.');
    }

    $adminRoleId = store_registration_role_id($pdo, 'admin');
    if ($adminRoleId <= 0) {
        throw new RuntimeException('Role admin tidak ditemukan.');
    }

    $passwordColumn = User::passwordColumn($pdo) ?: 'password_hash';
    $activeColumn = User::activeColumn($pdo);
    $storeIdColumn = User::storeIdColumn($pdo);

    $pdo->beginTransaction();
    try {
        $userData = [
            'role_id' => $adminRoleId,
            'name' => (string) ($store['admin_name'] ?? $store['owner_name'] ?? 'Admin Toko'),
            'username' => (string) ($store['admin_username'] ?? ''),
            $passwordColumn === 'password_hash' ? 'password_hash' : $passwordColumn => (string) ($store['admin_password_hash'] ?? ''),
        ];

        if ($activeColumn) {
            $userData[$activeColumn] = 1;
        }
        if ($storeIdColumn) {
            $userData[$storeIdColumn] = $storeId;
        }

        $adminUserId = User::create($pdo, $userData);

        $update = $pdo->prepare(
            'UPDATE stores
             SET status = :status,
                 approved_by = :approved_by,
                 approved_at = NOW(),
                 rejected_at = NULL,
                 approval_note = NULL
             WHERE id = :id'
        );
        $update->execute([
            ':status' => 'approved',
            ':approved_by' => $approvedByUserId,
            ':id' => $storeId,
        ]);

        $pdo->commit();

        return [
            'store' => store_registration_find($pdo, $storeId),
            'admin_user_id' => $adminUserId,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function store_registration_reject(PDO $pdo, int $storeId, int $approvedByUserId, string $note = ''): void
{
    $store = store_registration_find($pdo, $storeId);
    if (!$store) {
        throw new RuntimeException('Pendaftaran toko tidak ditemukan.');
    }

    $stmt = $pdo->prepare(
        'UPDATE stores
         SET status = :status,
             approved_by = :approved_by,
             rejected_at = NOW(),
             approved_at = NULL,
             approval_note = :approval_note
         WHERE id = :id'
    );
    $stmt->execute([
        ':status' => 'rejected',
        ':approved_by' => $approvedByUserId,
        ':approval_note' => trim($note),
        ':id' => $storeId,
    ]);
}

function store_registration_generate_code(string $storeName): string
{
    $normalized = strtoupper(trim(preg_replace('/[^a-z0-9]+/i', '-', $storeName) ?? 'STORE'));
    $normalized = trim($normalized, '-');
    if ($normalized === '') {
        $normalized = 'STORE';
    }
    $prefix = substr(str_replace('-', '', $normalized), 0, 6);
    if ($prefix === '') {
        $prefix = 'STORE';
    }

    return $prefix . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
}

function store_registration_code_exists(PDO $pdo, string $code): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM stores WHERE store_code = :code');
    $stmt->execute([':code' => $code]);

    return (int) $stmt->fetchColumn() > 0;
}

function store_registration_role_id(PDO $pdo, string $roleName): int
{
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
    $stmt->execute([':name' => $roleName]);

    return (int) $stmt->fetchColumn();
}

function store_referral_create(PDO $pdo, array $data, int $generatedByUserId): array
{
    $label = trim((string) ($data['label'] ?? ''));
    $note = trim((string) ($data['note'] ?? ''));
    $maxUses = (int) ($data['max_uses'] ?? 1);
    $expiresAt = trim((string) ($data['expires_at'] ?? ''));

    if ($label === '') {
        throw new RuntimeException('Nama batch referral wajib diisi.');
    }
    if ($maxUses < 1) {
        throw new RuntimeException('Kuota referral minimal 1 penggunaan.');
    }

    $normalizedExpiry = null;
    if ($expiresAt !== '') {
        $timestamp = strtotime($expiresAt);
        if ($timestamp === false) {
            throw new RuntimeException('Tanggal kedaluwarsa referral tidak valid.');
        }
        $normalizedExpiry = date('Y-m-d H:i:s', $timestamp);
    }

    $code = store_referral_generate_unique_code($pdo, $label);

    $stmt = $pdo->prepare(
        'INSERT INTO store_referral_codes (
            code, label, note, max_uses, used_count, expires_at, is_active, generated_by
         ) VALUES (
            :code, :label, :note, :max_uses, :used_count, :expires_at, :is_active, :generated_by
         )'
    );
    $stmt->execute([
        ':code' => $code,
        ':label' => $label,
        ':note' => $note,
        ':max_uses' => $maxUses,
        ':used_count' => 0,
        ':expires_at' => $normalizedExpiry,
        ':is_active' => 1,
        ':generated_by' => $generatedByUserId,
    ]);

    return store_referral_find($pdo, (int) $pdo->lastInsertId()) ?? [];
}

function store_referral_update(PDO $pdo, int $referralId, array $data): array
{
    $referral = store_referral_find($pdo, $referralId);
    if (!$referral) {
        throw new RuntimeException('Kode referral tidak ditemukan.');
    }

    $label = trim((string) ($data['label'] ?? ''));
    $note = trim((string) ($data['note'] ?? ''));
    $maxUses = (int) ($data['max_uses'] ?? 1);
    $expiresAt = trim((string) ($data['expires_at'] ?? ''));
    $isActive = !empty($data['is_active']) ? 1 : 0;
    $usedCount = (int) ($referral['used_count'] ?? 0);

    if ($label === '') {
        throw new RuntimeException('Nama batch referral wajib diisi.');
    }
    if ($maxUses < 1) {
        throw new RuntimeException('Kuota referral minimal 1 penggunaan.');
    }
    if ($maxUses < $usedCount) {
        throw new RuntimeException('Kuota referral tidak boleh lebih kecil dari jumlah yang sudah dipakai.');
    }

    $normalizedExpiry = null;
    if ($expiresAt !== '') {
        $timestamp = strtotime($expiresAt);
        if ($timestamp === false) {
            throw new RuntimeException('Tanggal kedaluwarsa referral tidak valid.');
        }
        $normalizedExpiry = date('Y-m-d H:i:s', $timestamp);
    }

    $stmt = $pdo->prepare(
        'UPDATE store_referral_codes
         SET label = :label,
             note = :note,
             max_uses = :max_uses,
             expires_at = :expires_at,
             is_active = :is_active
         WHERE id = :id'
    );
    $stmt->execute([
        ':label' => $label,
        ':note' => $note,
        ':max_uses' => $maxUses,
        ':expires_at' => $normalizedExpiry,
        ':is_active' => $isActive,
        ':id' => $referralId,
    ]);

    return store_referral_find($pdo, $referralId) ?? [];
}

function store_referral_all(PDO $pdo, string $search = ''): array
{
    $sql = 'SELECT referral.*,
                   creator.name AS generated_by_name,
                   COUNT(DISTINCT stores.id) AS registrations_total,
                   COUNT(DISTINCT CASE WHEN stores.status = \'approved\' THEN stores.id END) AS approved_total,
                   COUNT(DISTINCT CASE WHEN stores.status = \'pending\' THEN stores.id END) AS pending_total
            FROM store_referral_codes AS referral
            LEFT JOIN users AS creator ON creator.id = referral.generated_by
            LEFT JOIN stores ON stores.referral_code_id = referral.id
                OR stores.referral_code = referral.code';
    $params = [];
    $search = trim($search);
    if ($search !== '') {
        $sql .= ' WHERE referral.code LIKE :search
                  OR referral.label LIKE :search_label
                  OR referral.note LIKE :search_note';
        $searchValue = '%' . $search . '%';
        $params[':search'] = $searchValue;
        $params[':search_label'] = $searchValue;
        $params[':search_note'] = $searchValue;
    }

    $sql .= ' GROUP BY referral.id
              ORDER BY referral.is_active DESC, referral.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['availability_status'] = store_referral_status($row);
        $row['remaining_uses'] = max(0, (int) ($row['max_uses'] ?? 0) - (int) ($row['used_count'] ?? 0));
    }
    unset($row);

    return $rows;
}

function store_referral_find(PDO $pdo, int $referralId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT referral.*,
                creator.name AS generated_by_name
         FROM store_referral_codes AS referral
         LEFT JOIN users AS creator ON creator.id = referral.generated_by
         WHERE referral.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $referralId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $row['availability_status'] = store_referral_status($row);
    $row['remaining_uses'] = max(0, (int) ($row['max_uses'] ?? 0) - (int) ($row['used_count'] ?? 0));

    return $row;
}

function store_referral_find_by_code(PDO $pdo, string $code, bool $forUpdate = false): ?array
{
    $normalized = strtoupper(trim($code));
    if ($normalized === '') {
        return null;
    }

    $sql = 'SELECT referral.*,
                   creator.name AS generated_by_name
            FROM store_referral_codes AS referral
            LEFT JOIN users AS creator ON creator.id = referral.generated_by
            WHERE referral.code = :code
            LIMIT 1';
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':code' => $normalized]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $row['availability_status'] = store_referral_status($row);
    $row['remaining_uses'] = max(0, (int) ($row['max_uses'] ?? 0) - (int) ($row['used_count'] ?? 0));

    return $row;
}

function store_referral_stats(PDO $pdo): array
{
    $stats = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'exhausted' => 0,
        'expired' => 0,
        'used_slots' => 0,
    ];

    $rows = store_referral_all($pdo);
    $stats['total'] = count($rows);

    foreach ($rows as $row) {
        $status = (string) ($row['availability_status'] ?? 'inactive');
        if (array_key_exists($status, $stats)) {
            $stats[$status] += 1;
        }
        $stats['used_slots'] += (int) ($row['used_count'] ?? 0);
    }

    return $stats;
}

function store_referral_set_active(PDO $pdo, int $referralId, bool $isActive): void
{
    $stmt = $pdo->prepare(
        'UPDATE store_referral_codes
         SET is_active = :is_active
         WHERE id = :id'
    );
    $stmt->execute([
        ':is_active' => $isActive ? 1 : 0,
        ':id' => $referralId,
    ]);
}

function store_referral_delete(PDO $pdo, int $referralId): array
{
    $referral = store_referral_find($pdo, $referralId);
    if (!$referral) {
        throw new RuntimeException('Kode referral tidak ditemukan.');
    }

    $code = (string) ($referral['code'] ?? '');
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM stores
         WHERE referral_code_id = :id
            OR referral_code = :code'
    );
    $stmt->execute([
        ':id' => $referralId,
        ':code' => $code,
    ]);
    $linkedStores = (int) $stmt->fetchColumn();
    $usedCount = (int) ($referral['used_count'] ?? 0);

    if ($linkedStores > 0 || $usedCount > 0) {
        throw new RuntimeException('Referral ini sudah dipakai pengajuan toko, jadi tidak bisa dihapus. Nonaktifkan referral agar tidak bisa dipakai lagi.');
    }

    $delete = $pdo->prepare('DELETE FROM store_referral_codes WHERE id = :id LIMIT 1');
    $delete->execute([':id' => $referralId]);

    return $referral;
}

function store_referral_generate_unique_code(PDO $pdo, string $label = 'KASPINDO'): string
{
    $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', substr($label, 0, 4)) ?? '');
    if ($prefix === '') {
        $prefix = 'KSP';
    }

    do {
        $code = $prefix . '-' . date('ym') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM store_referral_codes WHERE code = :code');
        $stmt->execute([':code' => $code]);
        $exists = (int) $stmt->fetchColumn() > 0;
    } while ($exists);

    return $code;
}

function store_referral_redeem(PDO $pdo, int $referralId): void
{
    $stmt = $pdo->prepare(
        'UPDATE store_referral_codes
         SET used_count = used_count + 1,
             last_used_at = NOW()
         WHERE id = :id'
    );
    $stmt->execute([':id' => $referralId]);
}

function store_referral_assert_available(array $referral): void
{
    $status = store_referral_status($referral);
    if ($status === 'inactive') {
        throw new RuntimeException('Kode referral belum aktif.');
    }
    if ($status === 'expired') {
        throw new RuntimeException('Kode referral sudah kedaluwarsa.');
    }
    if ($status === 'exhausted') {
        throw new RuntimeException('Kuota kode referral sudah habis.');
    }
}

function store_referral_status(array $referral): string
{
    if ((int) ($referral['is_active'] ?? 0) !== 1) {
        return 'inactive';
    }

    $expiresAt = trim((string) ($referral['expires_at'] ?? ''));
    if ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time()) {
        return 'expired';
    }

    if ((int) ($referral['used_count'] ?? 0) >= (int) ($referral['max_uses'] ?? 0)) {
        return 'exhausted';
    }

    return 'active';
}
