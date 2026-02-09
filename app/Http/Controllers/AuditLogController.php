<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::query()->orderByDesc('id');

        if ($request->filled('user')) {
            $user = $request->string('user');
            $query->where(function($q) use ($user) {
                $q->where('user_name', 'like', "%{$user}%")
                  ->orWhere('user_email', 'like', "%{$user}%");
            });
        }
        if ($request->filled('action')) {
            $query->where('action', 'like', "%{$request->string('action')}%");
        }
        if ($request->filled('method')) {
            $query->where('method', $request->string('method'));
        }
        if ($request->filled('route')) {
            $query->where('route', 'like', "%{$request->string('route')}%");
        }
        if ($request->filled('ip')) {
            $query->where('ip_address', $request->string('ip'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        $logs = $query->paginate(25)->appends($request->query());
        return view('admin.audit.index', compact('logs'));
    }
}
