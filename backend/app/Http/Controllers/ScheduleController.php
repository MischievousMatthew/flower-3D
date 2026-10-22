<?php

namespace App\Http\Controllers;

use App\Models\ShiftTemplate;
use App\Models\StaffShift;
use App\Services\Schedules\ShiftPlanner;
use App\Traits\ScopesOwner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The shift planner: ERP → HR → Schedules, and the vendor portal's Shift planner.
 * Both read and change the same shifts.
 */
class ScheduleController extends Controller
{
    use ScopesOwner;

    private const TIME = 'regex:/^([01]\d|2[0-3]):[0-5]\d$/';
    private const CONFIRMS = ['move', 'allow_rest_day', 'off_day'];

    public function __construct(private ShiftPlanner $planner) {}

    /** GET board?from&to */
    public function board(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => 'required|date', 'to' => 'required|date|after_or_equal:from']);
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->startOfDay();
        if ($from->diffInDays($to) > 42) {
            $to = $from->copy()->addDays(42);
        }

        return response()->json(['success' => true, 'data' => $this->planner->board($this->getOwnerId(), $from, $to)]);
    }

    /** POST templates */
    public function storeTemplate(Request $request): JsonResponse
    {
        $template = $this->planner->saveTemplate($this->getOwnerId(), $this->validateTemplate($request), null, false);

        return response()->json(['success' => true, 'message' => "{$template->name} created. Now link people to it.", 'data' => $this->planner->formatTemplate($template)], 201);
    }

    /** PUT templates/{id} */
    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $template = ShiftTemplate::where('owner_id', $this->getOwnerId())->whereNull('archived_at')->findOrFail($id);
        $template = $this->planner->saveTemplate($this->getOwnerId(), $this->validateTemplate($request), $template, $request->boolean('update_future'));

        return response()->json(['success' => true, 'message' => 'Shift saved.', 'data' => $this->planner->formatTemplate($template)]);
    }

    /** DELETE templates/{id} */
    public function destroyTemplate(int $id): JsonResponse
    {
        $cleared = $this->planner->archiveTemplate($this->getOwnerId(), $id);

        return response()->json(['success' => true, 'message' => $cleared
            ? "Shift removed. {$cleared} upcoming " . ($cleared === 1 ? 'day was' : 'days were') . ' cleared; those people are back on their usual hours.'
            : 'Shift removed.']);
    }

    /** POST templates/{id}/link {employee_ids[], from, to} */
    public function link(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $data = $request->validate([
            'employee_ids' => 'required|array|min:1|max:100',
            'employee_ids.*' => ['integer', Rule::exists('employees_info', 'id')->where('owner_id', $ownerId)->whereNull('deleted_at')],
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ], ['employee_ids.required' => 'Choose at least one person.']);
        $result = $this->planner->link($ownerId, $id, $data['employee_ids'], Carbon::parse($data['from']), Carbon::parse($data['to']), $request->user());

        return response()->json([
            'success' => true,
            'message' => $result['added']
                ? "Scheduled {$result['added']} " . ($result['added'] === 1 ? 'day' : 'days') . '.' . ($result['skipped'] ? ' Some days were skipped.' : '')
                : 'Nothing new to schedule.' . ($result['skipped'] ? ' Some days were skipped.' : ''),
            'data' => $result,
        ]);
    }

    /** POST assign {employee_id, date, template_id | start,end,label | day_off, confirm[]} */
    public function assign(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $data = $request->validate([
            'employee_id' => 'required|integer',
            'date' => 'required|date',
            'template_id' => 'nullable|integer',
            'start' => ['nullable', self::TIME],
            'end' => ['nullable', self::TIME],
            'label' => 'nullable|string|max:40',
            'day_off' => 'boolean',
            'confirm' => 'array',
            'confirm.*' => Rule::in(self::CONFIRMS),
        ]);
        $shift = $this->planner->assign($ownerId, $data['employee_id'], $data['date'], $data, $data['confirm'] ?? [], $request->user());

        return response()->json(['success' => true, 'message' => $shift->is_off ? 'Day off set.' : 'Scheduled.', 'data' => ['id' => $shift->id]]);
    }

    /** POST shifts/{id}/replace {employee_id, reason, note, confirm[]} */
    public function replace(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|integer',
            'reason' => ['required', Rule::in(StaffShift::REPLACE_REASONS)],
            'note' => 'nullable|string|max:255',
            'confirm' => 'array',
            'confirm.*' => Rule::in(self::CONFIRMS),
        ]);
        $result = $this->planner->replace($this->getOwnerId(), $id, $data['employee_id'], $data['reason'], $data['note'] ?? null, $data['confirm'] ?? [], $request->user());

        return response()->json(['success' => true, 'message' => $result['message']]);
    }

    /** POST shifts/{id}/undo-replace */
    public function undoReplace(int $id): JsonResponse
    {
        $this->planner->undoReplace($this->getOwnerId(), $id);

        return response()->json(['success' => true, 'message' => 'Back on the original person.']);
    }

    /** DELETE shifts/{id} */
    public function remove(int $id): JsonResponse
    {
        $this->planner->remove($this->getOwnerId(), $id);

        return response()->json(['success' => true, 'message' => 'Removed. They\'re back on their usual hours that day.']);
    }

    /** POST copy-week {week} */
    public function copyWeek(Request $request): JsonResponse
    {
        $data = $request->validate(['week' => 'required|date']);
        $result = $this->planner->copyWeek($this->getOwnerId(), Carbon::parse($data['week']), $request->user());

        return response()->json([
            'success' => true,
            'message' => $result['copied'] ? "Copied {$result['copied']} " . ($result['copied'] === 1 ? 'day' : 'days') . ' from last week.' : 'Nothing new to copy from last week.',
            'data' => $result,
        ]);
    }

    private function validateTemplate(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:60',
            'start_time' => ['required', self::TIME],
            'end_time' => ['required', self::TIME],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'headcount' => 'required|integer|min:1|max:50',
            'days' => 'required|array|min:1',
            'days.*' => Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']),
            'note' => 'nullable|string|max:255',
        ], ['days.required' => 'Pick the days this shift runs.', 'name.required' => 'Name the shift, like "Morning counter".']);
        if ($data['end_time'] <= $data['start_time']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['end_time' => 'The shift must end after it starts (overnight shifts aren\'t supported yet).']);
        }

        return $data;
    }
}
