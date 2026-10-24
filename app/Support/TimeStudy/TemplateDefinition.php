<?php

namespace App\Support\TimeStudy;

/**
 * The shape of a template version's `definition` JSON: defaults, presets, normalisation of
 * whatever the builder sends, and the list of problems that stop a version being published.
 * Pure: no database. Rules are a fixed menu on purpose (no free-form expressions).
 */
class TemplateDefinition
{
    public const SEVERITIES = ['block', 'warn', 'off'];
    public const FIRST_LAST = ['ask', 'first', 'last', 'both', 'none'];
    public const APPROVALS = ['always', 'warnings', 'never'];
    public const FIELD_TYPES = ['text', 'number', 'yesno', 'choice'];
    public const PRESETS = ['standard', 'strict', 'explore'];

    /** Evidence floor for anything that feeds the skill matrix. */
    public const MATRIX_MIN_LAPS = 3;
    public const MAX_FIELDS = 20;

    public static function defaults(string $purpose, string $preset = 'standard'): array
    {
        $rules = match ($preset) {
            'strict' => [
                'min_laps' => ['value' => 12, 'severity' => 'block'],
                'outlier' => ['pct' => 25, 'severity' => 'block'],
                'variation' => ['pct' => 15, 'severity' => 'block'],
                'down_time' => ['pct' => 10, 'severity' => 'warn'],
                'first_last' => 'both',
            ],
            'explore' => [
                'min_laps' => ['value' => 3, 'severity' => 'warn'],
                'outlier' => ['pct' => 40, 'severity' => 'off'],
                'variation' => ['pct' => 30, 'severity' => 'off'],
                'down_time' => ['pct' => 25, 'severity' => 'off'],
                'first_last' => 'ask',
            ],
            default => [
                'min_laps' => ['value' => 8, 'severity' => 'warn'],
                'outlier' => ['pct' => 30, 'severity' => 'warn'],
                'variation' => ['pct' => 20, 'severity' => 'warn'],
                'down_time' => ['pct' => 15, 'severity' => 'warn'],
                'first_last' => 'ask',
            ],
        };

        $definition = [
            'manual_only' => false,
            'rules' => $rules,
            'fields' => [],
            'counts_in_matrix' => $purpose === 'perf' && $preset !== 'explore',
            'approval' => $preset === 'strict' ? 'always' : 'warnings',
        ];

        if ($purpose === 'smv') {
            $definition['rules']['min_operators'] = ['value' => 3, 'severity' => 'block'];
            $definition['rules']['rating_required'] = true;
            $definition['allowance_pct'] = 14;
            $definition['counts_in_matrix'] = false;
            $definition['approval'] = 'always';
        }

        return $definition;
    }

    /**
     * Fills gaps with the standard defaults, coerces types, drops unknown keys and purpose-specific
     * rules that do not apply, and gives every custom field a stable key. It never rejects:
     * problems are reported by issues() so a half-finished draft can still be saved.
     */
    public static function normalize(array $raw, string $purpose): array
    {
        $base = self::defaults($purpose);
        $rawRules = is_array($raw['rules'] ?? null) ? $raw['rules'] : [];

        $rules = [];
        foreach (['min_laps' => 'value', 'outlier' => 'pct', 'variation' => 'pct', 'down_time' => 'pct'] as $rule => $limitKey) {
            $given = is_array($rawRules[$rule] ?? null) ? $rawRules[$rule] : [];
            $rules[$rule] = [
                $limitKey => self::number($given[$limitKey] ?? null, $base['rules'][$rule][$limitKey]),
                'severity' => in_array($given['severity'] ?? null, self::SEVERITIES, true) ? $given['severity'] : $base['rules'][$rule]['severity'],
            ];
        }
        $rules['first_last'] = in_array($rawRules['first_last'] ?? null, self::FIRST_LAST, true) ? $rawRules['first_last'] : 'ask';

        $definition = [
            // Scope switch lives in the version so a draft never changes live behaviour until published.
            'manual_only' => (bool) ($raw['manual_only'] ?? false),
            'rules' => $rules,
            'fields' => self::normalizeFields($raw['fields'] ?? []),
            'counts_in_matrix' => $purpose === 'perf' && (bool) ($raw['counts_in_matrix'] ?? $base['counts_in_matrix']),
            'approval' => in_array($raw['approval'] ?? null, self::APPROVALS, true) ? $raw['approval'] : $base['approval'],
        ];

        if ($purpose === 'smv') {
            $given = is_array($rawRules['min_operators'] ?? null) ? $rawRules['min_operators'] : [];
            $definition['rules']['min_operators'] = [
                'value' => self::number($given['value'] ?? null, 3),
                'severity' => in_array($given['severity'] ?? null, self::SEVERITIES, true) ? $given['severity'] : 'block',
            ];
            $definition['rules']['rating_required'] = (bool) ($rawRules['rating_required'] ?? true);
            $definition['allowance_pct'] = self::number($raw['allowance_pct'] ?? null, 14);
        }

        return $definition;
    }

    /** @return string[] plain-language reasons this definition cannot be published */
    public static function issues(array $definition, string $purpose): array
    {
        $issues = [];
        $rules = $definition['rules'] ?? [];

        $limits = [
            ['min_laps', 'value', 'Minimum laps', 1, 100],
            ['outlier', 'pct', 'The unusual-lap limit', 5, 200],
            ['variation', 'pct', 'The lap-time variation limit', 5, 100],
            ['down_time', 'pct', 'The down-time limit', 1, 100],
        ];
        foreach ($limits as [$rule, $key, $label, $min, $max]) {
            $value = $rules[$rule][$key] ?? null;
            if (!is_numeric($value) || $value < $min || $value > $max) {
                $issues[] = "$label must be between $min and $max.";
            }
        }

        if ($purpose === 'perf' && !empty($definition['counts_in_matrix'])) {
            $minLaps = $rules['min_laps'] ?? [];
            if (($minLaps['value'] ?? 0) < self::MATRIX_MIN_LAPS || ($minLaps['severity'] ?? 'off') === 'off') {
                $issues[] = 'Minimum laps must be at least ' . self::MATRIX_MIN_LAPS . ' and not switched off while the template counts towards the skill matrix.';
            }
        }

        if ($purpose === 'smv') {
            $operators = $rules['min_operators']['value'] ?? null;
            if (!is_numeric($operators) || $operators < 1 || $operators > 20) {
                $issues[] = 'The number of operators to time must be between 1 and 20.';
            }
            $allowance = $definition['allowance_pct'] ?? null;
            if (!is_numeric($allowance) || $allowance < 0 || $allowance > 60) {
                $issues[] = 'The allowance must be between 0 and 60 percent.';
            }
        }

        $fields = $definition['fields'] ?? [];
        if (count($fields) > self::MAX_FIELDS) {
            $issues[] = 'A template can ask at most ' . self::MAX_FIELDS . ' questions.';
        }
        $seen = [];
        foreach ($fields as $field) {
            $label = trim((string) ($field['label'] ?? ''));
            if ($label === '' || mb_strlen($label) > 60) {
                $issues[] = 'Every question needs a name of up to 60 characters.';
                continue;
            }
            if (isset($seen[mb_strtolower($label)])) {
                $issues[] = "The question “{$label}” appears more than once.";
            }
            $seen[mb_strtolower($label)] = true;
            if (($field['type'] ?? null) === 'choice' && count($field['options'] ?? []) === 0) {
                $issues[] = "The question “{$label}” needs at least one choice.";
            }
        }

        return array_values(array_unique($issues));
    }

    private static function normalizeFields($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $fields = [];
        $usedKeys = [];
        foreach (array_values($raw) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ''));
            $type = in_array($item['type'] ?? null, self::FIELD_TYPES, true) ? $item['type'] : 'text';
            $options = [];
            if ($type === 'choice') {
                $source = $item['options'] ?? [];
                $source = is_string($source) ? explode(',', $source) : (is_array($source) ? $source : []);
                $options = array_values(array_unique(array_filter(array_map(fn ($o) => trim((string) $o), $source), fn ($o) => $o !== '')));
            }

            $key = (string) ($item['key'] ?? '');
            if (!preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key) || isset($usedKeys[$key])) {
                $key = self::uniqueKey($label, $usedKeys);
            }
            $usedKeys[$key] = true;

            $fields[] = ['key' => $key, 'label' => $label, 'type' => $type, 'required' => (bool) ($item['required'] ?? false), 'options' => $options];
        }

        return $fields;
    }

    private static function uniqueKey(string $label, array $usedKeys): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $label));
        $slug = trim(substr($slug, 0, 30), '_');
        $slug = ($slug === '' || !ctype_alpha($slug[0])) ? 'field_' . $slug : $slug;
        $slug = rtrim($slug, '_');

        $key = $slug;
        for ($i = 2; isset($usedKeys[$key]); $i++) {
            $key = $slug . '_' . $i;
        }

        return $key;
    }

    private static function number($value, $fallback)
    {
        return is_numeric($value) ? $value + 0 : $fallback;
    }
}
