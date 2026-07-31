<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Helpers\Auth;
use App\Models\Backup;
use App\Services\BackupService;

final class BackupController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('backup/index', [
            'pageTitle' => 'Backup & Restore',
            'backups'   => (new Backup())->all([], 'id DESC', 100),
        ]);
    }

    public function create(Request $request): never
    {
        [$ok, $message] = (new BackupService())->create('manual');
        $ok ? $this->ok($message) : $this->fail($message, 500);
    }

    /** Restore requires administrator password re-authentication. */
    public function restore(Request $request): never
    {
        $stmt = Database::pdo()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([Auth::id()]);
        $hash = $stmt->fetchColumn();
        if ($hash === false || !password_verify((string) $request->input('confirm_password', ''), (string) $hash)) {
            $this->fail('Password confirmation failed. Restore aborted.', 403);
        }

        [$ok, $message] = (new BackupService())->restore((int) $request->param('id'));
        $ok ? $this->ok($message) : $this->fail($message, 500);
    }
}
