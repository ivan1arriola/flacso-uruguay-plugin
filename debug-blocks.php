<?php
/**
 * Script de depuración para listar bloques registrados
 * 
 * Agregar este código a functions.php temporalmente:
 * add_action('admin_notices', function() {
 *     if (current_user_can('manage_options')) {
 *         $blocks = WP_Block_Type_Registry::get_instance()->get_all_registered();
 *         $flacso_blocks = array_filter($blocks, function($block) {
 *             return isset($block->category) && (
 *                 $block->category === 'flacso-uruguay' ||
 *                 $block->category === 'flacso-posgrados' ||
 *                 $block->category === 'flacso-docentes' ||
 *                 $block->category === 'flacso'
 *             );
 *         });
 *         
 *         echo '<div class="notice notice-info"><pre>';
 *         echo "BLOQUES FLACSO REGISTRADOS (" . count($flacso_blocks) . "):\n\n";
 *         foreach ($flacso_blocks as $name => $block) {
 *             echo "- {$name} ({$block->title}) - Categoría: {$block->category}\n";
 *         }
 *         echo '</pre></div>';
 *         
 *         $categories = get_block_categories(get_post());
 *         $flacso_cats = array_filter($categories, function($cat) {
 *             return strpos($cat['slug'], 'flacso') !== false;
 *         });
 *         
 *         echo '<div class="notice notice-info"><pre>';
 *         echo "CATEGORÍAS FLACSO REGISTRADAS (" . count($flacso_cats) . "):\n\n";
 *         foreach ($flacso_cats as $cat) {
 *             echo "- {$cat['slug']}: {$cat['title']}\n";
 *         }
 *         echo '</pre></div>';
 *     }
 * });
 */

// También puedes ejecutar esto directamente en un archivo PHP temporal:
if (defined('ABSPATH')) {
    $blocks = WP_Block_Type_Registry::get_instance()->get_all_registered();
    $flacso_blocks = array_filter($blocks, function($block) {
        return isset($block->category) && (
            $block->category === 'flacso-uruguay' ||
            $block->category === 'flacso-posgrados' ||
            $block->category === 'flacso-docentes' ||
            $block->category === 'flacso'
        );
    });
    
    echo "BLOQUES FLACSO REGISTRADOS (" . count($flacso_blocks) . "):\n\n";
    foreach ($flacso_blocks as $name => $block) {
        echo "- {$name} ({$block->title}) - Categoría: {$block->category}\n";
    }
    
    echo "\n\nCATEGORÍAS REGISTRADAS:\n\n";
    $categories = get_block_categories(get_post());
    $flacso_cats = array_filter($categories, function($cat) {
        return strpos($cat['slug'], 'flacso') !== false;
    });
    
    foreach ($flacso_cats as $cat) {
        echo "- {$cat['slug']}: {$cat['title']}\n";
    }
}
