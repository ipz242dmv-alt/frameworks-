<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogController extends Controller
{
    private static array $logs = [
        [
            'id'         => '1',
            'level'      => 'INFO',
            'message'    => 'Application bootstrapped successfully.',
            'source'     => 'kernel.boot',
            'context'    => ['env' => 'production', 'php_version' => '8.5'],
            'created_at' => '2026-06-01 08:00:00',
            'updated_at' => '2026-06-01 08:00:00',
        ],
        [
            'id'         => '2',
            'level'      => 'WARNING',
            'message'    => 'RAM usage exceeded threshold of 80%.',
            'source'     => 'monitor.memory',
            'context'    => ['usage_percent' => 83, 'threshold' => 80],
            'created_at' => '2026-06-01 09:15:00',
            'updated_at' => '2026-06-01 09:15:00',
        ],
        [
            'id'         => '3',
            'level'      => 'ERROR',
            'message'    => 'Failed to establish database connection.',
            'source'     => 'db.connection',
            'context'    => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306],
            'created_at' => '2026-06-01 10:30:00',
            'updated_at' => '2026-06-01 10:30:00',
        ],
        [
            'id'         => '4',
            'level'      => 'DEBUG',
            'message'    => 'Incoming login request from user agent.',
            'source'     => 'auth.login',
            'context'    => ['ip' => '192.168.1.10', 'method' => 'POST'],
            'created_at' => '2026-06-01 11:00:00',
            'updated_at' => '2026-06-01 11:00:00',
        ],
        [
            'id'         => '5',
            'level'      => 'INFO',
            'message'    => 'Scheduled cleanup task finished without errors.',
            'source'     => 'scheduler.cleanup',
            'context'    => ['deleted_records' => 142, 'duration_ms' => 380],
            'created_at' => '2026-06-01 12:00:00',
            'updated_at' => '2026-06-01 12:00:00',
        ],
    ];

    private array $allowedLevels = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'];

    public function index(Request $request): JsonResponse
    {
        $logs = self::$logs;

        $level  = $request->query('level');
        $source = $request->query('source');
        $from   = $request->query('from');
        $to     = $request->query('to');

        if ($level !== null) {
            $level = strtoupper($level);
            $logs  = array_values(array_filter($logs, fn($l) => $l['level'] === $level));
        }

        if ($source !== null) {
            $logs = array_values(array_filter($logs, fn($l) => str_contains($l['source'], $source)));
        }

        if ($from !== null) {
            $logs = array_values(array_filter($logs, fn($l) => $l['created_at'] >= $from));
        }

        if ($to !== null) {
            $logs = array_values(array_filter($logs, fn($l) => $l['created_at'] <= $to));
        }

        return response()->json([
            'total' => count($logs),
            'data'  => $logs,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $log = collect(self::$logs)->firstWhere('id', $id);

        if (!$log) {
            return response()->json(
                ['error' => sprintf('Log entry with id "%s" was not found.', $id)],
                404
            );
        }

        return response()->json($log);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'level'   => 'required|string',
            'message' => 'required|string',
            'source'  => 'required|string',
        ]);

        $level = strtoupper($request->input('level'));

        if (!in_array($level, $this->allowedLevels, true)) {
            return response()->json(
                [
                    'error'   => sprintf('Invalid log level "%s".', $level),
                    'allowed' => $this->allowedLevels,
                ],
                422
            );
        }

        $now = date('Y-m-d H:i:s');

        $entry = [
            'id'         => uniqid('log_', true),
            'level'      => $level,
            'message'    => trim($request->input('message')),
            'source'     => trim($request->input('source')),
            'context'    => $request->input('context', []),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        self::$logs[] = $entry;

        return response()->json($entry, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        foreach (self::$logs as &$log) {
            if ($log['id'] !== $id) {
                continue;
            }

            if ($request->has('level')) {
                $level = strtoupper($request->input('level'));
                if (!in_array($level, $this->allowedLevels, true)) {
                    return response()->json(
                        [
                            'error'   => sprintf('Invalid log level "%s".', $level),
                            'allowed' => $this->allowedLevels,
                        ],
                        422
                    );
                }
                $log['level'] = $level;
            }

            if ($request->has('message')) {
                $log['message'] = trim($request->input('message'));
            }

            if ($request->has('source')) {
                $log['source'] = trim($request->input('source'));
            }

            if ($request->has('context') && is_array($request->input('context'))) {
                $log['context'] = $request->input('context');
            }

            $log['updated_at'] = date('Y-m-d H:i:s');

            return response()->json($log);
        }

        return response()->json(
            ['error' => sprintf('Log entry with id "%s" was not found.', $id)],
            404
        );
    }

    public function destroy(string $id): JsonResponse
    {
        foreach (self::$logs as $key => $log) {
            if ($log['id'] !== $id) {
                continue;
            }

            unset(self::$logs[$key]);
            self::$logs = array_values(self::$logs);

            return response()->json([
                'message' => sprintf('Log entry "%s" has been deleted successfully.', $id),
            ]);
        }

        return response()->json(
            ['error' => sprintf('Log entry with id "%s" was not found.', $id)],
            404
        );
    }
}