<?php

namespace App\Http\Controllers\Web\Permit;

use App\Http\Controllers\Web\BaseController;
use App\Models\WorkerDocumentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class WorkerDocumentTypeController extends BaseController
{
    /**
     * @var list<string>
     */
    private const CATEGORIES = [
        'identity',
        'medical',
        'competence',
        'site_access',
        'other',
    ];

    public function index(): InertiaResponse
    {
        $documentTypes = WorkerDocumentType::query()
            ->withCount('documents')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (WorkerDocumentType $type): array => $this->toArray($type));

        return Inertia::render('workforce/worker-document-types/index', [
            'documentTypes' => $documentTypes->values()->all(),
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        WorkerDocumentType::query()->create($validated);

        return back()->with('flash', ['success' => 'Document type created.']);
    }

    public function update(Request $request, WorkerDocumentType $workerDocumentType): RedirectResponse
    {
        $validated = $this->validated($request, $workerDocumentType);

        $workerDocumentType->update($validated);

        return back()->with('flash', ['success' => 'Document type updated.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?WorkerDocumentType $existing = null): array
    {
        $validated = $request->validate([
            'code' => [
                $existing === null ? 'required' : 'prohibited',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('worker_document_types', 'code'),
            ],
            'name' => [
                $existing === null ? 'required' : 'sometimes',
                'string',
                'max:255',
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => [
                $existing === null ? 'required' : 'sometimes',
                'string',
                Rule::in(self::CATEGORIES),
            ],
            'requires_expiry' => ['sometimes', 'boolean'],
            'requires_file' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        foreach (['requires_expiry', 'requires_file', 'is_active'] as $booleanField) {
            if ($request->has($booleanField)) {
                $validated[$booleanField] = $request->boolean($booleanField);
            }
        }

        if ($existing === null) {
            $validated['requires_expiry'] = $validated['requires_expiry'] ?? true;
            $validated['requires_file'] = $validated['requires_file'] ?? true;
            $validated['is_active'] = $validated['is_active'] ?? true;
            $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        } elseif ($request->has('sort_order')) {
            $validated['sort_order'] = (int) $validated['sort_order'];
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(WorkerDocumentType $type): array
    {
        return [
            'id' => $type->id,
            'uuid' => $type->uuid,
            'code' => $type->code,
            'name' => $type->name,
            'description' => $type->description,
            'category' => $type->category,
            'requires_expiry' => $type->requires_expiry,
            'requires_file' => $type->requires_file,
            'is_active' => $type->is_active,
            'sort_order' => $type->sort_order,
            'documents_count' => $type->documents_count,
        ];
    }
}
