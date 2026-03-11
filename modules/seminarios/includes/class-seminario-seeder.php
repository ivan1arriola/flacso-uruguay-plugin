<?php
if (!defined('ABSPATH')) {
    exit;
}

class Seminario_Seeder
{
    public static function seed()
    {
        if (class_exists('Seminario_Taxonomies')) {
            Seminario_Taxonomies::maybe_cleanup_legacy_taxonomies();
        }
    }
}
