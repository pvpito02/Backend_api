<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\UploadMediaRequest;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;

class MediaController extends Controller
{
    public function __construct(private readonly MediaService $media) {}

    /**
     * Upload fichier → disk public (accessible via /storage/... après storage:link).
     */
    public function store(UploadMediaRequest $request): JsonResponse
    {
        $stored = $this->media->store(
            $request->file('file'),
            $request->string('folder')->toString(),
        );

        return response()->json([
            'message' => 'Fichier uploadé.',
            'path' => $stored['path'],
            'url' => $stored['url'],
            'original_name' => $stored['original_name'],
            'mime' => $stored['mime'],
            'size' => $stored['size'],
        ], 201);
    }
}
