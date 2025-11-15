<?php

// ============================================
// Config File: config/asset-cleaner.php
// ============================================

return [
    /*
     * Asset types to clean - Set to 'all' or specify types: ['js', 'css', 'img', 'fonts']
     * This determines which asset categories will be scanned and cleaned
     */
    'clean_types' => 'all', // or ['js', 'css', 'img'] for specific types

    'strict_matching' => true, // Default to strict mode
    /*
     * Asset type definitions
     * Define directories and extensions for each asset type
     */
    'asset_types' => [
        'js' => [
            'directories' => [
                'resources/js',
                'public/js',
                'public/js/*',
                'resources/json'
            ],
            'extensions' => ['js', 'jsx', 'ts', 'tsx', 'vue', 'mjs'],
        ],
        'css' => [
            'directories' => [
                'resources/css',
                'resources/sass',
                'resources/scss',
                'resources/less',
                'public/css',
                'public/css/*',
            ],
            'extensions' => ['css', 'scss', 'sass', 'less'],
        ],
        'img' => [
            'directories' => [
                'resources/images',
                'resources/img',
                'public/images',
                'public/img',
                'public/assets/images',
                'public/assets/**',
            ],
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico', 'bmp'],
        ],
        'fonts' => [
            'directories' => [
                'resources/fonts',
                'public/fonts',
                'public/assets/fonts',
            ],
            'extensions' => ['woff', 'woff2', 'ttf', 'eot', 'otf'],
        ],
        'video' => [
            'directories' => [
                'resources/videos',
                'public/videos',
                'public/assets/videos',
            ],
            'extensions' => ['mp4', 'webm', 'ogg', 'avi', 'mov'],
        ],
        'audio' => [
            'directories' => [
                'resources/audio',
                'public/audio',
                'public/assets/audio',
            ],
            'extensions' => ['mp3', 'wav', 'ogg', 'aac', 'm4a'],
        ],
        'json' => [
            'directories' => ['resources/json', 'public/json'],
            'extensions' => ['json', 'js'],
        ],
    ],

    /*
     * Directories to search for asset references
     */
    'scan_directories' => [
        'app',
        'resources',
        'routes',
        'config',
    ],

    /*
     * File extensions to scan for references
     */
    'reference_extensions' => [
        'php',
        'blade.php',
        'js',
        'jsx',
        'ts',
        'tsx',
        'vue',
        'json',
        'css',
        'scss',
        'sass',
        'less',
        'html',
    ],

    /*
     * Patterns to exclude from deletion (regex)
     */
    'exclude_patterns' => [
        '/node_modules/',
        '/vendor/',
        '/.git/',
        '/app.js$/',
        '/app.css$/',
        '/bootstrap.js$/',
    ],

    /*
     * Files that should never be deleted
     */
    'protected_files' => [
        'resources/js/app.js',
        'resources/css/app.css',
        'resources/sass/app.scss',
    ],

    /*
     * Backup directory for deleted files
     */
    'backup_directory' => storage_path('asset-cleaner-backup'),

    /*
     * Create backup before deletion
     */
    'create_backup' => true,
];
