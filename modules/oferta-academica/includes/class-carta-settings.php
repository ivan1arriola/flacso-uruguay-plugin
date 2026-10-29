<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Textos institucionales de la carta pública de una Oferta Académica.
 *
 * Fuente única de claves, valores por defecto, reglas de visibilidad y
 * saneamiento. Los consume el tema para mostrar la carta, los edita la pantalla
 * "Textos de la carta" (FLACSO_Carta_Settings_Admin) y la API de ajustes
 * (/wp-json/flacso/v1/ofertas/settings).
 *
 * Reglas:
 * - Una opción inexistente devuelve su valor por defecto; una opción guardada
 *   vacía devuelve vacío, de modo que quien edita puede ocultar un texto.
 * - Cada texto tiene una condición (CONDITIONS) que decide en qué ofertas se
 *   muestra. El tema usa la misma condición que la pantalla de administración
 *   describe: `visible_text()`.
 * - Marcador disponible: {anio} (año de inicio de la cohorte).
 */
final class FLACSO_Carta_Settings {
    private const OPTION_PREFIX = 'flacso_';

    private const TYPE_TEXT = 'text';
    private const TYPE_HTML = 'html';
    private const TYPE_EMAIL = 'email';

    public const COND_ALWAYS = 'siempre';
    public const COND_APOSTILLE = 'apostilla';
    public const COND_NO_APOSTILLE = 'sin_apostilla';
    public const COND_MEC = 'mec';
    public const COND_NETWORK = 'red';
    public const COND_IIN = 'iin';
    public const COND_PRESENTIAL = 'presenciales';
    public const COND_NO_CONTACT = 'sin_contacto';

    /**
     * Condición => [descripción de la regla, campo de la oferta, se niega].
     *
     * @return array<string,array{0:string,1:string,2:bool}>
     */
    private static function conditions(): array {
        return [
            self::COND_ALWAYS => ['Se muestra en todas las cartas.', '', false],
            self::COND_APOSTILLE => ['Solo en ofertas con “Apostillado de La Haya” marcado (reconocimiento internacional).', 'reconocimiento_internacional', false],
            self::COND_NO_APOSTILLE => ['Solo en ofertas sin “Apostillado de La Haya” marcado.', 'reconocimiento_internacional', true],
            self::COND_MEC => ['Solo en ofertas con “Reconocimiento oficial MEC” marcado.', 'reconocido_mec', false],
            self::COND_NETWORK => ['Solo en ofertas con reconocimiento en la red FLACSO marcado.', 'reconocimiento_red_flacso', false],
            self::COND_IIN => ['Solo en ofertas con el convenio IIN-OEA marcado.', 'convenio_iin_oea', false],
            self::COND_PRESENTIAL => ['Solo en ofertas con “Incluye instancias exclusivamente presenciales” marcado.', 'carta_instancias_presenciales', false],
            self::COND_NO_CONTACT => ['Solo cuando la oferta no tiene un contacto marcado en Coordinación académica.', '', false],
        ];
    }

    /**
     * Grupos de la pantalla de administración: clave => [título, dónde se ve].
     *
     * @return array<string,array{0:string,1:string}>
     */
    public static function groups(): array {
        return [
            'portada' => ['Distintivos de la portada', 'Portada de la carta, debajo del título.'],
            'practica' => ['Información práctica', 'Carta · sección 04 “Información práctica”.'],
            'confianza' => ['Por qué estudiar en FLACSO', 'Carta · sección 05.'],
            'contacto' => ['Contacto institucional', 'Carta · sección 06 “Contacto académico” y consultas.'],
        ];
    }

    /**
     * clave => [tipo, valor por defecto, grupo, etiqueta, condición, multilínea]
     *
     * @return array<string,array{0:string,1:string,2:string,3:string,4:string,5:bool}>
     */
    private static function definitions(): array {
        return [
            'carta_badge_mec' => [self::TYPE_TEXT, 'Reconocida por el M.E.C', 'portada', 'Distintivo: reconocimiento MEC', self::COND_MEC, false],
            'carta_badge_apostilla' => [self::TYPE_TEXT, 'Apostillado de La Haya', 'portada', 'Distintivo: Apostillado de La Haya', self::COND_APOSTILLE, false],
            'carta_badge_red' => [self::TYPE_TEXT, 'Título con validez y reconocimiento en la red del Sistema Internacional FLACSO.', 'portada', 'Distintivo: red FLACSO', self::COND_NETWORK, false],
            'carta_badge_iin' => [self::TYPE_TEXT, 'Convenio IIN-OEA', 'portada', 'Distintivo: convenio IIN-OEA', self::COND_IIN, false],
            'carta_badge_presenciales' => [self::TYPE_TEXT, 'Incluye instancias exclusivamente presenciales', 'portada', 'Distintivo: instancias presenciales', self::COND_PRESENTIAL, false],

            'carta_pago_dolares' => [self::TYPE_TEXT, 'Para pagos en dólares, las cuotas se mantendrán fijas durante toda la cursada.', 'practica', 'Pagos en dólares', self::COND_ALWAYS, true],
            'carta_pago_pesos' => [self::TYPE_TEXT, 'Para pagos en pesos, las cuotas permanecerán fijas durante todo {anio}, y a partir del siguiente año se ajustarán en enero, por debajo de la inflación interanual.', 'practica', 'Pagos en pesos', self::COND_ALWAYS, true],
            'carta_vencimiento_cuotas' => [self::TYPE_TEXT, 'Las cuotas vencen el día 15 de cada mes.', 'practica', 'Vencimiento de las cuotas', self::COND_ALWAYS, false],
            'carta_titulo_exterior' => [self::TYPE_TEXT, 'El título se expide en el exterior y tiene un costo asociado de USD 150, que incluye el trámite de Apostilla de La Haya.', 'practica', 'Emisión del título con costo (Apostilla)', self::COND_APOSTILLE, true],
            'carta_titulo_sin_costo' => [self::TYPE_TEXT, 'La emisión del título no tiene costo adicional. Tampoco existe costo asociado por concepto de matrícula.', 'practica', 'Emisión del título sin costo', self::COND_NO_APOSTILLE, true],
            'carta_envio_titulos' => [self::TYPE_TEXT, 'El costo de envío de títulos al exterior dependerá del país destino y del courier elegido para su recepción.', 'practica', 'Envío de títulos al exterior', self::COND_ALWAYS, true],
            'carta_financiacion_label' => [self::TYPE_TEXT, 'Consultá por otros planes de financiación en', 'practica', 'Planes de financiación (seguido del correo)', self::COND_ALWAYS, false],

            'carta_mas_info_section_title' => [self::TYPE_TEXT, 'Más información', 'confianza', 'Título de la sección', self::COND_ALWAYS, false],
            'carta_mas_info_trayectoria_title' => [self::TYPE_TEXT, 'Trayectoria Académica', 'confianza', 'Trayectoria: título', self::COND_ALWAYS, false],
            'carta_mas_info_trayectoria_html' => [self::TYPE_HTML, '<p>Nuestra <strong>Facultad de Posgrados</strong> busca formar a sus estudiantes a nivel <strong>académico, profesional y laboral</strong>. Nos distinguen <strong>19 años de trayectoria</strong> a nivel nacional y más de <strong>65 años a nivel internacional</strong>. Además, más de <strong>7000 personas egresadas</strong> de FLACSO Uruguay trabajan en el ámbito público y privado.</p>', 'confianza', 'Trayectoria: texto', self::COND_ALWAYS, true],
            'carta_mas_info_gestion_title' => [self::TYPE_TEXT, 'Gestión Académica', 'confianza', 'Gestión: título', self::COND_ALWAYS, false],
            'carta_mas_info_gestion_html' => [self::TYPE_HTML, '<p>Nos distingue un sistema de <strong>gestión académica eficiente y cercano</strong>, que acompaña de forma <strong>personalizada</strong> a cada estudiante y garantiza <strong>altos niveles de egreso, superiores al 90%</strong>.</p>', 'confianza', 'Gestión: texto', self::COND_ALWAYS, true],

            'carta_correo_inscripciones' => [self::TYPE_EMAIL, 'inscripciones@flacso.edu.uy', 'contacto', 'Correo de inscripciones', self::COND_ALWAYS, false],
            'carta_telefono' => [self::TYPE_TEXT, '+598 2481 7459', 'contacto', 'Teléfono', self::COND_ALWAYS, false],
            'carta_contacto_nombre' => [self::TYPE_TEXT, 'Inscripciones FLACSO Uruguay', 'contacto', 'Contacto de respaldo: nombre', self::COND_NO_CONTACT, false],
            'carta_contacto_rol' => [self::TYPE_TEXT, 'Asistencia Académica', 'contacto', 'Contacto de respaldo: rol', self::COND_NO_CONTACT, false],
        ];
    }

    /** @return string[] */
    public static function keys(): array {
        return array_keys(self::definitions());
    }

    public static function has(string $key): bool {
        return isset(self::definitions()[$key]);
    }

    public static function option_name(string $key): string {
        return self::OPTION_PREFIX . $key;
    }

    public static function default_value(string $key): string {
        return self::definitions()[$key][1] ?? '';
    }

    /**
     * Metadatos para la pantalla de administración.
     *
     * @return array{type:string,default:string,group:string,label:string,condition:string,rule:string,multiline:bool}|null
     */
    public static function describe(string $key): ?array {
        $definition = self::definitions()[$key] ?? null;
        if ($definition === null) {
            return null;
        }
        return [
            'type' => $definition[0],
            'default' => $definition[1],
            'group' => $definition[2],
            'label' => $definition[3],
            'condition' => $definition[4],
            'rule' => self::conditions()[$definition[4]][0],
            'multiline' => $definition[5],
        ];
    }

    public static function get(string $key): string {
        if (!self::has($key)) {
            return '';
        }
        $stored = get_option(self::option_name($key), null);
        return $stored === null || $stored === false ? self::default_value($key) : (string) $stored;
    }

    /** @return array<string,string> */
    public static function all(): array {
        $values = [];
        foreach (self::keys() as $key) {
            $values[$key] = self::get($key);
        }
        return $values;
    }

    /**
     * ¿La condición del texto se cumple para esta oferta?
     *
     * @param array<string,mixed> $academic Datos académicos de la oferta.
     */
    public static function applies(string $key, array $academic): bool {
        $condition = self::definitions()[$key][4] ?? self::COND_ALWAYS;
        [, $field, $negate] = self::conditions()[$condition];
        if ($field === '') {
            return true;
        }
        $active = !empty($academic[$field]);
        return $negate ? !$active : $active;
    }

    /**
     * Texto listo para mostrar: vacío si la regla no se cumple o si quien edita
     * lo dejó vacío. Reemplaza los marcadores.
     *
     * @param array<string,mixed> $academic
     * @param array<string,string> $replacements Ej.: ['{anio}' => '2027'].
     */
    public static function visible_text(string $key, array $academic, array $replacements = []): string {
        if (!self::applies($key, $academic)) {
            return '';
        }
        return trim(strtr(self::get($key), $replacements));
    }

    /** @param mixed $value */
    public static function sanitize(string $key, $value): string {
        $type = self::definitions()[$key][0] ?? self::TYPE_TEXT;
        $value = is_scalar($value) ? (string) $value : '';
        if ($type === self::TYPE_HTML) {
            return wp_kses_post($value);
        }
        if ($type === self::TYPE_EMAIL) {
            return sanitize_email($value);
        }
        return sanitize_text_field($value);
    }

    /**
     * Guarda los valores recibidos; ignora claves desconocidas (por ejemplo,
     * las tarjetas de financiación retiradas).
     *
     * @param array<string,mixed> $payload
     */
    public static function update_from_payload(array $payload): void {
        foreach (self::keys() as $key) {
            if (isset($payload[$key])) {
                update_option(self::option_name($key), self::sanitize($key, $payload[$key]));
            }
        }
    }

    /** Vuelve al valor por defecto (borra la opción guardada). */
    public static function reset(string $key): void {
        if (self::has($key)) {
            delete_option(self::option_name($key));
        }
    }
}
