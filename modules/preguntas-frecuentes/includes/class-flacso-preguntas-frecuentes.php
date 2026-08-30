<?php
/**
 * CPT y renderizador de preguntas frecuentes.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH')) {
    exit;
}

final class FLACSO_Preguntas_Frecuentes {
    public const POST_TYPE = 'pregunta-frecuente';
    private const SEED_OPTION = 'flacso_faq_seed_version';
    private const SEED_VERSION = 1;

    public static function init(): void {
        add_action('init', [self::class, 'register_post_type']);
        add_action('admin_init', [self::class, 'maybe_seed_initial_questions']);
        add_action('pre_get_posts', [self::class, 'order_admin_list']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [self::class, 'admin_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [self::class, 'admin_column_content'], 10, 2);
    }

    public static function register_post_type(): void {
        $labels = [
            'name'                  => __('Preguntas frecuentes', 'flacso-uruguay'),
            'singular_name'         => __('Pregunta frecuente', 'flacso-uruguay'),
            'menu_name'             => __('Preguntas frecuentes', 'flacso-uruguay'),
            'add_new'               => __('Añadir pregunta', 'flacso-uruguay'),
            'add_new_item'          => __('Añadir pregunta frecuente', 'flacso-uruguay'),
            'edit_item'             => __('Editar pregunta frecuente', 'flacso-uruguay'),
            'new_item'              => __('Nueva pregunta frecuente', 'flacso-uruguay'),
            'view_item'             => __('Ver pregunta frecuente', 'flacso-uruguay'),
            'search_items'          => __('Buscar preguntas frecuentes', 'flacso-uruguay'),
            'not_found'             => __('No se encontraron preguntas frecuentes.', 'flacso-uruguay'),
            'not_found_in_trash'    => __('No hay preguntas frecuentes en la papelera.', 'flacso-uruguay'),
            'item_published'        => __('Pregunta publicada.', 'flacso-uruguay'),
            'item_updated'          => __('Pregunta actualizada.', 'flacso-uruguay'),
        ];

        register_post_type(self::POST_TYPE, [
            'labels'              => $labels,
            'description'         => __('Preguntas y respuestas que se muestran en la página de preguntas frecuentes.', 'flacso-uruguay'),
            'public'              => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => true,
            'menu_position'       => 22,
            'menu_icon'           => 'dashicons-editor-help',
            'supports'            => ['title', 'editor', 'page-attributes', 'revisions'],
            'has_archive'         => false,
            'rewrite'             => false,
            'map_meta_cap'        => true,
        ]);
    }

    public static function render(array $args = []): string {
        $args = wp_parse_args($args, [
            'id'           => 'faq-flacso',
            'titulo'       => __('Preguntas frecuentes', 'flacso-uruguay'),
            'intro'        => '',
            'nivel_titulo' => 'h2',
        ]);

        $questions = get_posts([
            'post_type'              => self::POST_TYPE,
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'orderby'                => ['menu_order' => 'ASC', 'title' => 'ASC'],
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        $base_id = sanitize_title((string) $args['id']);
        $base_id = $base_id !== '' ? $base_id : 'faq-flacso';
        $root_id = wp_unique_id($base_id . '-');
        $heading_tag = in_array($args['nivel_titulo'], ['h1', 'h2'], true)
            ? $args['nivel_titulo']
            : 'h2';
        $question_tag = 'h1' === $heading_tag ? 'h2' : 'h3';

        ob_start();
        ?>
        <section class="flacso-faq" id="<?php echo esc_attr($root_id); ?>" data-flacso-faq>
            <header class="flacso-faq__header">
                <<?php echo tag_escape($heading_tag); ?> class="flacso-faq__title">
                    <?php echo esc_html($args['titulo']); ?>
                </<?php echo tag_escape($heading_tag); ?>>
                <?php if ($args['intro'] !== '') : ?>
                    <p class="flacso-faq__intro"><?php echo esc_html($args['intro']); ?></p>
                <?php endif; ?>
            </header>

            <?php if (!empty($questions)) : ?>
                <div class="flacso-faq__grid" aria-label="<?php echo esc_attr($args['titulo']); ?>">
                    <?php foreach ($questions as $index => $question) : ?>
                        <?php $answer_id = $root_id . '-respuesta-' . (int) $question->ID; ?>
                        <article class="flacso-faq__item" data-flacso-faq-item data-flacso-faq-order="<?php echo (int) $index; ?>">
                            <<?php echo tag_escape($question_tag); ?> class="flacso-faq__question" aria-describedby="<?php echo esc_attr($answer_id); ?>">
                                <span class="flacso-faq__number" aria-hidden="true"><?php echo esc_html((string) ($index + 1)); ?></span>
                                <span><?php echo esc_html(get_the_title($question)); ?></span>
                            </<?php echo tag_escape($question_tag); ?>>
                            <div class="flacso-faq__answer" id="<?php echo esc_attr($answer_id); ?>">
                                <?php echo apply_filters('the_content', $question->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php elseif (current_user_can('edit_posts')) : ?>
                <p class="flacso-faq__empty">
                    <?php esc_html_e('Todavía no hay preguntas frecuentes publicadas.', 'flacso-uruguay'); ?>
                </p>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    public static function admin_columns(array $columns): array {
        $result = [];
        foreach ($columns as $key => $label) {
            if ('date' === $key) {
                $result['menu_order'] = __('Orden', 'flacso-uruguay');
            }
            $result[$key] = $label;
        }
        return $result;
    }

    public static function admin_column_content(string $column, int $post_id): void {
        if ('menu_order' === $column) {
            echo esc_html((string) get_post_field('menu_order', $post_id));
        }
    }

    public static function order_admin_list($query): void {
        if (
            !is_admin()
            || !$query->is_main_query()
            || self::POST_TYPE !== $query->get('post_type')
            || $query->get('orderby')
        ) {
            return;
        }

        $query->set('orderby', ['menu_order' => 'ASC', 'title' => 'ASC']);
        $query->set('order', 'ASC');
    }

    /** Importa una sola vez las preguntas que antes estaban fijas en el shortcode. */
    public static function maybe_seed_initial_questions(): void {
        if ((int) get_option(self::SEED_OPTION, 0) >= self::SEED_VERSION) {
            return;
        }

        $counts = wp_count_posts(self::POST_TYPE);
        $existing = 0;
        if ($counts) {
            foreach (get_object_vars($counts) as $count) {
                $existing += (int) $count;
            }
        }

        if ($existing > 0) {
            update_option(self::SEED_OPTION, self::SEED_VERSION, false);
            return;
        }

        foreach (self::initial_questions() as $order => $question) {
            wp_insert_post([
                'post_type'    => self::POST_TYPE,
                'post_status'  => 'publish',
                'post_title'   => $question['question'],
                'post_content' => $question['answer'],
                'menu_order'   => $order,
            ]);
        }

        update_option(self::SEED_OPTION, self::SEED_VERSION, false);
    }

    private static function initial_questions(): array {
        return [
            [
                'question' => '¿Qué es FLACSO Uruguay?',
                'answer'   => '<p>FLACSO es una organización internacional de carácter público, regional y autónomo, creada bajo los auspicios de la UNESCO. En Uruguay, fue establecida en 2006 mediante la Ley 17.976.</p><p>Se enfoca en la producción de conocimiento social, la docencia de posgrado y la cooperación académica, promoviendo sociedades más justas, equitativas y sostenibles.</p>',
            ],
            [
                'question' => '¿Cómo estudiar en FLACSO Uruguay?',
                'answer'   => '<p>Para estudiar en FLACSO Uruguay, seguí estos pasos:</p><ul><li>Revisá la oferta académica en <a href="https://flacso.edu.uy/formacion/">flacso.edu.uy/formacion</a>.</li><li>Elegí el programa de tu interés y revisá sus requisitos de admisión.</li><li>Completá el formulario de solicitud de admisión en línea.</li><li>Una vez aceptada la postulación, recibirás la información para abonar los aranceles y formalizar la inscripción.</li><li>Ante dudas, podés contactar con la asistente académica del programa correspondiente.</li></ul>',
            ],
            [
                'question' => '¿Qué se estudia en FLACSO Uruguay?',
                'answer'   => '<p>En FLACSO Uruguay se estudian diversas áreas de las ciencias sociales, como:</p><ul><li><strong>Género e interseccionalidad</strong></li><li><strong>Educación</strong></li><li><strong>Educación Audiovisual</strong></li><li><strong>Comunicación</strong></li><li><strong>Infancias y adolescencias</strong></li><li><strong>Comprendiendo China</strong></li></ul><p>Los posgrados colocan el acento en la igualdad, la no discriminación y los derechos humanos, con enfoque interdisciplinario y regional.</p>',
            ],
            [
                'question' => '¿Por qué estudiar en FLACSO Uruguay?',
                'answer'   => '<ul><li><strong>Prestigio académico</strong>: FLACSO es una de las instituciones más reconocidas de América Latina en Ciencias Sociales.</li><li><strong>Equipo docente internacional</strong> con amplia experiencia.</li><li><strong>Enfoque interdisciplinario</strong> que fomenta pensamiento crítico.</li><li><strong>Modalidad flexible</strong> con cursadas virtuales y asincrónicas.</li><li><strong>Acompañamiento cercano</strong> y comunidad académica activa.</li></ul>',
            ],
            [
                'question' => '¿Cuánto dura estudiar en FLACSO?',
                'answer'   => '<p>Las maestrías tienen entre 18 y 24 meses de duración (3 a 4 semestres). Los diplomados y especializaciones varían según su estructura y modalidad (virtual o semipresencial).</p>',
            ],
            [
                'question' => '¿Quiénes pueden estudiar en FLACSO?',
                'answer'   => '<p>FLACSO Uruguay promueve la inclusión y la diversidad. Los posgrados requieren título universitario o terciario de al menos 4 años, pero los seminarios y cursos están abiertos a todas las personas interesadas, incluso sin título previo.</p>',
            ],
            [
                'question' => '¿Cuáles son las modalidades de cursada?',
                'answer'   => '<p>FLACSO Uruguay combina educación en línea asincrónica con encuentros sincrónicos (virtuales o presenciales vía Zoom), permitiendo cursar desde cualquier lugar y con horarios flexibles.</p>',
            ],
            [
                'question' => '¿Cuáles son las titulaciones que se brindan?',
                'answer'   => '<p>Los títulos de Maestría y Especialización son emitidos por la Secretaría General de FLACSO (Costa Rica) con Apostilla de La Haya. Los Diplomas, Diplomados y Seminarios son emitidos por FLACSO Uruguay.</p>',
            ],
            [
                'question' => '¿Hay cupos para los posgrados?',
                'answer'   => '<p>Sí. Cada cohorte cuenta con cupos limitados. Recomendamos inscribirse dentro de los plazos establecidos.</p>',
            ],
            [
                'question' => '¿Cuáles son los mecanismos de pago?',
                'answer'   => '<p><strong>Residentes en Uruguay:</strong> Transferencias bancarias (BROU), giros por RedPagos o Abitab, y Mercado Pago mediante QR.</p><p><strong>No residentes:</strong> Enlace de pago con tarjetas Visa, MasterCard o Amex, enviado por correo electrónico.</p>',
            ],
            [
                'question' => '¿Los planes de pago son flexibles? ¿Cuentan con becas?',
                'answer'   => '<p>Los pagos pueden realizarse en cuotas mensuales adaptadas a la duración de la cursada. Existen becas limitadas, sujetas a convenios institucionales. Consultá con la asistente académica para más información.</p>',
            ],
            [
                'question' => '¿Los títulos y su envío tienen costo?',
                'answer'   => '<p>Las Maestrías y Especializaciones incluyen el costo del envío de títulos con Apostilla de La Haya. Los demás programas pueden tener costo adicional si se requiere envío físico al exterior.</p>',
            ],
            [
                'question' => '¿Tienen convenios?',
                'answer'   => '<p>FLACSO Uruguay cuenta con una amplia red de convenios nacionales e internacionales que permiten cooperación institucional y bonificación de aranceles. Consultá el listado en <a href="https://flacso.edu.uy/convenios/" target="_blank" rel="noopener noreferrer"><strong>flacso.edu.uy/convenios</strong></a>.</p>',
            ],
            [
                'question' => '¿Cuál es la diferencia entre los posgrados?',
                'answer'   => '<p><strong>Maestrías</strong>: 18 a 24 meses, con trabajo final de investigación.</p><p><strong>Especializaciones</strong>: profundización teórica y metodológica; títulos con Apostilla de La Haya.</p><p><strong>Diplomados</strong>: orientados a la práctica profesional, con certificación de FLACSO Uruguay.</p><p><strong>Diplomas</strong>: formaciones breves que pueden acreditar hacia posgrados.</p><p><strong>Cursos y Seminarios</strong>: actualizaciones temáticas de corta duración.</p>',
            ],
        ];
    }
}
