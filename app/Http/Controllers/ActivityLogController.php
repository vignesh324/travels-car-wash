<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use App\Http\Traits\AjaxDataTableTrait;

class ActivityLogController extends Controller
{
    use AjaxDataTableTrait;

    public function getData(Request $request)
    {
        $query = Activity::with('causer:id,name')
            ->when($request->log_name,     fn($q, $v) => $q->where('log_name', $v))
            ->when($request->causer_id,    fn($q, $v) => $q->where('causer_id', $v))
            ->when($request->subject_type, fn($q, $v) => $q->where('subject_type', 'like', "%{$v}%"))
            ->when($request->date_from,    fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->date_to,      fn($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('created_at');

        return $this->dataTableOf($query);
    }

    public function index()
    {
        return view('activity-logs.index');
    }
}
