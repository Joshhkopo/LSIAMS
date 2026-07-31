<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Validator;
use App\Models\SecurityLog;

final class SecurityController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('security/index', ['pageTitle' => 'Security Center']);
    }

    public function data(Request $request): never
    {
        [$limit, $offset] = $this->paging($request);
        $logs = new SecurityLog();
        $pdo = Database::pdo();

        $blocked = $pdo->query(
            'SELECT bi.*, u.username AS blocked_by_name FROM blocked_ips bi
             LEFT JOIN users u ON u.id = bi.blocked_by
             WHERE bi.unblocked_at IS NULL ORDER BY bi.id DESC LIMIT 100'
        )->fetchAll();

        $this->ok('OK', [
            'logs'    => $logs->search([
                'severity' => $request->query('severity'),
                'resolved' => $request->query('resolved'),
                'event'    => $request->query('event'),
            ], $limit, $offset),
            'summary'     => $logs->weeklySummary(),
            'blocked_ips' => $blocked,
        ]);
    }

    public function resolve(Request $request): never
    {
        $id = (int) $request->param('id');
        $logs = new SecurityLog();
        if ($logs->find($id) === null) {
            $this->fail('Security event not found.', 404);
        }
        $logs->update($id, [
            'resolved'    => 1,
            'resolved_by' => Auth::id(),
            'notes'       => substr((string) $request->input('notes', ''), 0, 500),
        ]);
        Audit::log('security_event_resolved', 'security', $id);
        $this->ok('Security event marked as resolved.');
    }

    public function blockIp(Request $request): never
    {
        $v = Validator::make($request->all(), [
            'ip_address' => 'required|max:45',
            'reason'     => 'required|max:255',
        ]);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422);
        }
        $ip = (string) $request->input('ip_address');
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->fail('Invalid IP address.', 422);
        }
        Database::pdo()->prepare(
            'INSERT INTO blocked_ips (ip_address, reason, blocked_by, created_at) VALUES (?, ?, ?, NOW())'
        )->execute([$ip, (string) $request->input('reason'), Auth::id()]);
        Audit::log('ip_blocked', 'security', $ip, null, ['reason' => $request->input('reason')]);
        $this->ok('IP address blocked.');
    }

    public function unblockIp(Request $request): never
    {
        $id = (int) $request->param('id');
        $count = Database::pdo()->prepare('UPDATE blocked_ips SET unblocked_at = NOW() WHERE id = ? AND unblocked_at IS NULL');
        $count->execute([$id]);
        if ($count->rowCount() === 0) {
            $this->fail('Blocked IP entry not found.', 404);
        }
        Audit::log('ip_unblocked', 'security', $id);
        $this->ok('IP address unblocked.');
    }
}
