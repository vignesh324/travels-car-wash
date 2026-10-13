<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        if ($request->boolean('datatable')) {
            $query = Activity::with('causer:id,name')
                ->when($request->log_name, fn($q, $v) => $q->where('log_name', $v))
                ->when($request->causer_id, fn($q, $v) => $q->where('causer_id', $v))
                ->when($request->subject_type, fn($q, $v) => $q->where('subject_type', 'like', "%{$v}%"))
                ->when($request->date_from, fn($q, $v) => $q->whereDate('created_at', '>=', $v))
                ->when($request->date_to, fn($q, $v) => $q->whereDate('created_at', '<=', $v))
                ->orderByDesc('created_at');

            return DataTables::of($query)->make(true);
        }

        return response()->json([]);
    }
}
