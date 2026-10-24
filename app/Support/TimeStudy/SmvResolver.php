<?php

namespace App\Support\TimeStudy;

use Illuminate\Support\Facades\DB;

/**
 * Decides which SMV a study is measured against, so the server — not the browser — owns the
 * number efficiency is computed from. Same precedence the study screen shows: the style's own
 * override for the operation, otherwise the operation's default SMV.
 */
class SmvResolver
{
    /** @return array{smv: ?float, source: ?string} */
    public static function resolve(int $operationId, ?int $styleId): array
    {
        if ($styleId !== null) {
            $override = DB::table('style_operations')
                ->where('style_id', $styleId)
                ->where('operation_id', $operationId)
                ->value('smv');
            if ($override !== null && $override !== '') {
                return ['smv' => (float) $override, 'source' => 'style_override'];
            }
        }

        $default = DB::table('operations')->where('id', $operationId)->value('smv');
        if ($default !== null && $default !== '') {
            return ['smv' => (float) $default, 'source' => 'operation'];
        }

        return ['smv' => null, 'source' => null];
    }
}
