<?php
/**
 * Formulario de edición completo de Oferta.
 * Los campos del dominio los renderiza el controlador mediante su puerto PHP.
 * Esta plantilla no consulta WP_Post ni post_meta.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<form id="post" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="flacso-new-editor__form">
    <input type="hidden" name="action" value="flacso_editor_save_offer">
    <input type="hidden" name="flacso_editor_post_id" value="<?php echo esc_attr((string) $entity['id']); ?>">
    <input type="hidden" name="flacso_editor_offer_form_present" value="1">
    <?php wp_nonce_field('flacso_editor_save_offer_' . (int) $entity['id'], 'flacso_editor_nonce'); ?>

    <div class="flacso-new-editor__layout">
        <main class="flacso-new-editor__main">
            <section class="flacso-new-editor__card flacso-new-editor__identity" aria-labelledby="flacso-editor-general">
                <div class="flacso-new-editor__section-heading">
                    <div>
                        <p class="flacso-new-editor__eyebrow"><?php esc_html_e('Identidad', 'flacso-uruguay'); ?></p>
                        <h2 id="flacso-editor-general"><?php esc_html_e('Nombre de la oferta académica', 'flacso-uruguay'); ?></h2>
                    </div>
                </div>
                <label class="flacso-new-editor__label" for="flacso-offer-title"><?php esc_html_e('Título', 'flacso-uruguay'); ?></label>
                <input
                    id="flacso-offer-title"
                    class="flacso-new-editor__title-field"
                    type="text"
                    name="post_title"
                    value="<?php echo esc_attr((string) $entity['title']); ?>"
                    required
                    autocomplete="off"
                >
            </section>

            <section class="flacso-new-editor__card flacso-new-editor__image">
                    <p class="flacso-new-editor__eyebrow"><?php esc_html_e('Presentación pública', 'flacso-uruguay'); ?></p>
                    <h2><?php esc_html_e('Imagen destacada', 'flacso-uruguay'); ?></h2>
                    <div class="flacso-new-editor__image-row">
                    <input
                        type="hidden"
                        name="flacso_thumbnail_id"
                        id="flacso-offer-thumbnail-id"
                        value="<?php echo esc_attr((string) ($fields['thumbnail_id'] ?? 0)); ?>"
                    >
                    <div class="flacso-new-editor__image-preview" id="flacso-offer-thumbnail-preview">
                        <?php if (!empty($fields['thumbnail_url'])) : ?>
                            <img src="<?php echo esc_url((string) $fields['thumbnail_url']); ?>" alt="">
                        <?php else : ?>
                            <span><?php esc_html_e('Sin imagen destacada', 'flacso-uruguay'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="flacso-new-editor__image-actions">
                        <button type="button" class="button button-secondary" id="flacso-offer-thumbnail-pick"><?php esc_html_e('Elegir imagen', 'flacso-uruguay'); ?></button>
                        <button type="button" class="button button-link-delete" id="flacso-offer-thumbnail-remove"><?php esc_html_e('Quitar', 'flacso-uruguay'); ?></button>
                    </div>
                    </div>
            </section>

            <section class="flacso-new-editor__fields" aria-label="<?php esc_attr_e('Todos los campos académicos de la oferta', 'flacso-uruguay'); ?>">
                <?php if ($controller instanceof FLACSO_Editor_Editable_Controller_Interface) : ?>
                    <?php $controller->render_form((int) $entity['id']); ?>
                <?php endif; ?>
            </section>
        </main>

        <aside class="flacso-new-editor__aside">
            <div class="flacso-new-editor__aside-sticky">
                <section class="flacso-new-editor__card flacso-new-editor__publication">
                    <p class="flacso-new-editor__eyebrow"><?php esc_html_e('Publicación', 'flacso-uruguay'); ?></p>
                    <h2><?php esc_html_e('Guardar cambios', 'flacso-uruguay'); ?></h2>
                    <p class="flacso-new-editor__muted">
                        <?php esc_html_e('Los campos académicos utilizan las mismas validaciones y reglas del editor actual.', 'flacso-uruguay'); ?>
                    </p>

                    <label class="flacso-new-editor__label" for="flacso-offer-status"><?php esc_html_e('Estado de publicación', 'flacso-uruguay'); ?></label>
                    <?php if ($can_publish) : ?>
                        <select id="flacso-offer-status" name="post_status" class="flacso-new-editor__select">
                            <?php foreach ([
                                'draft' => __('Borrador', 'flacso-uruguay'),
                                'pending' => __('Pendiente de revisión', 'flacso-uruguay'),
                                'publish' => __('Publicada', 'flacso-uruguay'),
                                'private' => __('Privada', 'flacso-uruguay'),
                            ] as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($entity['status'], $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                            <?php if ($entity['status'] === 'future') : ?>
                                <option value="future" selected><?php esc_html_e('Programada (conservar fecha actual)', 'flacso-uruguay'); ?></option>
                            <?php endif; ?>
                        </select>
                    <?php else : ?>
                        <input type="hidden" name="post_status" value="<?php echo esc_attr((string) $entity['status']); ?>">
                        <p class="flacso-new-editor__muted"><?php echo esc_html((string) $entity['status']); ?></p>
                    <?php endif; ?>

                    <button type="submit" class="button button-primary flacso-new-editor__primary-action" data-flacso-save>
                        <?php esc_html_e('Guardar oferta académica', 'flacso-uruguay'); ?>
                    </button>
                    <p class="flacso-new-editor__save-hint" data-flacso-dirty aria-live="polite"></p>
                    <a class="flacso-new-editor__legacy-link" href="<?php echo esc_url($legacy_url); ?>">
                        &larr; <?php esc_html_e('Volver al editor actual', 'flacso-uruguay'); ?>
                    </a>
                </section>


            </div>
        </aside>
    </div>
</form>
