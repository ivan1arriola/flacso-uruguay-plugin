<?php

if (!class_exists('FLACSO_Posgrados_Plugin')) {
    class FLACSO_Posgrados_Plugin {
        public static function init(): void {
            add_action('init', [FLACSO_Posgrados_Fields::class, 'register_meta_and_support']);
            add_action('admin_menu', [FLACSO_Posgrados_Admin_Page::class, 'register_menu']);
            add_action('admin_init', [FLACSO_Posgrados_Admin_Page::class, 'handle_bulk_save']);
            add_action('admin_init', [FLACSO_Posgrados_Seeder::class, 'maybe_seed_map']);
            add_action('admin_notices', [FLACSO_Posgrados_Seeder::class, 'maybe_notice_seed_needed']);
            add_action('admin_enqueue_scripts', [FLACSO_Posgrados_Admin_Page::class, 'enqueue_assets']);
            FLACSO_Posgrados_Block::init();
        }
    }
}
