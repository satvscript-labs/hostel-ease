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
         * DEPRECATED — being emptied by the private-disk migration
         * (_artifact/ui_ux_audit/05_private_disk_plan.md). Nothing new may be
         * written here; it exists only until the last legacy file has moved,
         * then it is deleted from this config entirely (owner decision D5) so
         * that reaching for 'public' throws instead of silently leaking.
         *
         * Files here are served by the WEB SERVER, straight off disk, before
         * PHP runs — so auth, SetTenant, TenantScope, route binding and the
         * activity log are all bypassed. The URL is the only credential.
         */
        'public' => [
            'driver' => 'local',
            // Write public uploads straight into public/storage so no symlink is
            // needed — shared hosts (e.g. Hostinger) disable symlink()/exec().
            'root' => public_path('storage'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

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
