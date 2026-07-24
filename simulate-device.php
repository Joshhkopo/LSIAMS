<?php

declare(strict_types=1);

/**
 * ESP32 terminal simulator — test the device API without hardware.
 *
 * Speaks the exact protocol of the firmware: every request carries
 * device_id, api_key, timestamp and a single-use nonce.
 *
 * Usage:
 *   php scripts/simulate-device.php <command> [args] [--url=U] [--device=D] [--key=K]
 *
 * Commands:
 *   auth                 boot handshake (device authenticate + time sync)
 *   heartbeat            send one heartbeat
 *   open [slot]          verify teacher fingerprint slot (default 1) -> opens session
 *   tap <uid>            student RFID tap against the open session
 *   sync <uid> [uid...]  upload taps as an offline-queue batch
 *   end                  close the open session (generates absents)
 *   demo                 full happy-path: auth -> open -> taps -> duplicate ->
 *                        unknown card -> end
 *
 * Defaults match the seeded development device:
 *   --url=http://localhost:8000  --device=DEV-001
 *   --key=SAMPLE-DEV-KEY-0000000000000000
 *
 * NOTE: fingerprint verification only succeeds while an active schedule for
 * the device's classroom exists at the current time (see docs/DEPLOYMENT.md
 * for a SQL snippet that inserts a "right now" schedule for testing).
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

// ---- Options ----------------------------------------------------------
$options = ['url' => 'http://localhost:8000', 'device' => 'DEV-001', 'key' => 'SAMPLE-DEV-KEY-0000000000000000'];
$args = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(url|device|key)=(.+)$/', $arg, $m)) {
        $options[$m[1]] = $m[2];
    } else {
        $args[] = $arg;
    }
}
$command = $args[0] ?? 'help';
$stateFile = sys_get_temp_dir() . '/lsiams-sim-' . preg_replace('/[^A-Za-z0-9]/', '', $options['device']) . '.json';

// ---- HTTP client (mirrors the firmware's ApiClient) ---------------------
function apiPost(string $path, array $body): array
{
    global $options;
    $body += [
        'device_id' => $options['device'],
        'api_key'   => $options['key'],
        'timestamp' => date('Y-m-d\TH:i:s'),
        'nonce'     => bin2hex(random_bytes(8)),
    ];
    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content'       => json_encode($body),
            'ignore_errors' => true,
            'timeout'       => 10,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false], // dev only
    ]);
    $raw = file_get_contents(rtrim($options['url'], '/') . $path, false, $context);
    if ($raw === false) {
        fwrite(STDERR, "!! Cannot reach {$options['url']}{$path}\n");
        exit(2);
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : ['success' => false, 'message' => 'Non-JSON response: ' . substr($raw, 0, 200)];
}

function show(string $label, array $res): void
{
    printf("[%s] %s %s\n", $label, ($res['success'] ?? false) ? 'OK ' : 'FAIL', $res['message'] ?? '');
    if (!empty($res['data'])) {
        echo '      ' . json_encode($res['data']) . "\n";
    }
}

function saveSession(?string $code): void
{
    global $stateFile;
    file_put_contents($stateFile, json_encode(['session' => $code]));
}

function loadSession(): ?string
{
    global $stateFile;
    if (!is_file($stateFile)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($stateFile), true);
    return $data['session'] ?? null;
}

// ---- Commands ------------------------------------------------------------
function cmdAuth(): void
{
    show('authenticate', apiPost('/api/device/authenticate', []));
}

function cmdHeartbeat(): void
{
    show('heartbeat', apiPost('/api/device/heartbeat', [
        'firmware' => '1.0.0-sim', 'wifi_signal' => -48, 'queue' => 0, 'uptime' => 120,
    ]));
}

function cmdOpen(int $slot): void
{
    $res = apiPost('/api/fingerprint/verify', ['fingerprint_id' => $slot]);
    show('fingerprint', $res);
    if ($res['success'] ?? false) {
        saveSession($res['data']['session_code']);
        echo "      session saved: {$res['data']['session_code']}\n";
    } elseif (str_contains($res['message'] ?? '', 'schedule')) {
        echo "      hint: insert a schedule covering the current time (docs/DEPLOYMENT.md).\n";
    }
}

function cmdTap(string $uid): void
{
    $session = loadSession();
    if ($session === null) {
        fwrite(STDERR, "!! No open session — run 'open' first.\n");
        exit(2);
    }
    show("tap $uid", apiPost('/api/attendance/record', ['session_id' => $session, 'rfid_uid' => $uid]));
}

function cmdSync(array $uids): void
{
    $session = loadSession();
    if ($session === null) {
        fwrite(STDERR, "!! No open session — run 'open' first.\n");
        exit(2);
    }
    $records = [];
    foreach ($uids as $i => $uid) {
        $records[] = [
            'session_id' => $session,
            'rfid_uid'   => $uid,
            // Simulate offline taps captured a few minutes ago.
            'timestamp'  => date('Y-m-d\TH:i:s', time() - 300 + $i * 30),
        ];
    }
    show('sync', apiPost('/api/attendance/sync', ['records' => $records]));
}

function cmdEnd(): void
{
    $session = loadSession();
    if ($session === null) {
        fwrite(STDERR, "!! No open session — run 'open' first.\n");
        exit(2);
    }
    show('end', apiPost('/api/attendance/end', ['session_id' => $session]));
    saveSession(null);
}

switch ($command) {
    case 'auth':      cmdAuth(); break;
    case 'heartbeat': cmdHeartbeat(); break;
    case 'open':      cmdOpen((int) ($args[1] ?? 1)); break;
    case 'tap':
        if (!isset($args[1])) { fwrite(STDERR, "usage: tap <uid>\n"); exit(2); }
        cmdTap(strtoupper($args[1]));
        break;
    case 'sync':
        $uids = array_map('strtoupper', array_slice($args, 1));
        if ($uids === []) { fwrite(STDERR, "usage: sync <uid> [uid...]\n"); exit(2); }
        cmdSync($uids);
        break;
    case 'end':       cmdEnd(); break;

    case 'demo':
        echo "=== L-SIAMS device simulator: full attendance flow ===\n";
        cmdAuth();
        cmdHeartbeat();
        cmdOpen(1);
        if (loadSession() === null) {
            echo "Demo stopped: could not open a session (see hint above).\n";
            exit(1);
        }
        foreach (['04A7C1935D', '04B2D4816E', '04C9E2A47F'] as $uid) {
            cmdTap($uid);
        }
        echo "--- duplicate tap (must be rejected) ---\n";
        cmdTap('04A7C1935D');
        echo "--- unknown card (must be rejected + queued for admin) ---\n";
        cmdTap('DEADBEEF01');
        echo "--- offline-queue sync for the remaining student ---\n";
        cmdSync(['04D1F3B580']);
        echo "--- closing session (absents auto-generated) ---\n";
        cmdEnd();
        echo "=== Done. Check the admin dashboard: live feed, unknown RFID queue, notifications. ===\n";
        break;

    default:
        echo "Usage: php scripts/simulate-device.php <auth|heartbeat|open [slot]|tap <uid>|sync <uid...>|end|demo>\n";
        echo "       [--url=http://localhost:8000] [--device=DEV-001] [--key=...]\n";
}
