<?php
/**
 * Vista preliminar: no contiene formularios ni escribe datos en WordPress.
 *
 * Variables normalizadas suministradas por los controladores de /editor.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap flacso-new-editor">
    <nav class="flacso-new-editor__breadcrumb" aria-label="<?php esc_attr_e('Ruta de navegación', 'flacso-uruguay'); ?>">
        <a href="<?php echo esc_url(admin_url('admin.php?page=flacso-panel')); ?>"><?php esc_html_e('FLACSO', 'flacso-uruguay'); ?></a>
        <span aria-hidden="true">/</span>
        <a href="<?php echo esc_url(admin_url('edit.php?post_type=oferta-academica')); ?>"><?php esc_html_e('Ofertas académicas', 'flacso-uruguay'); ?></a>
        <span aria-hidden="true">/</span>
        <span><?php echo esc_html($is_offer ? __('Oferta', 'flacso-uruguay') : __('Cohorte', 'flacso-uruguay')); ?></span>
    </nav>

    <header class="flacso-new-editor__header">
        <div>
            <p class="flacso-new-editor__eyebrow"><?php echo esc_html($is_offer ? __('Oferta académica', 'flacso-uruguay') : __('Cohorte', 'flacso-uruguay')); ?> · <?php esc_html_e('Editor nuevo', 'flacso-uruguay'); ?></p>
            <h1><?php echo esc_html((string) $entity['title']); ?></h1>
            <p class="flacso-new-editor__subtitle"><?php esc_html_e('Vista preliminar de la nueva experiencia de edición académica.', 'flacso-uruguay'); ?></p>
        </div>
        <div class="flacso-new-editor__header-actions">
            <a class="button button-secondary" href="<?php echo esc_url($legacy_url); ?>">
                &larr; <?php esc_html_e('Volver al editor actual', 'flacso-uruguay'); ?>
            </a>
        </div>
    </header>

    <div class="flacso-new-editor__notice" role="status">
        <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
        <p><strong><?php esc_html_e('Etapa inicial: navegación y consulta.', 'flacso-uruguay'); ?></strong>
        <?php esc_html_e('Los cambios todavía se guardan en el editor actual. Esta vista no modifica información ni altera las publicaciones.', 'flacso-uruguay'); ?></p>
    </div>

    <div class="flacso-new-editor__layout">
        <main class="flacso-new-editor__main">
            <section class="flacso-new-editor__card" aria-labelledby="flacso-editor-identity">
                <div class="flacso-new-editor__section-heading">
                    <div>
                        <p class="flacso-new-editor__eyebrow"><?php esc_html_e('Información', 'flacso-uruguay'); ?></p>
                        <h2 id="flacso-editor-identity"><?php esc_html_e('Datos generales', 'flacso-uruguay'); ?></h2>
                    </div>
                </div>
                <dl class="flacso-new-editor__details">
                    <div>
                        <dt><?php esc_html_e('Tipo de entidad', 'flacso-uruguay'); ?></dt>
                        <dd><?php echo esc_html($is_offer ? __('Oferta académica', 'flacso-uruguay') : __('Cohorte', 'flacso-uruguay')); ?></dd>
                    </div>
                    <div>
                        <dt><?php esc_html_e('Estado en WordPress', 'flacso-uruguay'); ?></dt>
                        <dd><?php echo esc_html((string) $entity['status']); ?></dd>
                    </div>
                    <?php if ($is_offer) : ?>
                        <div>
                            <dt><?php esc_html_e('Abreviación', 'flacso-uruguay'); ?></dt>
                            <dd><?php echo esc_html((string) ($fields['abbreviation'] ?? '') ?: '—'); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('Correo de contacto', 'flacso-uruguay'); ?></dt>
                            <dd><?php echo esc_html((string) ($fields['contact_email'] ?? '') ?: '—'); ?></dd>
                        </div>
                    <?php else : ?>
                        <div>
                            <dt><?php esc_html_e('Número de cohorte', 'flacso-uruguay'); ?></dt>
                            <dd><?php
                                echo esc_html((string) ($fields['number_label'] ?? '') ?: '—');
                            ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('Estado académico', 'flacso-uruguay'); ?></dt>
                            <dd><?php echo esc_html((string) ($fields['academic_status'] ?? '') ?: '—'); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('Modalidad', 'flacso-uruguay'); ?></dt>
                            <dd><?php echo esc_html((string) ($fields['modality'] ?? '') ?: '—'); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('Inicio', 'flacso-uruguay'); ?></dt>
                            <dd><?php echo esc_html((string) ($fields['start'] ?? '') ?: '—'); ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </section>

            <?php if ($is_offer) : ?>
                <section class="flacso-new-editor__card" aria-labelledby="flacso-editor-cohortes">
                    <div class="flacso-new-editor__section-heading">
                        <div>
                            <p class="flacso-new-editor__eyebrow"><?php esc_html_e('Relaciones', 'flacso-uruguay'); ?></p>
                            <h2 id="flacso-editor-cohortes"><?php esc_html_e('Cohortes', 'flacso-uruguay'); ?></h2>
                        </div>
                        <span class="flacso-new-editor__count"><?php echo esc_html((string) count($cohorts)); ?></span>
                    </div>

                    <?php if ($cohorts) : ?>
                        <ul class="flacso-new-editor__cohorts">
                            <?php foreach ($cohorts as $cohort) : ?>
                                <li>
                                    <div>
                                        <strong><?php echo esc_html((string) $cohort['title']); ?></strong>
                                        <span><?php echo esc_html((string) ($cohort['fields']['academic_status'] ?? '') ?: __('Sin estado', 'flacso-uruguay')); ?></span>
                                    </div>
                                    <a class="button button-secondary" href="<?php echo esc_url(FLACSO_Editor_Router::new_url((int) $cohort['id'])); ?>">
                                        <?php esc_html_e('Abrir cohorte', 'flacso-uruguay'); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="flacso-new-editor__muted"><?php esc_html_e('No hay cohortes accesibles para esta oferta.', 'flacso-uruguay'); ?></p>
                    <?php endif; ?>
                </section>
            <?php elseif (is_array($parent) && ($parent['type'] ?? '') === 'oferta-academica') : ?>
                <section class="flacso-new-editor__card" aria-labelledby="flacso-editor-parent">
                    <p class="flacso-new-editor__eyebrow"><?php esc_html_e('Relaciones', 'flacso-uruguay'); ?></p>
                    <h2 id="flacso-editor-parent"><?php esc_html_e('Oferta académica', 'flacso-uruguay'); ?></h2>
                    <p><?php echo esc_html((string) $parent['title']); ?></p>
                    <?php if ($parent_url !== '') : ?>
                        <a class="button button-secondary" href="<?php echo esc_url($parent_url); ?>"><?php esc_html_e('Abrir oferta', 'flacso-uruguay'); ?></a>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>

        <aside class="flacso-new-editor__aside">
            <section class="flacso-new-editor__card">
                <p class="flacso-new-editor__eyebrow"><?php esc_html_e('Publicación', 'flacso-uruguay'); ?></p>
                <h2><?php esc_html_e('Acciones disponibles', 'flacso-uruguay'); ?></h2>
                <p class="flacso-new-editor__muted"><?php esc_html_e('Para guardar, actualizar o publicar, utilizá por ahora el editor actual.', 'flacso-uruguay'); ?></p>
                <a class="button button-primary flacso-new-editor__primary-action" href="<?php echo esc_url($legacy_url); ?>">
                    <?php esc_html_e('Editar y guardar en WordPress', 'flacso-uruguay'); ?>
                </a>
            </section>
        </aside>
    </div>
</div>
