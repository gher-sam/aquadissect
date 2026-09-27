<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\SpeciesAssignment;
use App\Models\ClassModuleAssignment;
use App\Support\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['from' => 'required|date', 'to' => 'required|date|after:from']);
        $from = Carbon::parse($request->from)->utc();
        $to = Carbon::parse($request->to)->utc();
        abort_if($from->diffInDays($to) > 370, 422, 'Choose a period of at most one year.');
        $classes = WorkspaceAccess::classes($request->user())->get(['id', 'name', 'status']);
        $events = CalendarEvent::whereIn('class_id', $classes->pluck('id'))->where('starts_at', '<', $to)->where('ends_at', '>', $from)->get()->map(fn ($event) => [
            'id' => $event->id, 'kind' => 'event', 'title' => $event->title, 'description' => $event->description,
            'location' => $event->location, 'classId' => $event->class_id, 'startsAt' => $event->starts_at->toIso8601String(),
            'endsAt' => $event->ends_at->toIso8601String(), 'cancelled' => (bool) $event->cancelled_at,
        ])->all();
        foreach ([SpeciesAssignment::class => 'species', ClassModuleAssignment::class => 'safety'] as $model => $kind) {
            $query = $model::whereIn('class_id', $classes->pluck('id'))->where('active', true);
            if ($request->user()->role === 'student') $query->whereHas($kind === 'species' ? 'package' : 'version', fn ($q) => $q->where('status', 'published'));
            foreach ($query->get() as $assignment) {
                foreach (['starts_at' => 'Opens', 'due_at' => 'Due'] as $field => $label) {
                    $date = $assignment->$field;
                    if (!$date || $date->lt($from) || $date->gte($to)) continue;
                    $events[] = ['id' => "$kind-{$assignment->id}-$field", 'kind' => $kind, 'classId' => $assignment->class_id,
                        'title' => $label.': '.($kind === 'species' ? $assignment->species?->common_name : $assignment->module?->title),
                        'startsAt' => $date->toIso8601String(), 'endsAt' => $date->toIso8601String(), 'cancelled' => false];
                }
            }
        }
        return response()->json(['events' => $events, 'classes' => $classes]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        WorkspaceAccess::manage($request->user(), $data['class_id']);
        $event = DB::transaction(fn () => CalendarEvent::create($data + ['created_by' => $request->user()->id]));
        return response()->json(['id' => $event->id], 201);
    }

    public function update(Request $request, CalendarEvent $event)
    {
        WorkspaceAccess::manage($request->user(), $event->class_id);
        abort_if($event->cancelled_at, 422, 'Cancelled events cannot be edited.');
        $data = $this->validated($request);
        abort_unless((int) $data['class_id'] === $event->class_id, 422, 'An event must stay in its original section.');
        DB::transaction(fn () => $event->update($data));
        return response()->json(['message' => 'Event updated.']);
    }

    public function cancel(Request $request, CalendarEvent $event)
    {
        WorkspaceAccess::manage($request->user(), $event->class_id);
        if (!$event->cancelled_at) DB::transaction(fn () => $event->update(['cancelled_at' => now()]));
        return response()->json(['message' => 'Event cancelled.']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate(['class_id' => 'required|integer|exists:classes,id', 'title' => 'required|string|max:160',
            'description' => 'nullable|string|max:3000', 'location' => 'nullable|string|max:255',
            'starts_at' => 'required|date_format:Y-m-d\TH:i:sP', 'ends_at' => 'required|date_format:Y-m-d\TH:i:sP|after:starts_at']);
        $data['starts_at'] = Carbon::parse($data['starts_at'])->utc();
        $data['ends_at'] = Carbon::parse($data['ends_at'])->utc();
        return $data;
    }
}
