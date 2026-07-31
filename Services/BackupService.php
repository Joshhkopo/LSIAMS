<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Backup;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Helpers\Logger;

/**
 * Database backup / restore using mysqldump + gzip with optional AES
 * encryption (openssl). Restores require administrator re-authentication
 * at the controller level.
 */
final class BackupService
{
    public function __construct(private readonly Backup $backups = new Backup())
    {
    }

    /** @return array{0: bool, 1: string} [ok, message] */
    public function create(string $label = 'manual'): array
    {
        $dir = Config::get('app.backup.dir');
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $name = sprintf('lsiams_%s_%s', preg_replace('/[^a-z0-9_\-]/i', '', $label), date('Ymd_His'));
        $file = $dir . '/' . $name . '.sql.gz';

        $cmd = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --routines %s | gzip > %s',
            escapeshellarg((string) Config::get('db.host')),
            escapeshellarg((string) Config::get('db.port')),
            escapeshellarg((string) Config::get('db.username')),
            escapeshellarg((string) Config::get('db.password')),
            escapeshellarg((string) Config::get('db.database')),
            escapeshellarg($file),
        );
        exec($cmd . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0 || !is_file($file) || filesize($file) === 0) {
            Logger::error('Backup failed', ['exit' => $exitCode]);
            $this->backups->create(['name' => $name, 'file_path' => '', 'status' => 'failed', 'created_by' => Auth::id()]);
            return [false, 'Backup failed. Check the server logs.'];
        }

        // Optional at-rest encryption of the archive.
        $key = (string) Config::get('app.backup.key');
        if ($key !== '') {
            $enc = $file . '.enc';
            $encCmd = sprintf(
                'openssl enc -aes-256-cbc -pbkdf2 -salt -in %s -out %s -pass pass:%s',
                escapeshellarg($file), escapeshellarg($enc), escapeshellarg($key),
            );
            exec($encCmd, $o, $encCode);
            if ($encCode === 0 && is_file($enc)) {
                unlink($file);
                $file = $enc;
            }
        }

        $this->backups->create([
            'name'       => $name,
            'file_path'  => str_replace(BASE_PATH . '/', '', $file),
            'size_bytes' => filesize($file),
            'status'     => 'completed',
            'created_by' => Auth::id(),
        ]);
        Audit::log('backup_created', 'backup', $name);
        return [true, 'Backup completed: ' . basename($file)];
    }

    /** @return array{0: bool, 1: string} */
    public function restore(int $backupId): array
    {
        $backup = $this->backups->find($backupId);
        if ($backup === null || $backup['status'] === 'failed') {
            return [false, 'Backup not found.'];
        }
        $file = BASE_PATH . '/' . $backup['file_path'];
        if (!is_file($file)) {
            return [false, 'Backup file is missing from disk.'];
        }

        $key = (string) Config::get('app.backup.key');
        $sqlSource = $file;
        if (str_ends_with($file, '.enc')) {
            if ($key === '') {
                return [false, 'Backup is encrypted but no key is configured.'];
            }
            $sqlSource = tempnam(sys_get_temp_dir(), 'lsiams_restore_') . '.sql.gz';
            exec(sprintf(
                'openssl enc -d -aes-256-cbc -pbkdf2 -in %s -out %s -pass pass:%s',
                escapeshellarg($file), escapeshellarg($sqlSource), escapeshellarg($key),
            ), $o, $code);
            if ($code !== 0) {
                return [false, 'Could not decrypt the backup archive.'];
            }
        }

        $cmd = sprintf(
            'gunzip -c %s | mysql --host=%s --port=%s --user=%s --password=%s %s',
            escapeshellarg($sqlSource),
            escapeshellarg((string) Config::get('db.host')),
            escapeshellarg((string) Config::get('db.port')),
            escapeshellarg((string) Config::get('db.username')),
            escapeshellarg((string) Config::get('db.password')),
            escapeshellarg((string) Config::get('db.database')),
        );
        exec($cmd . ' 2>&1', $output, $exitCode);
        if ($sqlSource !== $file && is_file($sqlSource)) {
            unlink($sqlSource);
        }

        if ($exitCode !== 0) {
            Logger::error('Restore failed', ['exit' => $exitCode, 'backup' => $backupId]);
            return [false, 'Restore failed. Check the server logs.'];
        }

        $this->backups->update($backupId, ['status' => 'restored']);
        Audit::log('backup_restored', 'backup', $backupId);
        return [true, 'Database restored from ' . $backup['name'] . '.'];
    }
}
