<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Services\ActivityLogger;
use App\Services\ImageService;
use App\Services\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentDocumentController extends Controller
{
    public function __construct(
        protected ActivityLogger $logger,
        protected ImageService $imageService,
        protected StorageService $storageService
    ) {}

    public function store(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['aadhaar', 'photo', 'agreement', 'other'])],
            'title' => ['nullable', 'string', 'max:150'],
            'expiry_date' => ['nullable', 'date'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'is_signed' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('file');
        $directory = "students/documents/{$student->id}";
        
        // If it's an image (not a pdf), compress it
        if (str_starts_with($file->getMimeType(), 'image/')) {
            $processed = $this->imageService->compressAndConvertToWebp($file, 1600, 1600, 80);
            $path = $this->storageService->store($processed['content'], $directory, 'public', $processed['extension']);
        } else {
            $path = $this->storageService->store($file, $directory, 'public');
        }

        $document = $student->documents()->create([
            'hostel_id' => $student->hostel_id,
            'type' => $data['type'],
            'title' => $data['title'] ?? ucfirst($data['type']),
            'file_path' => $path,
            'expiry_date' => $data['expiry_date'] ?? null,
            'is_signed' => $request->boolean('is_signed'),
        ]);

        $this->logger->log('document.upload', "Uploaded {$document->type} for {$student->name}", $document);

        return back()->with('success', 'Document uploaded successfully.');
    }

    public function destroy(Student $student, StudentDocument $document): RedirectResponse
    {
        abort_unless($document->student_id === $student->id, 404);

        $this->storageService->delete($document->file_path, 'public');
        $document->delete();

        $this->logger->log('document.delete', "Deleted document for {$student->name}", $student);

        return back()->with('success', 'Document deleted.');
    }
}
