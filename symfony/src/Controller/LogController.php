<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/logs', name: 'logs_')]
final class LogController extends AbstractController
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

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $logs = self::$logs;

        $level  = $request->query->get('level');
        $source = $request->query->get('source');
        $from   = $request->query->get('from');
        $to     = $request->query->get('to');

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

        return $this->json([
            'total' => count($logs),
            'data'  => $logs,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $log = $this->findById($id);

        if ($log === null) {
            return $this->json(
                ['error' => sprintf('Log entry with id "%s" was not found.', $id)],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json($log);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(
                ['error' => 'Invalid JSON payload.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $missing = [];
        foreach (['level', 'message', 'source'] as $field) {
            if (empty($body[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            return $this->json(
                [
                    'error'   => 'Validation failed. Required fields are missing.',
                    'missing' => $missing,
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        $level = strtoupper($body['level']);

        if (!in_array($level, $this->allowedLevels, true)) {
            return $this->json(
                [
                    'error'   => sprintf('Invalid log level "%s".', $level),
                    'allowed' => $this->allowedLevels,
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $now = date('Y-m-d H:i:s');

        $entry = [
            'id'         => uniqid('log_', true),
            'level'      => $level,
            'message'    => trim($body['message']),
            'source'     => trim($body['source']),
            'context'    => isset($body['context']) && is_array($body['context'])
                                ? $body['context']
                                : [],
            'created_at' => $now,
            'updated_at' => $now,
        ];

        self::$logs[] = $entry;

        return $this->json($entry, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(Request $request, string $id): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(
                ['error' => 'Invalid JSON payload.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        foreach (self::$logs as &$log) {
            if ($log['id'] !== $id) {
                continue;
            }

            if (isset($body['level'])) {
                $level = strtoupper($body['level']);
                if (!in_array($level, $this->allowedLevels, true)) {
                    return $this->json(
                        [
                            'error'   => sprintf('Invalid log level "%s".', $level),
                            'allowed' => $this->allowedLevels,
                        ],
                        Response::HTTP_UNPROCESSABLE_ENTITY
                    );
                }
                $log['level'] = $level;
            }

            if (isset($body['message'])) {
                $log['message'] = trim($body['message']);
            }

            if (isset($body['source'])) {
                $log['source'] = trim($body['source']);
            }

            if (isset($body['context']) && is_array($body['context'])) {
                $log['context'] = $body['context'];
            }

            $log['updated_at'] = date('Y-m-d H:i:s');

            return $this->json($log);
        }

        return $this->json(
            ['error' => sprintf('Log entry with id "%s" was not found.', $id)],
            Response::HTTP_NOT_FOUND
        );
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        foreach (self::$logs as $key => $log) {
            if ($log['id'] !== $id) {
                continue;
            }

            unset(self::$logs[$key]);
            self::$logs = array_values(self::$logs);

            return $this->json([
                'message' => sprintf('Log entry "%s" has been deleted successfully.', $id),
            ]);
        }

        return $this->json(
            ['error' => sprintf('Log entry with id "%s" was not found.', $id)],
            Response::HTTP_NOT_FOUND
        );
    }

    private function findById(string $id): ?array
    {
        foreach (self::$logs as $log) {
            if ($log['id'] === $id) {
                return $log;
            }
        }

        return null;
    }
}