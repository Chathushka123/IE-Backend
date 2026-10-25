<?php

namespace App\Http\Repositories;

use App\Exceptions\GeneralException;
use App\Support\TimeStudy\OperationCatalog;
use App\Support\TimeStudy\StudyTypes;
use App\Support\TimeStudy\TemplateDefinition;
use App\Support\TimeStudy\TemplateMatcher;
use App\TimeStudyTemplate;
use App\TimeStudyTemplateVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Templates, their versions and their scope.
 *
 * Lifecycle: a template has at most one draft version. Editing a published template saves a new
 * draft cloned from its latest published version; publishing freezes the draft as the next
 * version number. Published versions are never changed, so every study keeps the rules it ran
 * under.
 */
class TimeStudyTemplateRepository
{
    private const CONDITION_FIELDS = ['operation', 'base_operation', 'base_operation_category', 'machine_type', 'machine_category', 'product_category', 'product_group', 'study_type'];

    // ---------------------------------------------------------------
    // Reading
    // ---------------------------------------------------------------

    public static function search(array $filters = []): array
    {
        $templates = TimeStudyTemplate::query()
            ->when(empty($filters['include_archived']), fn ($q) => $q->whereNull('archived_at'))
            ->when(!empty($filters['purpose']), fn ($q) => $q->where('purpose', $filters['purpose']))
            ->when(!empty($filters['q']), fn ($q) => $q->where('name', 'like', '%' . $filters['q'] . '%'))
            ->orderBy('name')
            ->get();

        if ($templates->isEmpty()) {
            return [];
        }

        $versions = TimeStudyTemplateVersion::with('conditions')
            ->whereIn('time_study_template_id', $templates->pluck('id'))
            ->get()
            ->groupBy('time_study_template_id');

        $studyCounts = TimeStudyDashboardRepository::baseQuery([])
            ->join('time_study_template_versions as v', 'time_studies.template_version_id', '=', 'v.id')
            ->groupBy('v.time_study_template_id')
            ->selectRaw('v.time_study_template_id as template_id, COUNT(*) as studies')
            ->pluck('studies', 'template_id');

        $rows = [];
        foreach ($templates as $template) {
            [$published, $draft] = self::splitVersions($versions->get($template->id, collect()));
            $shown = $published ?? $draft;

            $rows[] = [
                'id' => $template->id,
                'name' => $template->name,
                'system_key' => $template->system_key,
                'purpose' => $template->purpose,
                'manual_only' => $template->manual_only,
                'archived_at' => $template->archived_at,
                'status' => $published ? 'published' : 'draft',
                'published_version_no' => $published?->version_no,
                'has_draft' => $draft !== null && $published !== null,
                'counts_in_matrix' => (bool) ($shown?->definition['counts_in_matrix'] ?? false),
                'studies_count' => (int) ($studyCounts[$template->id] ?? 0),
                'scope' => $shown ? OperationCatalog::labelConditions(self::conditionRows($shown)) : [],
                'updated_at' => $template->updated_at,
            ];
        }

        return $rows;
    }

    public static function find(int $id): array
    {
        $template = TimeStudyTemplate::findOrFail($id);
        $versions = TimeStudyTemplateVersion::with('conditions')->where('time_study_template_id', $id)->orderByDesc('id')->get();
        [$published, $draft] = self::splitVersions($versions);

        return [
            'id' => $template->id,
            'name' => $template->name,
            'system_key' => $template->system_key,
            'purpose' => $template->purpose,
            'manual_only' => $template->manual_only,
            'archived_at' => $template->archived_at,
            'editable' => $template->system_key === null && $template->archived_at === null,
            'published' => $published ? self::presentVersion($published) : null,
            'draft' => $draft ? self::presentVersion($draft) : null,
            'versions' => $versions->where('status', 'published')->sortByDesc('version_no')->values()->map(fn ($v) => [
                'id' => $v->id,
                'version_no' => $v->version_no,
                'published_at' => $v->published_at,
                'published_by_id' => $v->published_by_id,
            ])->all(),
            'updated_at' => $template->updated_at,
        ];
    }

    // ---------------------------------------------------------------
    // Writing
    // ---------------------------------------------------------------

    public static function create(array $rec): array
    {
        self::validatePayload($rec, [
            'name' => ['required', 'string', 'max:150', Rule::unique('time_study_templates', 'name')],
            'purpose' => ['required', Rule::in(['perf', 'smv'])],
            'preset' => ['nullable', Rule::in(TemplateDefinition::PRESETS)],
        ]);
        $conditions = self::validConditions($rec['conditions'] ?? []);

        $definition = isset($rec['definition'])
            ? TemplateDefinition::normalize($rec['definition'], $rec['purpose'])
            : TemplateDefinition::defaults($rec['purpose'], $rec['preset'] ?? 'standard');
        $definition['manual_only'] = (bool) ($rec['manual_only'] ?? $definition['manual_only']);

        return DB::transaction(function () use ($rec, $conditions, $definition) {
            $template = TimeStudyTemplate::create([
                'name' => trim($rec['name']),
                'purpose' => $rec['purpose'],
                'manual_only' => $definition['manual_only'],
            ]);
            self::createDraft($template, $definition, $conditions);

            return self::find($template->id);
        });
    }

    public static function saveDraft(int $id, array $rec): array
    {
        $template = self::editable($id);
        self::validatePayload($rec, [
            'name' => ['sometimes', 'required', 'string', 'max:150', Rule::unique('time_study_templates', 'name')->ignore($id)],
            'purpose' => ['sometimes', Rule::in(['perf', 'smv'])],
            'preset' => ['nullable', Rule::in(TemplateDefinition::PRESETS)],
        ]);

        $purpose = $rec['purpose'] ?? $template->purpose;
        if ($purpose !== $template->purpose && self::hasPublishedVersion($template)) {
            throw new GeneralException('The purpose of a template cannot change once it has been published. Create a new template instead.');
        }

        return DB::transaction(function () use ($template, $rec, $purpose) {
            $draft = self::draftOf($template);
            $current = $draft ?? self::latestPublished($template);
            $definition = TemplateDefinition::normalize($rec['definition'] ?? $current?->definition ?? [], $purpose);
            if (!empty($rec['preset'])) {
                // A preset replaces the rules, approval and matrix switch but keeps the custom questions and scope switch.
                $definition = array_merge(TemplateDefinition::defaults($purpose, $rec['preset']), [
                    'fields' => $definition['fields'],
                    'manual_only' => $definition['manual_only'],
                ]);
            }
            if (array_key_exists('manual_only', $rec)) {
                $definition['manual_only'] = (bool) $rec['manual_only'];
            }
            $conditions = array_key_exists('conditions', $rec)
                ? self::validConditions($rec['conditions'])
                : self::conditionRows($current);

            // Name and purpose are not part of the protocol, so they take effect straight away.
            // manual_only stays in the draft and reaches the live template only when published.
            $template->fill([
                'name' => isset($rec['name']) ? trim($rec['name']) : $template->name,
                'purpose' => $purpose,
            ])->save();

            if ($draft) {
                $draft->update(['definition' => $definition]);
                $draft->conditions()->delete();
                self::insertConditions($draft, $conditions);
            } else {
                self::createDraft($template, $definition, $conditions);
            }

            return self::find($template->id);
        });
    }

    public static function discardDraft(int $id): array
    {
        $template = self::editable($id);
        $draft = self::draftOf($template);
        if (!$draft) {
            throw new GeneralException('There are no unpublished changes to discard.');
        }
        if (!self::hasPublishedVersion($template)) {
            throw new GeneralException('A template that has never been published has nothing to go back to. Archive it instead.');
        }

        DB::transaction(function () use ($template, $draft) {
            $draft->conditions()->delete();
            $draft->delete();
            $template->touch();
        });

        return self::find($id);
    }

    public static function publish(int $id): array
    {
        return DB::transaction(function () use ($id) {
            // Lock the template row so two publishers cannot take the same version number.
            $template = TimeStudyTemplate::whereKey($id)->lockForUpdate()->firstOrFail();
            self::assertEditable($template);

            $draft = self::draftOf($template);
            if (!$draft) {
                throw new GeneralException('There are no changes to publish.');
            }

            $issues = self::issuesFor($template->name, $template->purpose, $draft->definition, $id);
            if ($issues) {
                throw new GeneralException('This template cannot be published yet: ' . implode(' ', $issues));
            }

            $nextNo = (int) TimeStudyTemplateVersion::where('time_study_template_id', $id)->max('version_no') + 1;
            $draft->update([
                'status' => 'published',
                'version_no' => $nextNo,
                'published_at' => now(),
                'published_by_id' => Auth::id(),
            ]);
            // The live template follows the version that was just published.
            $template->update(['manual_only' => (bool) ($draft->definition['manual_only'] ?? false)]);

            return self::find($id);
        });
    }

    public static function cloneTemplate(int $id): array
    {
        $source = TimeStudyTemplate::findOrFail($id);
        $version = self::draftOf($source) ?? self::latestPublished($source);
        if (!$version) {
            throw new GeneralException('There is nothing to copy yet.');
        }

        return DB::transaction(function () use ($source, $version) {
            $copy = TimeStudyTemplate::create([
                'name' => self::copyName($source->name),
                'purpose' => $source->purpose,
                'manual_only' => (bool) ($version->definition['manual_only'] ?? false),
            ]);
            self::createDraft($copy, $version->definition, self::conditionRows($version));

            return self::find($copy->id);
        });
    }

    public static function archive(int $id): void
    {
        $template = TimeStudyTemplate::findOrFail($id);
        if ($template->system_key !== null) {
            throw new GeneralException('This template is used by the system and cannot be archived.');
        }
        $template->update(['archived_at' => now()]);
    }

    // ---------------------------------------------------------------
    // Scope: preview and resolution
    // ---------------------------------------------------------------

    /**
     * What a draft would do if published, without saving anything: which operations it covers,
     * where it would win over existing templates, and what still blocks publishing. Uses the same
     * matcher as real resolution, so the builder cannot disagree with real behaviour.
     */
    public static function preview(array $rec): array
    {
        $purpose = in_array($rec['purpose'] ?? null, ['perf', 'smv'], true) ? $rec['purpose'] : 'perf';
        $selfId = isset($rec['template_id']) ? (int) $rec['template_id'] : null;
        $definition = TemplateDefinition::normalize($rec['definition'] ?? [], $purpose);
        $manualOnly = (bool) ($rec['manual_only'] ?? $definition['manual_only']);

        // A condition row the user is still filling in is left out of the match and flagged.
        $given = is_array($rec['conditions'] ?? null) ? $rec['conditions'] : [];
        $complete = array_values(array_filter($given, fn ($c) => !empty($c['ref_id'])));
        $issues = self::issuesFor($rec['name'] ?? '', $purpose, $definition, $selfId);
        if (count($complete) < count($given)) {
            $issues[] = 'Choose a value for every condition, or remove it.';
        }
        try {
            $conditions = self::validConditions($complete);
        } catch (GeneralException $e) {
            $conditions = [];
            $issues[] = 'A condition refers to something that no longer exists.';
        }

        // Which operations a template covers does not depend on the kind of study, so coverage is
        // judged on the operation conditions alone; the study types only decide who wins below.
        $operations = OperationCatalog::all();
        $operationConditions = self::withoutStudyType($conditions);
        $matched = $manualOnly ? [] : array_filter($operations, fn ($op) => TemplateMatcher::matches($operationConditions, $op['attributes']));

        $others = self::publishedCandidates($purpose, $selfId);
        $coveredTypes = self::studyTypesOf($conditions);
        $wins = 0;
        $losses = [];
        foreach ($matched as $op) {
            $lostTo = null;
            foreach ($coveredTypes as $typeId) {
                $attributes = $op['attributes'] + ['study_type' => $typeId];
                $rivals = array_filter($others, fn ($t) => TemplateMatcher::matches($t['conditions'], $attributes));
                $best = TemplateMatcher::rank(array_values($rivals))[0] ?? null;
                // A tie goes to the template published last, which this draft would be.
                if ($best !== null && TemplateMatcher::specificity($conditions) < TemplateMatcher::specificity($best['conditions'])) {
                    $lostTo = $best;
                    break;
                }
            }
            if ($lostTo === null) {
                $wins++;
            } else {
                $losses[$lostTo['id']] = ['template_id' => $lostTo['id'], 'name' => $lostTo['name'], 'count' => ($losses[$lostTo['id']]['count'] ?? 0) + 1];
            }
        }

        return [
            'issues' => array_values(array_unique($issues)),
            'scope' => [
                'manual_only' => $manualOnly,
                'total_operations' => count($operations),
                'matched_count' => count($matched),
                'wins' => $wins,
                'matched_operations' => array_map(fn ($op) => ['id' => $op['id'], 'label' => $op['label']], array_slice(array_values($matched), 0, 8)),
                'losses' => array_values($losses),
            ],
        ];
    }

    /** The template a study of this operation would use, every other one the analyst may pick, and the fallback when none applies. */
    public static function resolve(int $operationId, string $purpose, ?string $studyType = null): array
    {
        $operation = OperationCatalog::find($operationId);
        if ($operation === null) {
            throw new GeneralException('That operation could not be found.');
        }

        // Until the kind of study is known, only templates that do not name a study type can match.
        $attributes = $operation['attributes'] + ['study_type' => StudyTypes::idOf($studyType)];

        $all = self::publishedCandidates($purpose, null, true);
        $automatic = array_values(array_filter($all, fn ($t) => !$t['manual_only']));
        $candidates = TemplateMatcher::rank(array_values(array_filter($automatic, fn ($t) => TemplateMatcher::matches($t['conditions'], $attributes))));
        $chosen = $candidates[0] ?? null;

        $present = fn (array $t) => [
            'template_id' => $t['id'],
            'version_id' => $t['version_id'],
            'name' => $t['name'],
            'version_no' => $t['version_no'],
            'manual_only' => $t['manual_only'],
            'counts_in_matrix' => $t['counts_in_matrix'],
            // The study screen evaluates rules in the browser, so it needs the rules themselves.
            'definition' => $t['definition'],
        ];
        $legacy = collect($all)->firstWhere('system_key', TimeStudyTemplate::LEGACY_KEY);

        return [
            'operation' => ['id' => $operation['id'], 'label' => $operation['label']],
            'chosen' => $chosen ? $present($chosen) + [
                'matched_conditions' => OperationCatalog::labelConditions($chosen['conditions']),
                'candidate_count' => count($candidates),
            ] : null,
            'alternatives' => array_values(array_map($present, array_filter($all, fn ($t) => $t['id'] !== ($chosen['id'] ?? null) && $t['system_key'] === null))),
            // What a study runs under when no template applies: the server's own default, so the client never hardcodes it.
            'fallback' => $legacy ? $present($legacy) : null,
        ];
    }

    /**
     * What choosing this template up front pre-fills on the study screen: the operations and kinds
     * of study it covers (null operations = no limit) and the version and rules to run under.
     * Only a live, published, non-system template can be started from.
     */
    public static function startFrom(int $id): array
    {
        $template = TimeStudyTemplate::findOrFail($id);
        // Includes manual-only templates (the point of them); the Legacy fallback is not something to start from.
        $candidate = collect(self::publishedCandidates($template->purpose, null, true))->firstWhere('id', $template->id);
        if ($candidate === null || $candidate['system_key'] !== null) {
            throw new GeneralException('Only a published template can be used to start a study.');
        }

        // A manual-only template has no automatic scope, so it can be used for anything.
        $limited = !$candidate['manual_only'];
        $operations = $limited
            ? array_keys(array_filter(OperationCatalog::all(), fn ($op) => TemplateMatcher::matches(self::withoutStudyType($candidate['conditions']), $op['attributes'])))
            : null;
        $typeIds = $limited ? self::studyTypesOf($candidate['conditions']) : StudyTypes::all();

        return [
            'template_id' => $candidate['id'],
            'version_id' => $candidate['version_id'],
            'name' => $candidate['name'],
            'version_no' => $candidate['version_no'],
            'manual_only' => (bool) $candidate['manual_only'],
            'counts_in_matrix' => $candidate['counts_in_matrix'],
            'definition' => $candidate['definition'],
            'operation_ids' => $operations === null ? null : array_map('intval', $operations),
            // Production-floor studies start from a style, so only styles whose bulletin has one of the covered operations are useful.
            'style_ids' => $operations === null ? null : self::stylesWithOperations($operations),
            'study_types' => array_keys(array_filter(StudyTypes::IDS, fn ($typeId) => in_array($typeId, $typeIds, true))),
        ];
    }

    // ---------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------

    /** @return int[] ids of the styles whose active bulletin includes any of these operations */
    private static function stylesWithOperations(array $operationIds): array
    {
        if ($operationIds === []) {
            return [];
        }

        return DB::table('style_operations')
            ->where('is_active', true)
            ->whereIn('operation_id', $operationIds)
            ->distinct()
            ->pluck('style_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /** @return array<int,array{field:string,ref_id:int}> */
    private static function withoutStudyType(array $conditions): array
    {
        return array_values(array_filter($conditions, fn ($c) => $c['field'] !== 'study_type'));
    }

    /** The study type ids a template covers: the ones it names, or all of them when it names none. @return int[] */
    private static function studyTypesOf(array $conditions): array
    {
        $named = array_values(array_unique(array_map(fn ($c) => (int) $c['ref_id'], array_filter($conditions, fn ($c) => $c['field'] === 'study_type'))));

        return $named ?: StudyTypes::all();
    }

    /** Latest published version of every live template of this purpose, shaped for matching. */
    private static function publishedCandidates(string $purpose, ?int $excludeTemplateId, bool $includeSystem = false): array
    {
        $templates = TimeStudyTemplate::query()
            ->whereNull('archived_at')
            ->where('purpose', $purpose)
            ->when($excludeTemplateId, fn ($q) => $q->where('id', '!=', $excludeTemplateId))
            ->when(!$includeSystem, fn ($q) => $q->whereNull('system_key')->where('manual_only', false))
            ->get();

        $versions = TimeStudyTemplateVersion::with('conditions')
            ->where('status', 'published')
            ->whereIn('time_study_template_id', $templates->pluck('id'))
            ->orderBy('version_no')
            ->get()
            ->keyBy('time_study_template_id'); // later (higher) versions overwrite earlier ones

        $rows = [];
        foreach ($templates as $template) {
            $version = $versions->get($template->id);
            if (!$version) {
                continue;
            }
            $rows[] = [
                'id' => $template->id,
                'system_key' => $template->system_key,
                'name' => $template->name,
                'manual_only' => $template->manual_only,
                'version_id' => $version->id,
                'version_no' => $version->version_no,
                'published_at' => (string) $version->published_at,
                'conditions' => self::conditionRows($version),
                'counts_in_matrix' => (bool) ($version->definition['counts_in_matrix'] ?? false),
                'definition' => $version->definition,
            ];
        }

        return $rows;
    }

    /** @return string[] */
    private static function issuesFor(string $name, string $purpose, array $definition, ?int $selfId = null): array
    {
        $issues = [];
        if (trim($name) === '') {
            $issues[] = 'Give the template a name.';
        } elseif (TimeStudyTemplate::where('name', trim($name))->when($selfId, fn ($q) => $q->where('id', '!=', $selfId))->exists()) {
            $issues[] = 'Another template already uses this name.';
        }

        return array_merge($issues, TemplateDefinition::issues($definition, $purpose));
    }

    private static function validatePayload(array $rec, array $rules): void
    {
        $validator = Validator::make($rec, $rules + [
            'manual_only' => ['nullable', 'boolean'],
            'definition' => ['nullable', 'array'],
            'conditions' => ['nullable', 'array'],
        ]);
        if ($validator->fails()) {
            Utilities::extractError($validator);
        }
    }

    /**
     * @return array<int,array{field:string,ref_id:int}>
     */
    private static function validConditions($raw): array
    {
        if (!is_array($raw)) {
            throw new GeneralException('The conditions must be a list.');
        }

        $conditions = [];
        foreach ($raw as $condition) {
            $field = $condition['field'] ?? null;
            $refId = $condition['ref_id'] ?? null;
            if (!in_array($field, self::CONDITION_FIELDS, true)) {
                throw new GeneralException('Unknown condition field.');
            }
            if (!is_numeric($refId) || (int) $refId < 1) {
                throw new GeneralException('Choose a value for every condition, or remove it.');
            }
            if (!OperationCatalog::referenceExists($field, (int) $refId)) {
                throw new GeneralException('A condition refers to something that no longer exists.');
            }
            $conditions[$field . ':' . (int) $refId] = ['field' => $field, 'ref_id' => (int) $refId];
        }

        return array_values($conditions);
    }

    private static function createDraft(TimeStudyTemplate $template, array $definition, array $conditions): TimeStudyTemplateVersion
    {
        $draft = TimeStudyTemplateVersion::create([
            'time_study_template_id' => $template->id,
            'status' => 'draft',
            'definition' => $definition,
        ]);
        self::insertConditions($draft, $conditions);

        return $draft;
    }

    private static function insertConditions(TimeStudyTemplateVersion $version, array $conditions): void
    {
        foreach ($conditions as $condition) {
            $version->conditions()->create(['field' => $condition['field'], 'ref_id' => $condition['ref_id']]);
        }
    }

    private static function conditionRows(?TimeStudyTemplateVersion $version): array
    {
        if (!$version) {
            return [];
        }

        return $version->conditions->map(fn ($c) => ['field' => $c->field, 'ref_id' => (int) $c->ref_id])->values()->all();
    }

    private static function presentVersion(TimeStudyTemplateVersion $version): array
    {
        return [
            'id' => $version->id,
            'version_no' => $version->version_no,
            'status' => $version->status,
            'definition' => $version->definition,
            'conditions' => OperationCatalog::labelConditions(self::conditionRows($version)),
            'published_at' => $version->published_at,
        ];
    }

    /** @return array{0:?TimeStudyTemplateVersion,1:?TimeStudyTemplateVersion} latest published, draft */
    private static function splitVersions($versions): array
    {
        $published = $versions->where('status', 'published')->sortByDesc('version_no')->first();
        $draft = $versions->where('status', 'draft')->sortByDesc('id')->first();

        return [$published, $draft];
    }

    private static function draftOf(TimeStudyTemplate $template): ?TimeStudyTemplateVersion
    {
        return TimeStudyTemplateVersion::with('conditions')->where('time_study_template_id', $template->id)->where('status', 'draft')->orderByDesc('id')->first();
    }

    private static function latestPublished(TimeStudyTemplate $template): ?TimeStudyTemplateVersion
    {
        return TimeStudyTemplateVersion::with('conditions')->where('time_study_template_id', $template->id)->where('status', 'published')->orderByDesc('version_no')->first();
    }

    private static function hasPublishedVersion(TimeStudyTemplate $template): bool
    {
        return TimeStudyTemplateVersion::where('time_study_template_id', $template->id)->where('status', 'published')->exists();
    }

    private static function editable(int $id): TimeStudyTemplate
    {
        $template = TimeStudyTemplate::findOrFail($id);
        self::assertEditable($template);

        return $template;
    }

    private static function assertEditable(TimeStudyTemplate $template): void
    {
        if ($template->system_key !== null) {
            throw new GeneralException('This template is used by the system and cannot be edited. Make a copy to change it.');
        }
        if ($template->archived_at !== null) {
            throw new GeneralException('This template is archived and cannot be edited.');
        }
    }

    private static function copyName(string $name): string
    {
        $candidate = $name . ' (copy)';
        for ($i = 2; TimeStudyTemplate::where('name', $candidate)->exists(); $i++) {
            $candidate = $name . ' (copy ' . $i . ')';
        }

        return mb_substr($candidate, 0, 150);
    }
}
