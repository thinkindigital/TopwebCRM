<?php

namespace Webkul\TopwebChat\Services;

use Webkul\Activity\Models\Activity;

/**
 * E14-R2 S3: classificação central de actionability de Activities.
 *
 * ACTIONABLE: tipos comerciais que podem virar próxima ação.
 * RECORD: registros passivos (note).
 * SYSTEM: tipos técnicos/automáticos (system).
 * Desconhecido nunca é actionable por default — só entra por classificação
 * explícita nesta classe. Attendance ligada a topweb_chat_attendances continua
 * excluída por vínculo no NextActionService, como segunda barreira.
 */
class ActivityActionabilityPolicy
{
    public const ACTIONABLE_TYPES = [
        'call',
        'meeting',
        'visit',
        'follow-up',
        'followup',
        'task',
    ];

    public const RECORD_TYPES = ['note'];

    public const SYSTEM_TYPES = ['system'];

    public static function normalize(string $type): string
    {
        return mb_strtolower(trim($type));
    }

    /**
     * @return 'actionable'|'record'|'system'|'unknown'
     */
    public static function category(string $type): string
    {
        $normalized = self::normalize($type);

        if (in_array($normalized, self::ACTIONABLE_TYPES, true)) {
            return 'actionable';
        }

        if (in_array($normalized, self::RECORD_TYPES, true)) {
            return 'record';
        }

        if (in_array($normalized, self::SYSTEM_TYPES, true)) {
            return 'system';
        }

        return 'unknown';
    }

    public static function isActionable(Activity $activity): bool
    {
        if ($activity->is_done) {
            return false;
        }

        return self::category((string) $activity->type) === 'actionable';
    }
}
