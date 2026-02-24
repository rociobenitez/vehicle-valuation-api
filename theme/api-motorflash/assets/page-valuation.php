<?php
/*
* Template Name: Tasación
*
* Plantilla: page-tasacion.php
* Propósito: interfaz para el tasador online que guía al usuario por pasos
* (marca -> modelo -> versión -> año -> puertas -> km -> GF)
*
* Notas sobre ACF y Gravity Forms:
* - Se espera un campo de ACF llamado 'form' que contiene el ID del formulario de Gravity Forms.
* - Se espera 'text_bottom' y opcionalmente 'step' (array) en ACF.
* - El uso de Gravity Forms está integrado vía do_shortcode con ajax="true".
*
* IMPORTANTE: NO CAMBIAR los IDs HTML usados en este archivo: muchos están referenciados
* por el javascript (p. ej. brand-select, model-select, next-1, input_5_14, etc.).
*/

$bodyclass='page-vender';
$header='dark';

// Clases y textos para el formulario (configuración reutilizable)
$class_option_grid = 'row row-cols-2 row-cols-sm-3 row-cols-md-4 g-1 option-grid justify-content-center';
$text_btn_init     = 'Volver al inicio';
$text_btn_next     = 'Siguiente';
$text_btn_prev     = 'Atrás';
$class_btn_next    = 'btn btn-sm py-2 btn-default';
$class_btn_prev    = 'btn btn-sm py-2 btn-secondary mb-3 btn-back';

$marcas_json = '[]'; // se carga dinámicamente desde JS (API interna) para evitar cargar todo el catálogo en PHP (rendimiento)

get_header();

$title = get_the_title();
set_query_var('fields', $fields);
?>

<section class="container my-5">
  <div class="row py-5">
  
    <!-- Encabezado opcional / explicación del tasador -->

    <!-- Formulario de Tasación (contenedor principal) -->
    <div class="form-tasacion-container col-md-10 col-lg-8 mx-auto mt-4 mb-5">

        <!-- Stepper / barra de progreso -->
         <div class="position-relative">
            <nav class="mb-4" aria-label="<?php esc_attr_e('Progreso de tasación', 'text-domain-theme'); ?>">
                <ol id="tasacion-stepper" class="list-unstyled d-flex justify-content-between align-items-center gap-2 m-0" role="list">
                    <li class="step-item active" data-step="1" role="listitem" aria-current="step">
                        <span class="step-index">1</span>
                        <span class="step-label"><?php _e('Vehículo', 'text-domain-theme'); ?></span>
                    </li>
                    <li class="step-item" data-step="2" role="listitem">
                        <span class="step-index">2</span>
                        <span class="step-label"><?php _e('Propietario', 'text-domain-theme'); ?></span>
                    </li>
                    <li class="step-item" data-step="3" role="listitem">
                        <span class="step-index">3</span>
                        <span class="step-label"><?php _e('Preguntas', 'text-domain-theme'); ?></span>
                    </li>
                    <li class="step-item" data-step="4" role="listitem">
                        <span class="step-index">4</span>
                        <span class="step-label"><?php _e('Confirmación', 'text-domain-theme'); ?></span>
                    </li>
                </ol>
            </nav>
            <span class="line position-absolute top-0 start-50 translate-middle border-top" aria-hidden="true"></span>
        </div>

        <?php // Se recomienda mantener el form con method POST. Gravity Forms se encarga del submit final. ?>
        <form id="form-tasacion" method="post" class="form-tasacion position-relative my-3" novalidate>

            <!-- Mensaje para usuarios sin JS -->
            <noscript>
              <div class="alert alert-warning" role="alert">
                <?php _e('Este tasador funciona mejor con JavaScript habilitado. Si no tienes JS, por favor contacta con nosotros.', 'text-domain-theme'); ?>
              </div>
            </noscript>

            <!-- Botón para reiniciar (se muestra/oculta desde JS) -->
            <button type="button" id="btn-starter" class="btn btn-sm py-3 px-0 mb-3 position-absolute end-0 top-0 border-0 text-decoration-underline d-none" aria-hidden="true">
                <?php echo esc_html($text_btn_init); ?>
            </button>

            <!-- PASO 1: VEHÍCULO -->
            <div class="form-step d-grid justify-content-center" id="step-1" aria-labelledby="label-brand-select">
                <p class="form-title mb-2 text-center"><?php _e('Selecciona la Marca', 'text-domain-theme'); ?></p>

                <?php // Select de marcas: mantenemos los IDs originales para compatibilidad con JS ?>
                <div class="col-12 text-center">
                    <label id="label-brand-select" for="brand-select" class="visually-hidden"><?php esc_html_e('Selecciona la marca del vehículo', 'text-domain-theme'); ?></label>
                    <select id="brand-select" class="form-select form-select-size" size="6" aria-describedby="brand-help">
                        <option value=""></option>
                    </select>
                </div>

                <input type="hidden" name="selectedBrand" id="selectedBrand" />
                <input type="hidden" name="selectedBrandName" id="selectedBrandName" />

                <div class="mt-3 d-flex justify-content-center">
                    <button type="button" id="next-1" class="<?php echo esc_attr($class_btn_next); ?>" disabled aria-controls="step-2"><?php echo esc_html($text_btn_next); ?></button>
                </div>

                <p class="fs13 text-muted mt-4"><?php _e('No tasamos las marcas deshabilitadas', 'text-domain-theme'); ?>. <a class="c-black" href="/contacto"><?php _e('Contáctanos', 'text-domain-theme'); ?></a></p>
            </div>

            <!-- PASO 2: MODELO -->
            <div class="form-step d-none" id="step-2" aria-labelledby="label-model-select">
                <button type="button" class="<?php echo esc_attr($class_btn_prev); ?>" id="prev-step-2" aria-controls="step-1"><?php echo esc_html($text_btn_prev); ?></button>
                <p class="form-title mb-2 text-center"><?php _e('Selecciona el Modelo', 'text-domain-theme'); ?></p>

                <div class="text-center mb-3">
                    <span class="fs14">
                        <?php _e('Marca seleccionada:', 'text-domain-theme'); ?>
                        <strong id="selectedBrandNameDisplay" class="fw500" aria-live="polite"></strong>
                    </span>
                </div>

                <div class="col-12 text-center">
                    <label id="label-model-select" for="model-select" class="visually-hidden"><?php esc_html_e('Selecciona el modelo', 'text-domain-theme'); ?></label>
                    <select id="model-select" class="form-select form-select-size" size="6" aria-describedby="model-help">
                        <option value=""></option>
                    </select>
                </div>

                <input type="hidden" name="selectedModel" id="selectedModel" />
                <input type="hidden" name="selectedModelName" id="selectedModelName" />

                <div class="mt-3 d-flex justify-content-center">
                    <button type="button" id="next-2" class="<?php echo esc_attr($class_btn_next); ?>" disabled aria-controls="step-3"><?php echo esc_html($text_btn_next); ?></button>
                </div>
            </div>

            <!-- PASO 3: VERSIÓN -->
            <div class="form-step d-none" id="step-3" aria-labelledby="label-version-select">
                <button type="button" class="<?php echo esc_attr($class_btn_prev); ?>" id="prev-step-3" aria-controls="step-2"><?php echo esc_html($text_btn_prev); ?></button>
                <p class="form-title mb-2 text-center"><?php _e('Selecciona la Versión', 'text-domain-theme'); ?></p>

                <div class="text-center mb-3">
                    <span class="fs14">
                        <?php _e('Seleccionado:', 'text-domain-theme'); ?>
                        <strong id="summaryBrand" class="fw500"></strong>
                        <span class="mx-1">/</span>
                        <strong id="summaryModel" class="fw500"></strong>
                    </span>
                </div>

                <div class="col-12 text-center">
                    <label id="label-version-select" for="version-select" class="visually-hidden"><?php esc_html_e('Selecciona la versión', 'text-domain-theme'); ?></label>
                    <select id="version-select" class="form-select form-select-size" aria-describedby="version-help">
                        <option value=""><?php _e('Selecciona una versión', 'text-domain-theme'); ?></option>
                    </select>
                </div>

                <input type="hidden" name="selectedVersion" id="selectedVersion" />
                <input type="hidden" name="selectedVersionName" id="selectedVersionName" />

                <div class="mt-3 d-flex justify-content-center">
                    <button type="button" id="next-3" class="<?php echo esc_attr($class_btn_next); ?>" disabled aria-controls="step-4"><?php echo esc_html($text_btn_next); ?></button>
                </div>
            </div>

            <!-- PASO 4: AÑO -->
            <div class="form-step d-none" id="step-4" aria-labelledby="label-year-select">
                <button type="button" class="<?php echo esc_attr($class_btn_prev); ?>" id="prev-step-4" aria-controls="step-3"><?php echo esc_html($text_btn_prev); ?></button>
                <p class="form-title mb-2 text-center"><?php _e('Año', 'text-domain-theme'); ?></p>

                <div class="col-12 text-center">
                    <label id="label-year-select" for="year-select" class="visually-hidden"><?php esc_html_e('Selecciona el año del vehículo', 'text-domain-theme'); ?></label>
                    <select id="year-select" class="form-select" aria-describedby="year-message">
                        <option value=""><?php _e('Selecciona el año', 'text-domain-theme'); ?></option>
                    </select>
                </div>

                <input type="hidden" name="selectedYear" id="selectedYear" />
                <input type="hidden" name="selectedYearName" id="selectedYearName" />

                <div class="message-alert col-md-9 text-center fs14 mx-auto mt-3" id="message-no-version" style="display:none;" role="alert" aria-live="polite">
                    <p class="mb-3"><?php _e('Lo sentimos, no podemos tasar de forma online vehículos con ', 'text-domain-theme'); ?><strong><?php _e('más de 9 años', 'text-domain-theme'); ?></strong><?php _e(' de antigüedad. Contacta con nosotros para más información.', 'text-domain-theme'); ?></p>
                    <a href="/contacto/" class="btn btn-sm py-2 btn-primary"><?php _e('Contactar', 'text-domain-theme'); ?></a>
                </div>

                <div class="mt-3 d-flex justify-content-center">
                    <button type="button" id="next-4" class="<?php echo esc_attr($class_btn_next); ?>" disabled style="display:none;" aria-controls="step-5"><?php echo esc_html($text_btn_next); ?></button>
                </div>
            </div>

            <!-- PASO 5: PUERTAS -->
            <div class="form-step d-none" id="step-5" aria-labelledby="label-doors-select">
                <button type="button" class="<?php echo esc_attr($class_btn_prev); ?>" id="prev-step-5" aria-controls="step-4"><?php echo esc_html($text_btn_prev); ?></button>
                <p class="form-title mb-2 text-center"><?php _e('Número de Puertas', 'text-domain-theme'); ?></p>

                <div class="col-12 text-center">
                    <label id="label-doors-select" for="doors-select" class="visually-hidden"><?php esc_html_e('Selecciona número de puertas', 'text-domain-theme'); ?></label>
                    <select id="doors-select" class="form-select" aria-describedby="doors-help">
                        <option value=""><?php _e('Selecciona nº de puertas', 'text-domain-theme'); ?></option>
                    </select>
                </div>

                <input type="hidden" name="selectedDoors" id="selectedDoors" />
                <input type="hidden" name="selectedDoorsName" id="selectedDoorsName" />

                <div class="mt-3 d-flex justify-content-center">
                    <button type="button" id="next-5" class="<?php echo esc_attr($class_btn_next); ?>" disabled aria-controls="step-6"><?php echo esc_html($text_btn_next); ?></button>
                </div>
            </div>

            <!-- PASO 6: KILOMETRAJE y MATRÍCULA -->
            <div class="form-step d-none" id="step-6" aria-labelledby="label-mileage">
                <button type="button" class="<?php echo esc_attr($class_btn_prev); ?>" id="prev-step-6" aria-controls="step-5"><?php echo esc_html($text_btn_prev); ?></button>
                <div class="d-flex justify-content-center gap-4 flex-column flex-md-row align-items-stretch align-items-md-center" aria-labelledby="label-mileage label-license-plate">
                    <div class="mb-3">
                        <label id="label-mileage" for="mileage" class="form-label form-title mb-2"><?php _e('Kilometraje', 'text-domain-theme'); ?></label>
                        <input type="number" name="mileage" id="mileage" class="form-control" placeholder="Ej: 50000" inputmode="numeric" min="0" step="1" required aria-describedby="km-message" />

                        <div class="text-center fs14 mx-auto mt-3" id="message-too-many-km" style="display:none;" role="alert" aria-live="polite">
                            <p class="mb-2">
                                <?php _e('Solo podemos tasar de forma online vehículos con un ', 'text-domain-theme'); ?>
                                <strong><?php _e('máximo de 140.000 km.', 'text-domain-theme'); ?></strong>
                                <?php _e('Contacta con nosotros para más información.', 'text-domain-theme'); ?>
                            </p>
                            <a href="/contacto/" class="btn btn-sm py-2 btn-primary mt-2"><?php _e('Contactar', 'text-domain-theme'); ?></a>
                        </div>
                    </div>

                    <div class="pt-4">
                        <label for="licensePlate" class="form-label form-title mb-2"><?php _e('Matrícula', 'text-domain-theme'); ?></label>
                        <input type="text" name="licensePlate" id="licensePlate" class="form-control" placeholder="Ej: 1234 ABC" maxlength="12" />
                    </div>
                </div>

                <?php // Resultado de tasación (zona visible, manejada por JS). role=status para anunciar cambios ?>
                <div id="tasacion-result" class="d-none mx-auto" role="status" aria-live="polite"></div>

                <div class="mt-4 d-flex justify-content-center">
                    <button type="button" id="next-6" class="<?php echo esc_attr($class_btn_next); ?>" disabled aria-controls="step-7"><?php echo esc_html($text_btn_next); ?></button>
                </div>
            </div>
        </form>

        <!-- PASO 7: PROPIETARIO (Gravity Forms) -->
        <div class="form-step d-none gform_tasacion tasaciones__form" id="step-7" aria-live="polite">
            <button type="button" class="<?php echo esc_attr($class_btn_prev); ?>" id="prev-step-7" aria-controls="step-6"><?php echo esc_html($text_btn_prev); ?></button>

            <?php echo do_shortcode('[gravityform id="'. intval($fields["form"]) .'" title="false" description="false" ajax="true"]'); ?>

            <?php
            // Gravity Forms: campos ocultos esperados para integration frontend:
            // (comprobar que los IDs coinciden con los del formulario en GF)
            // - input_[ID]_13 -> resumen de datos
            // - input_[ID]_14 -> importe base (autobiz) o mensaje descriptivo
            // - input_[ID]_29 -> importe final (calculado en cliente) o mensaje descriptivo
            ?>
        </div>

    </div>

    <!-- Texto adicional debajo del formulario (contenido ACF) -->
    <div class="col-12 col-md-10 col-lg-8 mx-auto">
        <div class="text-bottom"><?php echo wp_kses_post($fields['text_bottom'] ?? ''); ?></div>
    </div>
  </div>
</section>

<?php get_footer();
