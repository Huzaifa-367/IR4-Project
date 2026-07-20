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
            'files' => ['nullable', 'array', 'max:20'],
            'files.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:51200'],
        ]);

        $documentType = WorkerDocumentType::query()->findOrFail((int) $validated['worker_document_type_id']);

        /** @var list<UploadedFile> $uploads */
        $uploads = [];

        /** @var UploadedFile|null $single */
        $single = $request->file('file');

        if ($single instanceof UploadedFile) {
            $uploads[] = $single;
        }

        $batch = $request->file('files');

        if (is_array($batch)) {
            foreach ($batch as $file) {
                if ($file instanceof UploadedFile) {
                    $uploads[] = $file;
                }
            }
        }

        if ($documentType->requires_file && $uploads === []) {
            throw ValidationException::withMessages([
                'file' => ['A file attachment is required for this document type.'],
            ]);
        }

        if ($documentType->requires_expiry && empty($validated['expires_at'])) {
            throw ValidationException::withMessages([
                'expires_at' => ['An expiry date is required for this document type.'],
            ]);
        }

        if ($uploads === []) {
            WorkerDocument::query()->create([
                'worker_id' => $worker->id,
                'worker_document_type_id' => $documentType->id,
                'document_number' => $validated['document_number'] ?? null,
                'issuing_body' => $validated['issuing_body'] ?? null,
                'issued_at' => $validated['issued_at'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'file_path' => null,
                'verification_status' => WorkerDocumentVerificationStatus::Pending,
                'uploaded_by' => $request->user()?->id,
            ]);

            return back()->with('flash', ['success' => 'Document added.']);
        }

        foreach ($uploads as $index => $file) {
            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $filePath = $file->storeAs(
                'worker-docs/'.$worker->id,
                (string) Str::uuid().'.'.$extension,
                'private',
            );

            WorkerDocument::query()->create([
                'worker_id' => $worker->id,
                'worker_document_type_id' => $documentType->id,
                'document_number' => $index === 0
                    ? ($validated['document_number'] ?? null)
                    : null,
                'issuing_body' => $index === 0
                    ? ($validated['issuing_body'] ?? null)
                    : null,
                'issued_at' => $index === 0
                    ? ($validated['issued_at'] ?? null)
                    : null,
                'expires_at' => $validated['expires_at'] ?? null,
                'notes' => $index === 0
                    ? ($validated['notes'] ?? null)
                    : null,
                'file_path' => $filePath,
                'verification_status' => WorkerDocumentVerificationStatus::Pending,
                'uploaded_by' => $request->user()?->id,
            ]);
        }

        $count = count($uploads);

        return back()->with('flash', [
            'success' => $count === 1
                ? 'Document uploaded.'
                : "{$count} documents uploaded.",
        ]);
    }

    public function update(Request $request, Worker $worker, WorkerDocument $document): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-worker-documents') ?? false, 403);
        abort_unless($document->worker_id === $worker->id, 404);

        $document->loadMissing('documentType');
        $documentType = $document->documentType;

        $validated = $request->validate([
            'document_number' => ['nullable', 'string', 'max:255'],
            'issuing_body' => ['nullable', 'string', 'max:255'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:51200'],
        ]);

        if ($documentType?->requires_expiry && empty($validated['expires_at'])) {
            throw ValidationException::withMessages([
                'expires_at' => ['An expiry date is required for this document type.'],
            ]);
        }

        $filePath = $document->file_path;

        /** @var UploadedFile|null $file */
        $file = $request->file('file');

        if ($file instanceof UploadedFile) {
            if ($filePath !== null && $filePath !== '') {
                Storage::disk('private')->delete($filePath);
            }

            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $filePath = $file->storeAs(
                'worker-docs/'.$worker->id,
                (string) Str::uuid().'.'.$extension,
                'private',
            );
        }

        if ($documentType?->requires_file && ($filePath === null || $filePath === '')) {
            throw ValidationException::withMessages([
                'file' => ['A file attachment is required for this document type.'],
            ]);
        }

        $document->update([
            'document_number' => $validated['document_number'] ?? null,
            'issuing_body' => $validated['issuing_body'] ?? null,
            'issued_at' => $validated['issued_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'file_path' => $filePath,
            // Replacing metadata/file returns the row to pending review.
            'verification_status' => WorkerDocumentVerificationStatus::Pending,
            'verified_by' => null,
            'verified_at' => null,
            'uploaded_by' => $request->user()?->id ?? $document->uploaded_by,
        ]);

        return back()->with('flash', ['success' => 'Document updated.']);
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

        return back()->with('flash', ['success' => 'Document verified.']);
    }

    public function reject(Request $request, Worker $worker, WorkerDocument $document): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-worker-documents') ?? false, 403);
        abort_unless($document->worker_id === $worker->id, 404);

        $document->update([
            'verification_status' => WorkerDocumentVerificationStatus::Rejected,
            'verified_by' => null,
            'verified_at' => null,
        ]);

        return back()->with('flash', ['success' => 'Document rejected.']);
    }

    public function destroy(Request $request, Worker $worker, WorkerDocument $document): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-worker-documents') ?? false, 403);
        abort_unless($document->worker_id === $worker->id, 404);

        if ($document->file_path !== null && $document->file_path !== '') {
            Storage::disk('private')->delete($document->file_path);
        }

        $document->delete();

        return back()->with('flash', ['success' => 'Document removed.']);
    }
}
