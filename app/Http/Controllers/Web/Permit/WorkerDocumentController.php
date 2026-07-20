<?php

namespace App\Http\Controllers\Web\Permit;

use App\Enums\WorkerDocumentVerificationStatus;
use App\Http\Controllers\Web\BaseController;
use App\Models\Worker;
use App\Models\WorkerDocument;
use App\Models\WorkerDocumentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class WorkerDocumentController extends BaseController
{
    public function index(Worker $worker): RedirectResponse
    {
        return redirect()->route('tracking.workers.show', $worker);
    }

    public function store(Request $request, Worker $worker): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-worker-documents') ?? false, 403);

        $validated = $request->validate([
            'worker_document_type_id' => ['required', 'integer', Rule::exists('worker_document_types', 'id')->where('is_active', true)],
            'document_number' => ['nullable', 'string', 'max:255'],
            'issuing_body' => ['nullable', 'string', 'max:255'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:51200'],
        ]);

        $documentType = WorkerDocumentType::query()->findOrFail((int) $validated['worker_document_type_id']);

        /** @var UploadedFile|null $file */
        $file = $request->file('file');

        if ($documentType->requires_file && $file === null) {
            throw ValidationException::withMessages([
                'file' => ['A file attachment is required for this document type.'],
            ]);
        }

        $filePath = null;
        if ($file instanceof UploadedFile) {
            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $filePath = $file->storeAs(
                'worker-docs/'.$worker->id,
                (string) Str::uuid().'.'.$extension,
                'private',
            );
        }

        WorkerDocument::query()->create([
            'worker_id' => $worker->id,
            'worker_document_type_id' => $documentType->id,
            'document_number' => $validated['document_number'] ?? null,
            'issuing_body' => $validated['issuing_body'] ?? null,
            'issued_at' => $validated['issued_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'file_path' => $filePath,
            'verification_status' => WorkerDocumentVerificationStatus::Pending,
            'uploaded_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('tracking.workers.show', $worker)
            ->with('flash', ['success' => 'Document uploaded.']);
    }

    public function verify(Request $request, Worker $worker, WorkerDocument $document): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-worker-documents') ?? false, 403);
        abort_unless($document->worker_id === $worker->id, 404);

        $document->loadMissing('documentType');

        if ($document->documentType?->requires_file && ($document->file_path === null || $document->file_path === '')) {
            throw ValidationException::withMessages([
                'file' => ['Attach a file before verifying this document.'],
            ]);
        }

        $document->update([
            'verification_status' => WorkerDocumentVerificationStatus::Verified,
            'verified_by' => $request->user()?->id,
            'verified_at' => now(),
        ]);

        return redirect()
            ->route('tracking.workers.show', $worker)
            ->with('flash', ['success' => 'Document verified.']);
    }

    public function destroy(Request $request, Worker $worker, WorkerDocument $document): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-worker-documents') ?? false, 403);
        abort_unless($document->worker_id === $worker->id, 404);

        if ($document->file_path !== null && $document->file_path !== '') {
            Storage::disk('private')->delete($document->file_path);
        }

        $document->delete();

        return redirect()
            ->route('tracking.workers.show', $worker)
            ->with('flash', ['success' => 'Document removed.']);
    }
}
