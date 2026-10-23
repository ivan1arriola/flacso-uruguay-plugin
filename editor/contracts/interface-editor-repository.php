<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Puerto de lectura del catálogo para la interfaz de edición.
 *
 * Ningún controlador ni vista debe conocer WP_Post, post_meta,
 * consultas SQL ni funciones de persistencia. La implementación
 * concreta convierte las entidades de WordPress a arrays simples.
 *
 * @phpstan-type EditorEntity array{id:int,type:string,title:string,status:string,fields:array<string,mixed>}
 */
interface FLACSO_Editor_Repository_Interface {
    /** @return array<string,mixed>|null */
    public function find(int $id): ?array;

    /** Respetar capacidades y alcance académico del usuario actual. */
    public function can_edit(int $id): bool;

    /** Publicación permitida según capacidades del CPT para el usuario actual. */
    public function can_publish(int $id): bool;

    /** URL del formulario clásico para el mismo registro. */
    public function legacy_edit_url(int $id): string;

    /** @return array<int,array<string,mixed>> Cohortes visibles y editables. */
    public function cohorts_for_offer(int $offer_id): array;
}
