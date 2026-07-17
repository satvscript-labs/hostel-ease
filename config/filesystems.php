<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
         * Every upload in this app is personal data — Aadhaar cards, photos,
         * signed agreements. This disk lives OUTSIDE the web root, so a file
         * here has no URL at all and can only be reached through
         * SecureFileController, which authenticates, tenant-scopes and
         * authorises first.
         *
         * Deliberately no 'url' key: an accidental ->url() call should fail
         * loudly rather than hand back a link that bypasses every guard.
         */
        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        /*
         * The 'public' disk is intentionally DISABLED (private-disk migration
         * P4, owner decision D5). Every upload in this app is personal data and
         * now lives on the 'private' disk, served through SecureFileController.
         *
         * Why a bogus driver instead of deleting the key or setting it null:
         *  · Laravel 11 merges the framework's DEFAULT filesystems config
         *    underneath this file, and that default defines a real 'public'
         *    disk — so just removing the key lets a working disk reappear.
         *  · null can't be used either: the framework's serveFiles() iterates
         *    every disk at boot and requires each to be an array, so null
         *    crashes the whole app on boot.
         * A disk with an unregistered driver satisfies boot (it's an array with
         * a 'driver' key, and shouldServeFiles only skips it) yet makes any
         * actual use — Storage::disk('public')->put(...) — throw
         * "Driver [disabled] is not supported". So the old mistake fails loudly
         * instead of silently writing a file the web server could hand out.
         *
         * The files this disk once held were migrated by
         * hostelease:privatise-uploads (P3) and public/storage emptied by
         * hostelease:purge-public-uploads (P4). Do not re-enable it.
         */
        'public' => ['driver' => 'disabled'],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
     * 'links' is deliberately EMPTY.
     *
     * It used to map public_path('storage') => storage_path('app/public') —
     * left over from Laravel's default. But the 'public' disk's root IS
     * public_path('storage'): a real directory holding every upload. So
     * `php artisan storage:link --force` would have deleted that directory and
     * replaced it with a symlink to storage/app/public (which isn't even the
     * configured root), orphaning every file in one command. It was inert only
     * because nobody had run it.
     *
     * Nothing in this app needs a symlink — the private disk is served through
     * a controller, and the legacy public disk already writes inside the web
     * root. Keep this empty.
     */
    'links' => [],

];
