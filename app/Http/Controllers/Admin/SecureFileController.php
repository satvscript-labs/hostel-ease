<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The ONLY way to read an uploaded file.
 *
 * Every upload in this app is personal data — Aadhaar cards, photos, signed
 * agreements. They used to sit in public/storage, i.e. physically inside the
 * web root, so the web server answered `/storage/staff/documents/abc.webp` by
 * reading the file straight off disk. PHP never ran, which meant auth, the
 * `role:`/`access:` middleware, SetTenant, TenantScope, route-model binding and
 * the activity log were all bypassed — not weakened, bypassed. The URL was the
 * only credential, and URLs escape: WhatsApp, shared-PC history, proxy caches,
 * Referer headers. Once out, the link worked forever, for anyone, unrevokably,
 * and nothing was logged because the app never saw the request.
 *
 * So the file now has no URL at all. It lives on the `private` disk, outside
 * the web root, and this controller is the guard: resolve → tenant-scope →
 * authorise → stream.
 *
 * Introduced in P1 (_artifact/ui_ux_audit/05_private_disk_plan.md); the legacy
 * public-disk fallback that carried P2/P3 is now removed (P4) — private only.
 */
class SecureFileController extends Controller
{
    /**
     * What may be asked for, and by whom.
     *
     * Nothing here is derived from the request beyond picking a key: the field
     * list is a whitelist, so `?field=password` resolves to nothing rather than
     * to something interesting. The `area` mirrors the `access:` middleware on
     * the pages these files belong to — a sub-user who cannot open the Staff
     * Board must not be able to fetch a staff Aadhaar by URL either.
     */
    private const SOURCES = [
        'staff' => [
            'model' => Staff::class,
            'fields' => ['photo', 'aadhaar_file'],
            'area' => 'staff',
            // Removing a staff member keeps their record reachable (W7.1), so
            // their photo has to resolve too or the profile renders broken.
            'with_trashed' => true,
        ],
        'student' => [
            'model' => Student::class,
            'fields' => ['photo', 'aadhaar_file'],
            'area' => 'students',
            'with_trashed' => true,
        ],
        'document' => [
            'model' => StudentDocument::class,
            'fields' => ['file_path'],
            'area' => 'students',
        ],
        'registration' => [
            'model' => StudentRegistration::class,
            'fields' => ['photo', 'aadhaar_file'],
            'area' => 'students',
        ],
    ];

    /** Repeat views are 304s, not re-downloads — avatars are the hot path. */
    private const CACHE_SECONDS = 86400;

    public function show(Request $request, string $source, int $id, string $field): Response
    {
        $config = self::SOURCES[$source] ?? null;

        // 404, never 403: a "forbidden" tells the caller the thing exists.
        // Every refusal below looks identical from outside.
        abort_if($config === null, 404);
        abort_unless(in_array($field, $config['fields'], true), 404);

        $user = $request->user();
        abort_unless(
            $user && ($user->isHostelAdmin() || $user->canAccessArea($config['area'])),
            404
        );

        $query = $config['model']::query();
        if ($config['with_trashed'] ?? false) {
            $query->withTrashed();
        }

        // TenantScope rides on this: another hostel's id simply isn't found.
        $model = $query->findOrFail($id);

        // Raw attribute, not the accessor — `photo` on Staff/Student has a
        // *_url accessor beside it, and we want the stored path.
        $path = $model->getAttributes()[$field] ?? null;
        abort_if(blank($path), 404);

        // The path comes from our own database, so traversal isn't reachable
        // from the URL. Checked anyway: this is the last gate before a
        // filesystem read, and it costs nothing to make that unrepresentable.
        abort_if(str_contains($path, '..'), 404);

        return $this->stream($request, $path);
    }

    /**
     * Stream from the private disk. The P2/P3 fallback to the legacy public disk
     * is gone (P4): every file has been migrated, the public disk no longer
     * exists in config, and a path that isn't on private is simply a 404.
     */
    private function stream(Request $request, string $path): Response
    {
        $disk = Storage::disk('private');
        abort_unless($disk->exists($path), 404);

        // Weak-compare the client's tag: browsers may send W/"..." back.
        $etag = '"'.md5($path.'|'.$disk->lastModified($path).'|'.$disk->size($path)).'"';
        $headers = [
            // `private` matters: this is one person's document, and a shared
            // proxy must never hold a copy to hand to the next requester.
            'Cache-Control' => 'private, max-age='.self::CACHE_SECONDS,
            'ETag' => $etag,
        ];

        $sent = $request->headers->get('If-None-Match');
        if ($sent && trim(str_replace('W/', '', $sent), '"') === trim($etag, '"')) {
            return response('', 304, $headers);
        }

        // ->response() streams and sets Content-Type from the file itself, so
        // the image opens inline in the browser tab.
        return $disk->response($path, null, $headers);
    }
}
