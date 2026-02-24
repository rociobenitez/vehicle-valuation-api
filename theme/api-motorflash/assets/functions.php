<?php
/**
 * TASACIÓN (API MOTORFLASH)
 * 
 * - FORMULARIO DE TASACIÓN
 * - INTEGRACIÓN API EXTERNA MOTORFLASH
 * - AUTENTICACIÓN
 * - AJAX PARA CARGAR DATOS FORMULARIO
 */

/**
 * MOTORFLASH - Token + Request helper
 * 
 * @return string|false El token de acceso o false en caso de error.
 */
function obtener_motorflash_token() {
    $cached_token = get_transient('motorflash_token');
    if ($cached_token) return $cached_token;

    if (!defined('MOTORFLASH_CLIENT_ID') || !defined('MOTORFLASH_CLIENT_SECRET')) {
        error_log('Motorflash: credenciales no definidas.');
        return false;
    }

    $url = 'https://apimf.motorflash.com/api/token';
    $payload = array(
        'client_id'     => (string) MOTORFLASH_CLIENT_ID,
        'client_secret' => (string) MOTORFLASH_CLIENT_SECRET,
    );

    $response = wp_remote_post($url, array(
        'method'  => 'POST',
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ),
        'body' => wp_json_encode($payload),
    ));

    if (is_wp_error($response)) {
        error_log('Motorflash token WP_ERROR: ' . $response->get_error_message());
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    $raw  = wp_remote_retrieve_body($response);

    $data  = json_decode($raw, true);
    $token = $data[0]['access_token'] ?? $data['access_token'] ?? null;

    if ($token) {
        set_transient('motorflash_token', $token, 55 * MINUTE_IN_SECONDS);
        return $token;
    }

    return false;
}

/**
 * POST genérico a Motorflash (todo es /api/* con Bearer)
 * 
 * @param string $endpoint El endpoint de la API (ej: /api/jato)
 * @param array $body El cuerpo de la petición, se convertirá a JSON
 * @return array|false La respuesta decodificada o false en caso de error.
 */
function motorflash_request($endpoint, $body = array()) {
    $token = obtener_motorflash_token();
    if (!$token) return false;

    $url = 'https://apimf.motorflash.com' . $endpoint;

    $args = array(
        'method'  => 'POST',
        'timeout' => 30,
        'headers' => array(
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ),
        'body' => wp_json_encode($body),
    );

    $response = wp_remote_post($url, $args);
    if (is_wp_error($response)) {
        error_log('Error en motorflash_request: ' . $response->get_error_message());
        return false;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    return $data;
}

/**
 * Obtener marcas desde la API de Motorflash, con cacheo de 24h
 * 
 * @return array|false Array de marcas o false en caso de error.
 */
function obtener_marcas() {
    // Cache 24h para no saturar la API y mejorar rendimiento
    $cached = get_transient('motorflash_marcas');
    if ($cached) return $cached;

    // Fecha sin guiones en /api/jato
    $fecha = date_i18n('Ymd');

    $res = motorflash_request('/api/jato', array(
        'entrada' => array(
            'fecha' => $fecha,
        ),
        'salida' => array('marca'),
    ));

    $marcas_raw = $res['data']['marca'] ?? array();
    if (empty($marcas_raw) || !is_array($marcas_raw)) return false;

    $marcas = array_map(function ($m) {
        return array(
            'id'   => $m['ID'] ?? '',
            'name' => $m['NOMBRE'] ?? '',
        );
    }, $marcas_raw);

    // Filtra entradas vacías por si acaso
    $marcas = array_values(array_filter($marcas, function ($m) {
        return !empty($m['id']) && !empty($m['name']);
    }));

    set_transient('motorflash_marcas', $marcas, DAY_IN_SECONDS);
    return $marcas;
}

/**
 * Handler AJAX para obtener las marcas.
 */
function ajax_obtener_marcas() {
   $marcas = obtener_marcas();
   if ( !empty($marcas) && is_array($marcas) ) {
       wp_send_json_success($marcas);
   } else {
       wp_send_json_error('No se encontraron marcas.');
   }
}
add_action('wp_ajax_obtener_marcas', 'ajax_obtener_marcas');
add_action('wp_ajax_nopriv_obtener_marcas', 'ajax_obtener_marcas');

/**
 * Obtener modelos de una marca específica desde la API de Motorflash
 * 
 * @param string|int $marca_id El ID de la marca para la que se quieren obtener los modelos.
 * @return array|false Array de modelos o false en caso de error.
 */
function obtener_modelos_por_marca($marca_id) {
    $marca_id = trim((string) $marca_id);
    if ($marca_id === '') return false;

    // Cache 24h por marca
    $cache_key = 'motorflash_modelos_' . md5($marca_id);
    $cached = get_transient($cache_key);
    if ($cached) return $cached;

    $fecha = date_i18n('Ymd');

    $res = motorflash_request('/api/jato', array(
        'entrada' => array(
            'fecha' => $fecha,
            'marca' => $marca_id,
        ),
        'salida' => array('modelo'),
    ));

    $modelos_raw = $res['data']['modelo'] ?? array();
    if (empty($modelos_raw) || !is_array($modelos_raw)) return false;

    // Normalizar al formato que espera el JS: [{id,name}, ...]
    $modelos = array_map(function ($m) {
        $id   = $m['ID'] ?? '';
        $name = $m['NOMBRE'] ?? $id;

        return array(
            'id'   => (string) $id,
            'name' => (string) $name,
        );
    }, $modelos_raw);

    $modelos = array_values(array_filter($modelos, function ($m) {
        return !empty($m['id']) && !empty($m['name']);
    }));

    set_transient($cache_key, $modelos, DAY_IN_SECONDS);
    return $modelos;
}

/**
 * Handler AJAX para obtener los modelos según la marca seleccionada.
 */
function ajax_obtener_modelos_por_marca() {
    $marca_id = isset($_POST['makeId']) ? sanitize_text_field(wp_unslash($_POST['makeId'])) : '';
    $modelos = obtener_modelos_por_marca($marca_id);

    if (!empty($modelos) && is_array($modelos)) {
        wp_send_json_success($modelos);
    } else {
        wp_send_json_error('No se encontraron modelos.');
    }
}
add_action('wp_ajax_obtener_modelos_por_marca', 'ajax_obtener_modelos_por_marca');
add_action('wp_ajax_nopriv_obtener_modelos_por_marca', 'ajax_obtener_modelos_por_marca');

/**
 * Obtener años disponibles para una marca y modelo específicos desde la API de Motorflash
 * 
 * @param string|int $marca El ID de la marca.
 * @param string|int $modelo El ID del modelo.
 * @return array|false Array de años o false en caso de error.
 */
function motorflash_obtener_anios($marca, $modelo) {
   $marca  = trim((string) $marca);
   $modelo = trim((string) $modelo);
   if ($marca === '' || $modelo === '') return false;

   $cache_key = 'mf_years_' . md5($marca . '|' . $modelo);

   $cached = get_transient($cache_key);
   if (is_array($cached) && !empty($cached)) return $cached;

   $fecha_catalogo = date_i18n('Ymd');

   $res = motorflash_request('/api/jato', array(
      'entrada' => array(
         'fecha'  => $fecha_catalogo,
         'marca'  => $marca,
         'modelo' => $modelo,
      ),
      'salida' => array('fecha'),
   ));

   $raw = $res['data']['fecha'] ?? null;
   if (empty($raw) || !is_array($raw)) return false;

   if (isset($raw['MIN'], $raw['MAX'])) {
      $min = (int) $raw['MIN'];
      $max = (int) $raw['MAX'];
      if ($min <= 0 || $max <= 0 || $min > $max) return false;

      $years = range($min, $max);
      rsort($years);
      $years = array_values($years);

      set_transient($cache_key, $years, DAY_IN_SECONDS);

      return $years;
   }

   return false;
}

/**
 * Obtener número de puertas disponibles para una combinación marca-modelo-año desde la API de Motorflash
 * 
 * @param string|int $marca El ID de la marca.
 * @param string|int $modelo El ID del modelo.
 * @param int $year El año del vehículo.
 * @return array|false Array de números de puertas o false en caso de error.
 */
function motorflash_obtener_puertas($marca, $modelo, $year) {
    $marca  = trim((string) $marca);
    $modelo = trim((string) $modelo);
    $year   = (int) $year;

    if ($marca === '' || $modelo === '' || $year <= 0) return false;

    $cache_key = 'mf_doors_' . md5($marca . '|' . $modelo . '|' . $year);
    $cached = get_transient($cache_key);
    if ($cached) return $cached;

    $fecha = sprintf('%04d0101', $year);

    $res = motorflash_request('/api/jato', array(
        'entrada' => array(
            'fecha'  => $fecha,
            'marca'  => $marca,
            'modelo' => $modelo,
        ),
        'salida' => array('puertas'),
    ));

    $raw = $res['data']['puertas'] ?? array();
    if (empty($raw) || !is_array($raw)) return false;

    $doors = array();
    foreach ($raw as $item) {
        $v = is_array($item) ? ($item['ID'] ?? $item['NOMBRE'] ?? '') : $item;
        $n = (int) preg_replace('/\D/', '', (string) $v);
        if ($n > 0) $doors[] = $n;
    }

    $doors = array_values(array_unique($doors));
    sort($doors);

    set_transient($cache_key, $doors, DAY_IN_SECONDS);
    return $doors;
}

/**
 * Handler AJAX para obtener los años según la marca y modelo seleccionados.
 */
function ajax_obtener_datos_por_marca_modelo() {
    $makeId  = isset($_POST['makeId']) ? sanitize_text_field(wp_unslash($_POST['makeId'])) : '';
    $modelId = isset($_POST['modelId']) ? sanitize_text_field(wp_unslash($_POST['modelId'])) : '';
    $dataKey = isset($_POST['dataKey']) ? sanitize_text_field(wp_unslash($_POST['dataKey'])) : '';

    if ($makeId === '' || $modelId === '' || $dataKey === '') {
        wp_send_json_error('Parámetros makeId, modelId o dataKey inválidos.');
    }

    if ($dataKey === 'years') {
      $years = motorflash_obtener_anios($makeId, $modelId);
      if (!empty($years)) wp_send_json_success($years);
      wp_send_json_error('No se encontraron años.');
   }

   if ($dataKey === 'doors') {
      // $year = isset($_POST['year']) ? (int) $_POST['year'] : 0;

      $year = 0;

      // intenta con varios nombres posibles (según el JS)
      foreach (array('year', 'registrationYear', 'selectedYear', 'anio', 'yearId') as $key) {
         if (isset($_POST[$key])) {
            $year = (int) sanitize_text_field(wp_unslash($_POST[$key]));
            if ($year > 0) break;
         }
      }

      $doors = motorflash_obtener_puertas($makeId, $modelId, $year);
      if (!empty($doors)) wp_send_json_success($doors);
      wp_send_json_error(array(
         'message' => 'No hay opciones de puertas para esa combinación (marca/modelo/año). Prueba con otro año o modelo.',
      ));
   }

    wp_send_json_error('dataKey no soportado aún en Motorflash.');
}
add_action('wp_ajax_obtener_datos_por_marca_modelo', 'ajax_obtener_datos_por_marca_modelo');
add_action('wp_ajax_nopriv_obtener_datos_por_marca_modelo', 'ajax_obtener_datos_por_marca_modelo');

/**
 * Obtener versiones disponibles para una combinación marca-modelo-año-puertas desde la API de Motorflash
 * 
 * @param string|int $marca El ID de la marca.
 * @param string|int $modelo El ID del modelo.
 * @param int $year El año del vehículo.
 * @param int $doors El número de puertas del vehículo.
 * @return array|false Array de versiones o false en caso de error.
 */
function motorflash_obtener_versiones($marca, $modelo, $year, $doors = 0) {
    $marca  = trim((string) $marca);
    $modelo = trim((string) $modelo);
    $year   = (int) $year;
    $doors  = (int) $doors;

    if ($marca === '' || $modelo === '' || $year <= 0) return false;

    $cache_key = 'mf_versions_' . md5($marca . '|' . $modelo . '|' . $year . '|' . $doors);
    $cached = get_transient($cache_key);
    if (is_array($cached) && !empty($cached)) return $cached;

    $entrada = array(
        'fecha'  => sprintf('%04d0101', $year),
        'marca'  => $marca,
        'modelo' => $modelo,
    );

    if ($doors > 0) {
        $entrada['puertas'] = $doors;
    }

    $res = motorflash_request('/api/jato', array(
        'entrada' => $entrada,
        'salida'  => array('version'),
    ));

    $versions_raw = $res['data']['version'] ?? null;

    if (empty($versions_raw) || !is_array($versions_raw)) return false;
    $out = array();

    foreach ($versions_raw as $v) {
        $name = is_array($v) ? ($v['NOMBRE'] ?? $v['ID'] ?? '') : (string) $v;
        $name = trim((string) $name);
        if ($name === '') continue;
        $id = is_array($v) ? ($v['vehicleId'] ?? $v['VEHICLEID'] ?? $v['ID'] ?? $name) : $name;

        $out[] = array(
            'id'   => (string) $id,
            'name' => (string) $name,
        );
    }

   if (!empty($out)) {
      set_transient($cache_key, $out, DAY_IN_SECONDS);
      return $out;
   }

   return false;
}

/**
 * Handler AJAX para obtener las versiones según la marca, modelo, año y puertas seleccionados.
 */
function ajax_obtener_versiones_por_modelo() {
    $makeId  = isset($_POST['makeId']) ? sanitize_text_field(wp_unslash($_POST['makeId'])) : '';
    $modelId = isset($_POST['modelId']) ? sanitize_text_field(wp_unslash($_POST['modelId'])) : '';
    $year    = isset($_POST['year']) ? (int) sanitize_text_field(wp_unslash($_POST['year'])) : 0;
    $doors   = isset($_POST['doors']) ? (int) sanitize_text_field(wp_unslash($_POST['doors'])) : 0;

    $versions = motorflash_obtener_versiones($makeId, $modelId, $year, $doors);

    if (!empty($versions) && is_array($versions)) {
        wp_send_json_success($versions);
    } else {
        wp_send_json_error('No se encontraron versiones.');
    }
}
add_action('wp_ajax_obtener_versiones_por_modelo', 'ajax_obtener_versiones_por_modelo');
add_action('wp_ajax_nopriv_obtener_versiones_por_modelo', 'ajax_obtener_versiones_por_modelo');

/**
 * Obtener la estimación de un vehículo específico desde la API de Motorflash
 * 
 * @param string|int $vehicleId El ID del vehículo (obtenido en el paso de versiones).
 * @param int $year El año de registro del vehículo.
 * @param int $km El número de kilómetros del vehículo.
 * @return array|false Array con la estimación o false en caso de error.
 */
function motorflash_get_estimation($vehicleId, $year, $km) {
   $vehicleId = trim((string) $vehicleId);
   $year = (int) $year;
   $km   = (int) $km;

   if ($vehicleId === '' || $year <= 0 || $km < 0) {
   return array('ok' => false, 'message' => 'Parámetros inválidos.');
   }

   $body = array(
      'vehicleId'        => $vehicleId,
      'registrationDate' => sprintf('%04d-01-01', $year),
      'plate'            => ' ', // obligatorio: un espacio
      'numKm'            => (string) $km,
   );

   $res = motorflash_request('/api/getEstimation', $body);
   if (!is_array($res)) {
      return array('ok' => false, 'message' => 'Respuesta inválida de Motorflash.');
   }

   $httpCode = isset($res['httpCode']) ? (int) $res['httpCode'] : 0;
   if ($httpCode !== 200) {
      $msg = trim((string) ($res['httpMessage'] ?? 'Error en Motorflash.'));
      return array('ok' => false, 'message' => $msg);
   }

   if (!isset($res['estimation'])) {
      return array('ok' => false, 'message' => 'Motorflash no devolvió estimation.');
   }

   return array(
      'ok' => true,
      'estimation' => (int) $res['estimation'],
   );
}

function ajax_calcular_tasacion() {
   $vehicleId = '';
   if (!empty($_POST['version'])) {
      $vehicleId = sanitize_text_field(wp_unslash($_POST['version']));
   }

   $year = 0;
   if (!empty($_POST['year'])) {
      $year = (int) sanitize_text_field(wp_unslash($_POST['year']));
   }

   $km = 0;
   if (isset($_POST['mileage']) && $_POST['mileage'] !== '') {
      $km = (int) sanitize_text_field(wp_unslash($_POST['mileage']));
   }

   if ($vehicleId === '' || $year <= 0) {
      wp_send_json_error('Parámetros inválidos para tasación.');
   }

   $estimation = motorflash_get_estimation($vehicleId, $year, $km);
   if (empty($estimation['ok'])) {
      wp_send_json_error(array(
         'message' => $estimation['message'] ?? 'No se pudo calcular la tasación.',
      ));
   }

   wp_send_json_success(array(
      'estimation' => (int) ($estimation['estimation'] ?? 0),
   ));
}
add_action('wp_ajax_calcular_tasacion', 'ajax_calcular_tasacion');
add_action('wp_ajax_nopriv_calcular_tasacion', 'ajax_calcular_tasacion');
