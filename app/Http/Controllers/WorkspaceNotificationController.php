<?php

namespace App\Http\Controllers;

use App\Models\WorkspaceNotification;
use App\Support\{WorkspaceAccess, PortalPages};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkspaceNotificationController extends Controller
{
    private function query(Request $request)
    {
        $ids = WorkspaceAccess::classes($request->user())->pluck('id');
        return WorkspaceNotification::where('user_id', $request->user()->id)
            ->where(fn ($q) => $q->whereNull('class_id')->orWhereIn('class_id', $ids));
    }

    public function index(Request $request)
    {
        $query = $this->query($request);
        return response()->json(['unread' => (clone $query)->whereNull('read_at')->count(),
            'notifications' => $query->latest('id')->paginate(20)->through(fn ($n) => [
                'id' => $n->id, 'summary' => $n->summary, 'createdAt' => $n->created_at->toIso8601String(),
                'read' => (bool) $n->read_at, 'url' => PortalPages::urls()[$n->destination] ?? url('/dashboard'),
            ])]);
    }

    public function read(Request $request, int $notification)
    {
        $record = $this->query($request)->findOrFail($notification);
        if (!$record->read_at) DB::transaction(fn () => $record->update(['read_at' => now()]));
        return response()->json(['message' => 'Marked as read.']);
    }

    public function readAll(Request $request)
    {
        DB::transaction(function () use ($request) {
            $this->query($request)->whereNull('read_at')->eachById(fn ($record) => $record->update(['read_at' => now()]));
        });
        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
