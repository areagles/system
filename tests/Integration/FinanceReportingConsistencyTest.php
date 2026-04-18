<?php
declare(strict_types=1);

namespace SmartSystem\Tests\Integration;

use SmartSystem\Tests\Support\DatabaseTestCase;

final class FinanceReportingConsistencyTest extends DatabaseTestCase
{
    public function testDashboardStatsExcludeCancelledSalesInvoicesAndOpeningBalanceReceipts(): void
    {
        $this->ensureClientsTable();
        $this->ensureInvoicesTable();
        $this->ensureReceiptsTable();

        $beforeStats = finance_dashboard_stats($this->conn);
        $beforeRefs = finance_reference_payloads($this->conn);
        $beforeSalesIds = array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            (array)($beforeRefs['sales_invoices'] ?? [])
        );

        $clientId = $this->createClient('Report Client');
        $cancelledInvoiceId = $this->createInvoice($clientId, 100.00, 'cancelled');
        $openInvoiceId = $this->createInvoice($clientId, 70.00, 'unpaid');

        $this->conn->query("
            INSERT INTO financial_receipts (type, category, amount, description, trans_date, client_id, created_by)
            VALUES ('in', 'client_opening', 500.00, 'Opening balance migrated', CURDATE(), {$clientId}, 'phpunit')
        ");
        $this->conn->query("
            INSERT INTO financial_receipts (type, category, amount, description, trans_date, client_id, created_by)
            VALUES ('in', 'general', 40.00, 'Normal receipt', CURDATE(), {$clientId}, 'phpunit')
        ");

        $stats = finance_dashboard_stats($this->conn);
        $refs = finance_reference_payloads($this->conn);
        $afterSalesIds = array_map(
            static fn(array $row): int => (int)($row['id'] ?? 0),
            (array)($refs['sales_invoices'] ?? [])
        );

        $totalInDelta = (float)$stats['total_in'] - (float)$beforeStats['total_in'];
        $receivablesDelta = (float)$stats['receivables_due'] - (float)$beforeStats['receivables_due'];

        $this->assertSame('40.00', number_format($totalInDelta, 2, '.', ''));
        $this->assertSame('70.00', number_format($receivablesDelta, 2, '.', ''));
        $this->assertNotContains($cancelledInvoiceId, $afterSalesIds);
        $this->assertContains($openInvoiceId, $afterSalesIds);
        $this->assertNotContains($openInvoiceId, $beforeSalesIds);
    }

    private function ensureClientsTable(): void
    {
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS clients (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function ensureInvoicesTable(): void
    {
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS invoices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                inv_date DATE NOT NULL,
                due_date DATE DEFAULT NULL,
                total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                remaining_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                status VARCHAR(40) NOT NULL DEFAULT 'unpaid',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function ensureReceiptsTable(): void
    {
        $this->conn->query("
            CREATE TABLE IF NOT EXISTS financial_receipts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(10) NOT NULL,
                category VARCHAR(40) DEFAULT NULL,
                amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                description TEXT DEFAULT NULL,
                trans_date DATE NOT NULL,
                client_id INT DEFAULT NULL,
                supplier_id INT DEFAULT NULL,
                employee_id INT DEFAULT NULL,
                invoice_id INT DEFAULT NULL,
                payroll_id INT DEFAULT NULL,
                created_by VARCHAR(100) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function createClient(string $name): int
    {
        $code = 'CLI-' . substr(sha1($name . microtime(true) . random_int(1, 999999)), 0, 12);
        $stmt = $this->conn->prepare("INSERT INTO clients (code, name) VALUES (?, ?)");
        $stmt->bind_param('ss', $code, $name);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    private function createInvoice(int $clientId, float $remaining, string $status): int
    {
        $stmt = $this->conn->prepare("
            INSERT INTO invoices (client_id, inv_date, due_date, total_amount, paid_amount, remaining_amount, status)
            VALUES (?, CURDATE(), CURDATE(), ?, 0, ?, ?)
        ");
        $stmt->bind_param('idds', $clientId, $remaining, $remaining, $status);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }
}
