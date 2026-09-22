<?php

namespace App\Http\Controllers;

use App\Models\Evidence;
use App\Models\Reflection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EvidenceController extends Controller
{
    // Max upload size in kilobytes (10 MB)
    const MAX_FILE_KB = 10240;

    // File types students can attach as evidence
    const ALLOWED_TYPES = 'pdf,doc,docx,ppt,pptx,xls,xlsx,txt,csv,png,jpg,jpeg,gif,webp,zip';

    /**
     * Attach evidence to a reflection.
     * Send either a `file` (multipart upload) or a `link` - not both.
     */
    public function store(Request $request, $id)
    {
        // 1. Find the reflection entry by ID
        $reflection = Reflection::find($id);

        // 2. Check if the entry exists (Edge case handling)
        if (!$reflection) {
            return response()->json([
                'success' => false,
                'message' => 'Reflection entry not found.'
            ], 404);
        }

        // 3. Validate - exactly one of file or link
        $validated = $request->validate([
            // mimes checks what's actually inside the file, extensions checks
            // its name - both have to be on the allowed list
            'file'        => 'required_without:link|prohibits:link|file|max:' . self::MAX_FILE_KB . '|mimes:' . self::ALLOWED_TYPES . '|extensions:' . self::ALLOWED_TYPES,
            'link'        => 'required_without:file|nullable|url:http,https|max:2048',
            'description' => 'nullable|string|max:255',
        ], [
            'file.required_without' => 'Please attach a file or add a link.',
            'link.required_without' => 'Please attach a file or add a link.',
            'file.prohibits'        => 'Please attach either a file or a link, not both.',
            'file.max'              => 'The file may not be bigger than 10 MB.',
            'file.mimes'            => 'That file type is not allowed. Allowed types: ' . str_replace(',', ', ', self::ALLOWED_TYPES) . '.',
            'file.extensions'       => 'That file type is not allowed. Allowed types: ' . str_replace(',', ', ', self::ALLOWED_TYPES) . '.',
            'file.uploaded'         => 'The file failed to upload. It may be too big for the server.',
            'link.url'              => 'The link must be a valid http or https URL.',
            'description.max'       => 'The description may not be greater than 255 characters.',
        ]);

        // 4. Save the evidence
        if ($request->hasFile('file')) {
            $file = $request->file('file');

            $evidence = $reflection->evidence()->create([
                'type'          => 'file',
                'description'   => $validated['description'] ?? null,
                'file_path'     => Storage::putFile("evidence/{$reflection->id}", $file),
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getMimeType(),
                'size'          => $file->getSize(),
            ]);
        } else {
            $evidence = $reflection->evidence()->create([
                'type'        => 'link',
                'description' => $validated['description'] ?? null,
                'link'        => $validated['link'],
            ]);
        }

        // 5. Return success response
        return response()->json([
            'success' => true,
            'message' => 'Evidence added successfully!',
            'data'    => $evidence
        ], 201);
    }

    /**
     * List all evidence attached to a reflection.
     */
    public function index($id)
    {
        $reflection = Reflection::find($id);

        if (!$reflection) {
            return response()->json([
                'success' => false,
                'message' => 'Reflection entry not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $reflection->evidence()->latest()->get()
        ], 200);
    }

    /**
     * Download an uploaded evidence file (with its original file name).
     */
    public function download($id)
    {
        $evidence = Evidence::find($id);

        if (!$evidence) {
            return response()->json([
                'success' => false,
                'message' => 'Evidence not found.'
            ], 404);
        }

        if ($evidence->type !== 'file' || !Storage::exists($evidence->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'This evidence has no file to download.'
            ], 404);
        }

        return Storage::download($evidence->file_path, $evidence->original_name);
    }

    /**
     * Remove a piece of evidence (and its file, if it has one).
     */
    public function destroy($id)
    {
        $evidence = Evidence::find($id);

        if (!$evidence) {
            return response()->json([
                'success' => false,
                'message' => 'Evidence not found.'
            ], 404);
        }

        $evidence->delete(); // the model also deletes the stored file

        return response()->json([
            'success' => true,
            'message' => 'Evidence deleted successfully!'
        ], 200);
    }
}
