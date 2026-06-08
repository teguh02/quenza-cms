<?php
declare(strict_types=1);

namespace Quenza\Core\Cms;

use DateTimeImmutable;
use Quenza\Core\Database\DatabaseManager;

final class ActivityLogService
{
    public function __construct(
        private readonly DatabaseManager $database,
    ) {
    }

    public function log(string $action, string $description, ?int $actorUserId = null, ?string $subjectType = null, ?int $subjectId = null): void
    {
        if (!$this->database->connection()->hasTable('activity_logs')) {
            return;
        }

        $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->database->insert('activity_logs', [
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'description' => $description,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 8): array
    {
        if (!$this->database->connection()->hasTable('activity_logs')) {
            return [];
        }

        return $this->database->select(
            sprintf(
                'SELECT al.*, u.full_name AS actor_name FROM %s al LEFT JOIN %s u ON u.id = al.actor_user_id ORDER BY al.created_at DESC LIMIT %d',
                $this->database->quotedTable('activity_logs'),
                $this->database->quotedTable('users'),
                max(1, $limit),
            ),
        );
    }
}
