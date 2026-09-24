<?php
namespace App\Http\Controllers;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;
class AuditLogController extends Controller
{
    public function index(Request $r): View { $logs=AuditLog::with('user:id,name')->when($r->event,fn($q,$v)=>$q->where('event',$v))->when($r->user_id,fn($q,$v)=>$q->where('user_id',$v))->latest('created_at')->paginate(50)->withQueryString();return view('audit.index',compact('logs')); }
}
