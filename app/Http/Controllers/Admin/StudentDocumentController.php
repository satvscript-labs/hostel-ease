<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StudentDocumentController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function store(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['aadhaar', 'photo', 'agreement', 'other'])],
            'title' => ['nullable', 'string', 'max:150'],
            'expiry_date' => ['nullable', 'date'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'is_signed' => ['nullable', 'boolean'],
        ]);

        $path = $request->file('file')->store("students/documents/{$student->id}", 'public');

        $document = $student->documents()->create([
            'hostel_id' => $student->hostel_id,
            'type' => $data['type'],
            'title' => $data['title'] ?? ucfirst($data['type']),
            'file_path' => $path,
            'expiry_date' => $data['expiry_date'] ?? null,
            'is_signed' => $request->boolean('is_signed'),
        ]);

        $this->logger->log('document.upload', "Uploaded {$document->type} for {$student->name}", $document);

        return back()->with('success', 'Document uploaded.');
    }

    public function destroy(Student $student, StudentDocument $document): RedirectResponse
    {
        abort_unless($document->student_id === $student->id, 404);

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        $this->logger->log('document.delete', "Deleted document for {$student->name}", $student);

        return back()->with('success', 'Document deleted.');
    }
}
