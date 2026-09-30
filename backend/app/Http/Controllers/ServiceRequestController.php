<?php

namespace App\Http\Controllers;

use App\Contracts\MalwareScanner;
use App\Models\Attachment;
use App\Models\ServiceRequest;
use App\Services\Requests\RequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServiceRequestController extends Controller
{
    public function index(Request $r)
    {
        $d = $r->validate(['kind' => 'sometimes|in:application,complaint', 'page' => 'sometimes|integer|min:1']);
        $q = ServiceRequest::where('user_id', $r->user()->id)->latest();
        if (isset($d['kind'])) {
            $q->where('kind', $d['kind']);
        }

return $this->paginate($q->paginate(10));
    }

    public function store(Request $r, RequestService $s)
    {
        return response()->json(['data' => $s->create($r->user(), $this->validated($r))], 201);
    }

    public function show(ServiceRequest $serviceRequest)
    {
        Gate::authorize('view', $serviceRequest);

        return response()->json(['data' => ['request' => $serviceRequest, 'events' => DB::table('service_request_events')->where('service_request_id', $serviceRequest->id)->orderBy('id')->get(), 'attachments' => Attachment::where('service_request_id', $serviceRequest->id)->get()]]);
    }

    public function update(Request $r, ServiceRequest $serviceRequest)
    {
        Gate::authorize('update', $serviceRequest);
        $d = $this->validated($r, false);
        unset($d['kind'],$d['external_account_id'],$d['external_bill_id']);

        return DB::transaction(function () use ($serviceRequest, $d) {
            $s = ServiceRequest::whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $s);
            $s->update($d);

            return response()->json(['data' => $s]);
        });
    }

    public function submit(Request $r, ServiceRequest $serviceRequest, RequestService $s)
    {
        return response()->json(['data' => $s->submit($r->user(), $serviceRequest)]);
    }

    public function reply(Request $r, ServiceRequest $serviceRequest, RequestService $s)
    {
        Gate::authorize('view', $serviceRequest);
        abort_if(in_array($serviceRequest->status, ['draft', 'closed', 'rejected', 'approved']), 409);
        $d = $r->validate(['message' => 'required|string|min:1|max:5000']);
        $s->event($serviceRequest, $r->user(), $d['message']);

        return response()->json(['data' => null], 201);
    }

    public function review(Request $r, ServiceRequest $serviceRequest, RequestService $s)
    {
        $d = $r->validate(['status' => 'required|string', 'message' => 'required|string|min:3|max:5000']);

        return response()->json(['data' => $s->review($r->user(), $serviceRequest, $d['status'], $d['message'])]);
    }

    public function upload(Request $r, ServiceRequest $serviceRequest, MalwareScanner $scanner)
    {
        Gate::authorize('update', $serviceRequest);
        $r->validate(['file' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png|mimetypes:application/pdf,image/jpeg,image/png']);
        abort_if(Attachment::where('service_request_id', $serviceRequest->id)->count() >= 5, 422);
        $file = $r->file('file');
        $scanner->scan($file->getRealPath());
        $id = (string) Str::uuid();
        $path = $file->storeAs('private-requests/'.$serviceRequest->id, $id, 'local');
        $attachment = Attachment::create(['id' => $id, 'service_request_id' => $serviceRequest->id, 'path' => $path, 'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 150), 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'scan_status' => app()->environment(['local', 'testing']) ? 'development_checked' : 'clean', 'created_at' => now()]);

        return response()->json(['data' => $attachment], 201);
    }

    public function download(Attachment $attachment)
    {
        $s = ServiceRequest::findOrFail($attachment->service_request_id);
        Gate::authorize('view', $s);

        return Storage::disk('local')->download($attachment->path, 'attachment-'.$attachment->id.'.'.match ($attachment->mime) {
            'application/pdf' => 'pdf','image/png' => 'png',default => 'jpg'
        }, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function validated(Request $r, bool $create = true): array
    {
        return $r->validate(['kind' => ($create ? 'required' : 'sometimes').'|in:application,complaint', 'subject' => 'required|string|max:180', 'description' => 'nullable|string|max:5000', 'category' => 'nullable|in:billing,meter,water_supply,profile_correction,other', 'external_account_id' => 'nullable|string|max:128', 'external_bill_id' => 'nullable|string|max:128', 'form_data' => 'nullable|array:applicant_name,guardian_name,contact_phone,contact_email,service_address,ward,holding_number,connection_type,pipe_size,notes', 'form_data.*' => 'nullable|string|max:1000']);
    }

    private function paginate($p)
    {
        return response()->json(['data' => $p->items(), 'meta' => ['pagination' => ['page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total(), 'has_next' => $p->hasMorePages()]]]);
    }
}
