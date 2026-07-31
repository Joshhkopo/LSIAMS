<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\AuditLog;

final class AuditLogController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('audit/index', ['pageTitle' => 'Audit Logs']);
    }

    public function data(Request $request): never
    {
        [$limit, $offset] = $this->paging($request);
        $this->ok('OK', (new AuditLog())->search([
            'search'    => trim((string) $request->query('search', '')),
            'module'    => $request->query('module'),
            'date_from' => $request->query('date_from'),
            'date_to'   => $request->query('date_to'),
        ], $limit, $offset));
    }
}
