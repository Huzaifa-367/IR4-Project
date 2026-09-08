<?php

namespace App\Http\Controllers\Web\Recordings;

use App\Services\Recordings\RecordingArchiveService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class RecordingBrowserController
{
    public function index(Request $request, RecordingArchiveService $archive, ?string $path = null): InertiaResponse
    {
        abort_unless($request->user()?->can('view-recordings'), 403);

        $page = $archive->browse($path ?? '');

        return Inertia::render('recordings/index', [
            'browse' => $page,
            'streamBaseUrl' => url('/recordings/stream'),
        ]);
    }

    public function stream(Request $request, RecordingArchiveService $archive, string $path): Response|BinaryFileResponse
    {
        abort_unless($request->user()?->can('view-recordings'), 403);

        $file = $archive->assertPlayableFile($path);

        if ($archive->usesXAccel()) {
            return response('', 200, [
                'Content-Type' => $file['mime'],
                'Content-Length' => (string) $file['size'],
                'Accept-Ranges' => 'bytes',
                'Content-Disposition' => 'inline; filename="'.$file['name'].'"',
                'X-Accel-Redirect' => $archive->xAccelPath($file['relative']),
            ]);
        }

        return response()->file($file['absolute'], [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline; filename="'.$file['name'].'"',
        ]);
    }
}
