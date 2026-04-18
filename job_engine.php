<?php

declare(strict_types=1);

if (!function_exists('job_issue_material_cost')) {
    function job_issue_material_cost(mysqli $conn, int $jobId, int $userId, int $itemId, int $warehouseId, float $qty, string $stageKey = '', string $extraNotes = ''): bool
    {
        if ($jobId <= 0 || $userId <= 0 || $itemId <= 0 || $warehouseId <= 0 || $qty <= 0) {
            throw new RuntimeException('invalid_material_payload');
        }

        $conn->begin_transaction();
        try {
            $stmtStock = $conn->prepare("SELECT quantity FROM inventory_stock WHERE item_id = ? AND warehouse_id = ? FOR UPDATE");
            $stmtStock->bind_param('ii', $itemId, $warehouseId);
            $stmtStock->execute();
            $stockRow = $stmtStock->get_result()->fetch_assoc();
            $stmtStock->close();

            $available = (float)($stockRow['quantity'] ?? 0);
            if ($available < $qty) {
                throw new RuntimeException('insufficient_stock');
            }

            $stmtItem = $conn->prepare("SELECT name, item_code, IFNULL(avg_unit_cost, 0) AS avg_unit_cost FROM inventory_items WHERE id = ? LIMIT 1");
            $stmtItem->bind_param('i', $itemId);
            $stmtItem->execute();
            $itemRow = $stmtItem->get_result()->fetch_assoc();
            $stmtItem->close();
            if (!$itemRow) {
                throw new RuntimeException('item_not_found');
            }

            $unitCost = (float)($itemRow['avg_unit_cost'] ?? 0);
            $totalCost = round($qty * $unitCost, 2);
            $signedQty = -$qty;
            $note = "صرف خامة للعملية #{$jobId}";
            if ($stageKey !== '') {
                $note .= " [{$stageKey}]";
            }
            if ($extraNotes !== '') {
                $note .= " - " . $extraNotes;
            }

            $stmtUpdStock = $conn->prepare("UPDATE inventory_stock SET quantity = quantity - ? WHERE item_id = ? AND warehouse_id = ?");
            $stmtUpdStock->bind_param('dii', $qty, $itemId, $warehouseId);
            $stmtUpdStock->execute();
            $stmtUpdStock->close();

            $stmtTrans = $conn->prepare("
                INSERT INTO inventory_transactions (
                    item_id, warehouse_id, user_id, transaction_type, quantity, related_order_id, notes,
                    unit_cost, total_cost, reference_type, reference_id, stage_key
                ) VALUES (?, ?, ?, 'out', ?, ?, ?, ?, ?, 'job_material', ?, ?)
            ");
            if (!$stmtTrans) {
                throw new RuntimeException('trans_insert_failed');
            }
            $stmtTrans->bind_param(
                'iiidisddis',
                $itemId,
                $warehouseId,
                $userId,
                $signedQty,
                $jobId,
                $note,
                $unitCost,
                $totalCost,
                $jobId,
                $stageKey
            );
            $stmtTrans->execute();
            $stmtTrans->close();

            $conn->commit();
            return true;
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}

if (!function_exists('job_reverse_material_cost')) {
    function job_reverse_material_cost(mysqli $conn, int $jobId, int $materialTransId): bool
    {
        if ($jobId <= 0 || $materialTransId <= 0) {
            throw new RuntimeException('invalid_material_transaction');
        }

        $conn->begin_transaction();
        try {
            $stmtTrans = $conn->prepare("
                SELECT item_id, warehouse_id, quantity
                FROM inventory_transactions
                WHERE id = ? AND related_order_id = ? AND reference_type = 'job_material'
                LIMIT 1
                FOR UPDATE
            ");
            if (!$stmtTrans) {
                throw new RuntimeException('load_material_transaction_failed');
            }
            $stmtTrans->bind_param('ii', $materialTransId, $jobId);
            $stmtTrans->execute();
            $transRow = $stmtTrans->get_result()->fetch_assoc();
            $stmtTrans->close();
            if (!$transRow) {
                throw new RuntimeException('material_transaction_not_found');
            }

            $itemId = (int)($transRow['item_id'] ?? 0);
            $warehouseId = (int)($transRow['warehouse_id'] ?? 0);
            $issuedQty = abs((float)($transRow['quantity'] ?? 0));
            if ($itemId <= 0 || $warehouseId <= 0 || $issuedQty <= 0) {
                throw new RuntimeException('material_transaction_invalid');
            }

            $stmtStock = $conn->prepare("
                INSERT INTO inventory_stock (item_id, warehouse_id, quantity)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
            ");
            $stmtStock->bind_param('iid', $itemId, $warehouseId, $issuedQty);
            $stmtStock->execute();
            $stmtStock->close();

            $stmtDelete = $conn->prepare("
                DELETE FROM inventory_transactions
                WHERE id = ? AND related_order_id = ? AND reference_type = 'job_material'
            ");
            $stmtDelete->bind_param('ii', $materialTransId, $jobId);
            $stmtDelete->execute();
            $stmtDelete->close();

            $conn->commit();
            return true;
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}

if (!function_exists('job_add_service_cost')) {
    function job_add_service_cost(mysqli $conn, int $jobId, int $userId, string $stageKey, string $serviceName, float $qty, float $unitCost, string $notes = ''): bool
    {
        if ($jobId <= 0 || $userId <= 0 || $stageKey === '' || $serviceName === '' || $qty <= 0 || $unitCost < 0) {
            throw new RuntimeException('invalid_service_cost');
        }
        $totalCost = round($qty * $unitCost, 2);
        $stmtSvc = $conn->prepare("
            INSERT INTO job_service_costs (job_id, stage_key, service_name, qty, unit_cost, total_cost, notes, created_by_user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmtSvc) {
            throw new RuntimeException('insert_service_cost_failed');
        }
        $stmtSvc->bind_param('issdddsi', $jobId, $stageKey, $serviceName, $qty, $unitCost, $totalCost, $notes, $userId);
        $stmtSvc->execute();
        $stmtSvc->close();
        return true;
    }
}

if (!function_exists('job_update_service_cost')) {
    function job_update_service_cost(mysqli $conn, int $jobId, int $serviceCostId, string $stageKey, string $serviceName, float $qty, float $unitCost, string $notes = ''): bool
    {
        if ($jobId <= 0 || $serviceCostId <= 0 || $stageKey === '' || $serviceName === '' || $qty <= 0 || $unitCost < 0) {
            throw new RuntimeException('invalid_service_cost');
        }
        $totalCost = round($qty * $unitCost, 2);
        $stmtUpdSvc = $conn->prepare("
            UPDATE job_service_costs
            SET stage_key = ?, service_name = ?, qty = ?, unit_cost = ?, total_cost = ?, notes = ?
            WHERE id = ? AND job_id = ?
        ");
        if (!$stmtUpdSvc) {
            throw new RuntimeException('update_service_cost_failed');
        }
        $stmtUpdSvc->bind_param('ssdddsii', $stageKey, $serviceName, $qty, $unitCost, $totalCost, $notes, $serviceCostId, $jobId);
        $stmtUpdSvc->execute();
        $stmtUpdSvc->close();
        return true;
    }
}

if (!function_exists('job_delete_service_cost')) {
    function job_delete_service_cost(mysqli $conn, int $jobId, int $serviceCostId): bool
    {
        if ($jobId <= 0 || $serviceCostId <= 0) {
            throw new RuntimeException('invalid_service_cost_delete');
        }
        $stmtDel = $conn->prepare("DELETE FROM job_service_costs WHERE id = ? AND job_id = ?");
        if (!$stmtDel) {
            throw new RuntimeException('delete_service_cost_failed');
        }
        $stmtDel->bind_param('ii', $serviceCostId, $jobId);
        $stmtDel->execute();
        $stmtDel->close();
        return true;
    }
}

if (!function_exists('job_material_cost_rows')) {
    function job_material_cost_rows(mysqli $conn, int $jobId, int $limit = 50): array
    {
        $rows = [];
        $stmt = $conn->prepare("
            SELECT
                t.id,
                t.item_id,
                t.warehouse_id,
                ABS(t.quantity) AS qty_used,
                IFNULL(t.unit_cost, 0) AS unit_cost,
                IFNULL(t.total_cost, 0) AS total_cost,
                IFNULL(t.stage_key, '') AS stage_key,
                IFNULL(t.notes, '') AS notes,
                t.transaction_date,
                i.name AS item_name,
                i.item_code AS item_code,
                w.name AS warehouse_name
            FROM inventory_transactions t
            LEFT JOIN inventory_items i ON i.id = t.item_id
            LEFT JOIN warehouses w ON w.id = t.warehouse_id
            WHERE t.reference_type = 'job_material' AND t.related_order_id = ?
            ORDER BY t.id DESC
            LIMIT ?
        ");
        if (!$stmt) {
            return $rows;
        }
        $stmt->bind_param('ii', $jobId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('job_service_cost_rows')) {
    function job_service_cost_rows(mysqli $conn, int $jobId, int $limit = 50): array
    {
        $rows = [];
        $stmt = $conn->prepare("
            SELECT id, stage_key, service_name, qty, unit_cost, total_cost, notes, created_at
            FROM job_service_costs
            WHERE job_id = ?
            ORDER BY id DESC
            LIMIT ?
        ");
        if (!$stmt) {
            return $rows;
        }
        $stmt->bind_param('ii', $jobId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('job_load_header_data')) {
    function job_load_header_data(mysqli $conn, int $jobId): ?array
    {
        static $cache = [];
        if (isset($cache[$jobId])) {
            return $cache[$jobId];
        }
        $stmt = $conn->prepare("
            SELECT j.*, c.name AS client_name, c.phone AS client_phone
            FROM job_orders j
            LEFT JOIN clients c ON j.client_id = c.id
            WHERE j.id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $jobId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $cache[$jobId] = $row ?: null;
        return $cache[$jobId];
    }
}

if (!function_exists('job_resolve_id')) {
    function job_resolve_id(mysqli $conn, $jobRef): int
    {
        if (is_int($jobRef) || ctype_digit((string)$jobRef)) {
            $rawId = (int)$jobRef;
            if ($rawId > 0) {
                $direct = job_load_header_data($conn, $rawId);
                if (is_array($direct)) {
                    return $rawId;
                }
            }
        }

        $jobNumber = strtoupper(trim((string)$jobRef));
        if ($jobNumber === '') {
            return 0;
        }

        $stmt = $conn->prepare("SELECT id FROM job_orders WHERE job_number = ? LIMIT 1");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('s', $jobNumber);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['id'] ?? 0);
    }
}

if (!function_exists('job_assignable_users')) {
    function job_assignable_users(mysqli $conn): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        $rows = [];
        $users_rs = $conn->query("SELECT id, full_name, role FROM users ORDER BY full_name ASC");
        if ($users_rs) {
            while ($row = $users_rs->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $cache = $rows;
        return $cache;
    }
}

if (!function_exists('job_cost_warehouses')) {
    function job_cost_warehouses(mysqli $conn): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        $rows = [];
        $res = $conn->query("SELECT id, name FROM warehouses WHERE is_active = 1 ORDER BY name ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $cache = $rows;
        return $cache;
    }
}

if (!function_exists('job_cost_items')) {
    function job_cost_items(mysqli $conn): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        $rows = [];
        $res = $conn->query("SELECT id, item_code, name, IFNULL(avg_unit_cost, 0) AS avg_unit_cost FROM inventory_items ORDER BY name ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $cache = $rows;
        return $cache;
    }
}

if (!function_exists('job_service_catalog_map')) {
    function job_service_catalog_map(mysqli $conn, string $jobType): array
    {
        static $cache = [];
        $cacheKey = strtolower(trim($jobType));
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }
        $rows = app_operation_catalog_entries($conn, $jobType, true);
        $map = [];
        foreach ($rows as $row) {
            $map[(string)$row['item_label']] = (float)($row['default_unit_price'] ?? 0);
        }
        $cache[$cacheKey] = ['rows' => $rows, 'map' => $map];
        return $cache[$cacheKey];
    }
}

if (!function_exists('job_stage_map_for_type')) {
    function job_stage_map_for_type(mysqli $conn, string $jobType): array
    {
        static $cache = [];
        $cacheKey = strtolower(trim($jobType));
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }
        $stageMap = app_operation_stage_map($conn, $jobType, true);
        if (empty($stageMap)) {
            $stageMap = ['briefing' => '1. التجهيز'];
        }
        $cache[$cacheKey] = $stageMap;
        return $cache[$cacheKey];
    }
}

if (!function_exists('job_type_by_id')) {
    function job_type_by_id(mysqli $conn, int $jobId): string
    {
        $job = job_load_header_data($conn, $jobId);
        return strtolower(trim((string)($job['job_type'] ?? '')));
    }
}

if (!function_exists('job_archive_transition')) {
    function job_archive_transition(mysqli $conn, int $jobId): bool
    {
        if ($jobId <= 0) {
            throw new RuntimeException('invalid_job_id');
        }
        $jobType = job_type_by_id($conn, $jobId);
        $workflow = $jobType !== '' ? app_operation_workflow($conn, $jobType, ['briefing' => '1. التجهيز', 'completed' => 'الأرشيف']) : [];
        $archiveStage = !empty($workflow)
            ? (isset($workflow['completed']) ? 'completed' : (string)(array_key_last($workflow) ?: 'completed'))
            : 'completed';
        if ($archiveStage === '') {
            $archiveStage = 'completed';
        }
        return app_update_job_stage($conn, $jobId, $archiveStage, 'completed');
    }
}

if (!function_exists('job_reopen_transition')) {
    function job_reopen_transition(mysqli $conn, int $jobId): bool
    {
        if ($jobId <= 0) {
            throw new RuntimeException('invalid_job_id');
        }
        $jobType = job_type_by_id($conn, $jobId);
        $workflow = $jobType !== '' ? app_operation_workflow($conn, $jobType, ['briefing' => '1. التجهيز', 'completed' => 'الأرشيف']) : [];
        $firstStage = !empty($workflow) ? (string)(array_key_first($workflow) ?: 'briefing') : 'briefing';
        if ($firstStage === '') {
            $firstStage = 'briefing';
        }
        return app_update_job_stage($conn, $jobId, $firstStage, 'processing');
    }
}

if (!function_exists('job_view_context')) {
    function job_view_context(mysqli $conn, int $jobId, bool $canManageAcl, string $jobType, int $editingServiceCostId = 0): array
    {
        $jobAssignments = app_job_assignments($conn, $jobId);
        $assignableUsers = $canManageAcl ? job_assignable_users($conn) : [];
        $jobStageMap = job_stage_map_for_type($conn, $jobType);
        $jobFinancial = app_job_financial_summary($conn, $jobId);
        $costWarehouses = job_cost_warehouses($conn);
        $costItems = job_cost_items($conn);
        $serviceCatalogBundle = job_service_catalog_map($conn, $jobType);
        $materialCostRows = job_material_cost_rows($conn, $jobId, 50);
        $serviceCostRows = job_service_cost_rows($conn, $jobId, 50);

        $serviceCostEditRow = null;
        if ($editingServiceCostId > 0) {
            foreach ($serviceCostRows as $candidateServiceRow) {
                if ((int)($candidateServiceRow['id'] ?? 0) === $editingServiceCostId) {
                    $serviceCostEditRow = $candidateServiceRow;
                    break;
                }
            }
        }

        return [
            'job_assignments' => $jobAssignments,
            'assignable_users' => $assignableUsers,
            'job_stage_map' => $jobStageMap,
            'job_financial' => $jobFinancial,
            'cost_warehouses' => $costWarehouses,
            'cost_items' => $costItems,
            'service_catalog_rows' => $serviceCatalogBundle['rows'],
            'service_catalog_map' => $serviceCatalogBundle['map'],
            'material_cost_rows' => $materialCostRows,
            'service_cost_rows' => $serviceCostRows,
            'service_cost_edit_row' => $serviceCostEditRow,
        ];
    }
}

if (!function_exists('job_profitability_summary')) {
    function job_profitability_summary(array $jobFinancial, callable $translator): array
    {
        $status = (string)($jobFinancial['status'] ?? '');
        $profitClass = '';
        $profitText = $translator('العملية متعادلة', 'Job is neutral');
        if ($status === 'profit') {
            $profitClass = 'profit';
            $profitText = $translator('العملية رابحة', 'Job is profitable');
        } elseif ($status === 'loss') {
            $profitClass = 'loss';
            $profitText = $translator('العملية خاسرة', 'Job is losing');
        }

        return [
            'status' => $status,
            'profit_class' => $profitClass,
            'profit_text' => $profitText,
            'cards' => [
                [
                    'label' => $translator('إيراد العملية', 'Job revenue'),
                    'value' => number_format((float)($jobFinancial['revenue'] ?? 0), 2) . ' EGP',
                    'class' => '',
                ],
                [
                    'label' => $translator('الفواتير المرتبطة / المحصل', 'Linked invoices / collected'),
                    'value' => (int)($jobFinancial['invoice_count'] ?? 0) . ' / ' . number_format((float)($jobFinancial['paid_revenue'] ?? 0), 2) . ' EGP',
                    'class' => '',
                ],
                [
                    'label' => $translator('المتبقي على فواتير العملية', 'Outstanding job invoices'),
                    'value' => number_format((float)($jobFinancial['remaining_revenue'] ?? 0), 2) . ' EGP',
                    'class' => '',
                ],
                [
                    'label' => $translator('تكلفة الخامات المصروفة', 'Issued material cost'),
                    'value' => number_format((float)($jobFinancial['material_cost'] ?? 0), 2) . ' EGP',
                    'class' => '',
                ],
                [
                    'label' => $translator('تكلفة الخدمات/المراحل', 'Service/stage cost'),
                    'value' => number_format((float)($jobFinancial['service_cost'] ?? 0), 2) . ' EGP',
                    'class' => '',
                ],
                [
                    'label' => $translator('صافي العملية (ربح/خسارة)', 'Net result (profit/loss)'),
                    'value' => number_format((float)($jobFinancial['profit'] ?? 0), 2) . ' EGP',
                    'class' => $profitClass,
                ],
            ],
        ];
    }
}
