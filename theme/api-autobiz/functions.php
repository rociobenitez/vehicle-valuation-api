<?php
/**
 * TASACIÓN (API AUTOBIZ)
 * 
 * - FORMULARIO DE TASACIÓN
 * - INTEGRACIÓN API EXTERNA AUTOBIZ
 * - AUTENTICACIÓN
 * - AJAX PARA CARGAR DATOS FORMULARIO
 */

/**
 * Obtiene y cachea el token JWT desde la API de Autobiz.
 *
 * @return string|false Token JWT o false en caso de error.
 */
function obtener_autobiz_token() {
   // Verificar si ya existe el token en cache
   $cached_token = get_transient('autobiz_token');
   if ( $cached_token ) {
       return $cached_token;
   }
   
   // Obtener las credenciales definidas en wp-config.php
   if ( !defined('AUTOBIZ_USER') || !defined('AUTOBIZ_PASS') ) {
      error_log('Las credenciales de Autobiz no están definidas en wp-config.php.');
      return false;
  }
  $username = AUTOBIZ_USER;
  $password = AUTOBIZ_PASS;

   // URL de autenticación
   $url = 'https://apiv2.autobiz.com/users/v1/auth';

   // Las credenciales se pasan en los headers y el body se envía vacío
   $args = array(
      'headers'      => array(
         'Content-Type' => 'application/json',
         'Username'     => $username,
         'Password'     => $password,
      ),
       'body'        => '{}',  // Se envía un body vacío en formato JSON
       'method'      => 'POST',
       'timeout'     => 20,
   );

   // Realizar la solicitud a la API para obtener el token
   $response = wp_remote_post($url, $args);
   if ( is_wp_error($response) ) {
       error_log('Error en obtener_autobiz_token: ' . $response->get_error_message());
       return false;
   }

   // Decodificar la respuesta y extraer el token
   $data = json_decode(wp_remote_retrieve_body($response), true);
   if ( isset($data['accessToken']) ) {
       // Cachear el token por 55 minutos (suponiendo una validez de 60 minutos)
       set_transient('autobiz_token', $data['accessToken'], 55 * MINUTE_IN_SECONDS);
       return $data['accessToken'];
   }

   error_log('Error: No se encontró token en la respuesta de la API.');
   return false;
}

/**
 * Función genérica para realizar solicitudes a la API de Autobiz.
 *
 * @param string $endpoint El endpoint de la API (por ejemplo, 'referential/v1/makes').
 * @param array $query_args Parámetros de consulta (opcional).
 * @return array|false Array con los datos de la API o false en caso de error.
 */
function obtener_datos_api_autobiz( $endpoint, $query_args = array() ) {
   $token = obtener_autobiz_token();
   if ( ! $token ) {
       error_log('No se pudo obtener el token para la API de Autobiz.');
       return false;
   }

   // Construir la URL completa
   $url = 'https://apiv2.autobiz.com/' . $endpoint;
   if ( ! empty( $query_args ) ) {
       $url .= '?' . http_build_query( $query_args );
   }

   $args = array(
       'headers' => array(
           'Authorization' => 'Bearer ' . $token,
           'Content-Type'  => 'application/json'
       ),
       'timeout' => 20,
   );

   $response = wp_remote_get( $url, $args );
   if ( is_wp_error( $response ) ) {
       error_log( 'Error en obtener_datos_api_autobiz: ' . $response->get_error_message() );
       return false;
   }

   $data = json_decode( wp_remote_retrieve_body( $response ), true );
   return $data;
}

/**
 * Obtiene las marcas desde la API de Autobiz.
 *
 * @return array|false Array de marcas o false en caso de error.
 */
function obtener_marcas() {
   return obtener_datos_api_autobiz('referential/v1/makes');
}

/**
 * Obtiene las versiones para una combinación específica de marca y modelo.
 *
 * @param int $makeId ID de la marca.
 * @param int $modelId ID del modelo.
 * @return array|false Array de versiones o false en caso de error.
 */
function obtener_versiones_por_modelo($makeId, $modelId) {
   $endpoint = "referential/v1/make/{$makeId}/model/{$modelId}/versions";
   return obtener_datos_api_autobiz($endpoint);
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
 * Obtiene los modelos para una marca específica usando el endpoint:
 * https://apiv2.autobiz.com/referential/v1/make/:makeId/models
 *
 * @param int $makeId ID de la marca.
 * @return array|false Array de modelos o false en caso de error.
 */
function obtener_modelos_por_marca($makeId) {
   // Construir el endpoint con la marca seleccionada
   $endpoint = "referential/v1/make/{$makeId}/models";
   $models = obtener_datos_api_autobiz($endpoint);
   return $models;
}

/**
 * Handler AJAX para obtener los modelos según la marca seleccionada.
 */
function ajax_obtener_modelos_por_marca() {
   $makeId = isset($_POST['makeId']) ? intval($_POST['makeId']) : 0;
   if (!$makeId) {
       wp_send_json_error('Parámetro makeId inválido.');
   }
   
   $models = obtener_modelos_por_marca($makeId);
   if (!empty($models) && is_array($models)) {
       wp_send_json_success($models);
   } else {
       wp_send_json_error('No se encontraron modelos.');
   }
}
add_action('wp_ajax_obtener_modelos_por_marca', 'ajax_obtener_modelos_por_marca');
add_action('wp_ajax_nopriv_obtener_modelos_por_marca', 'ajax_obtener_modelos_por_marca');

/**
 * Obtiene datos dinámicos (por ejemplo, fuels, years, etc.) para una combinación de marca y modelo.
 *
 * @param int $makeId ID de la marca.
 * @param int $modelId ID del modelo.
 * @param string $dataKey Segmento de datos a obtener (por ejemplo, 'fuels' o 'years').
 * @return array|false Array con los datos solicitados o false en caso de error.
 */
function obtener_datos_por_marca_modelo($makeId, $modelId, $dataKey) {
   $endpoint = "referential/v1/make/{$makeId}/model/{$modelId}/" . $dataKey;
   return obtener_datos_api_autobiz($endpoint);
}

/**
* Handler AJAX genérico para obtener datos dinámicos según marca y modelo.
*
* Se espera que se envíen por POST los parámetros:
* - makeId: ID de la marca.
* - modelId: ID del modelo.
* - dataKey: El segmento a obtener (por ejemplo, 'fuels', 'years', etc.).
*/
function ajax_obtener_datos_por_marca_modelo() {
   $makeId = isset($_POST['makeId']) ? intval($_POST['makeId']) : 0;
   $modelId = isset($_POST['modelId']) ? intval($_POST['modelId']) : 0;
   $dataKey = isset($_POST['dataKey']) ? sanitize_text_field($_POST['dataKey']) : '';

   if (!$makeId || !$modelId || empty($dataKey)) {
       wp_send_json_error('Parámetros makeId, modelId o dataKey inválidos.');
   }

   $data = obtener_datos_por_marca_modelo($makeId, $modelId, $dataKey);
   if (!empty($data) && is_array($data)) {
       wp_send_json_success($data);
   } else {
       wp_send_json_error("No se encontraron datos para {$dataKey}.");
   }
}
add_action('wp_ajax_obtener_datos_por_marca_modelo', 'ajax_obtener_datos_por_marca_modelo');
add_action('wp_ajax_nopriv_obtener_datos_por_marca_modelo', 'ajax_obtener_datos_por_marca_modelo');

/**
 * Handler AJAX para obtener las versiones según la marca y el modelo seleccionados.
 * Se espera que se envíen por POST los parámetros:
 * - makeId: ID de la marca.
 * - modelId: ID del modelo.
 * Devuelve un array con las versiones o un error si no se encuentran.
 */
function ajax_obtener_versiones_por_modelo() {
   $makeId = isset($_POST['makeId']) ? intval($_POST['makeId']) : 0;
   $modelId = isset($_POST['modelId']) ? intval($_POST['modelId']) : 0;

   if (!$makeId || !$modelId) {
       wp_send_json_error('Parámetros makeId o modelId inválidos.');
   }

   $versions = obtener_versiones_por_modelo($makeId, $modelId);
   error_log('Versiones obtenidas: ' . print_r($versions, true));
   if (!empty($versions) && is_array($versions)) {
       wp_send_json_success($versions);
   } else {
       wp_send_json_error('No se encontraron versiones.');
   }
}
add_action('wp_ajax_obtener_versiones_por_modelo', 'ajax_obtener_versiones_por_modelo');
add_action('wp_ajax_nopriv_obtener_versiones_por_modelo', 'ajax_obtener_versiones_por_modelo');

/**
 * Endpoint AJAX para calcular la tasación de un vehículo.
 * 
 * La API de Autobiz requiere los siguientes parámetros obligatorios:
 * - version: Nombre de la versión.
 * - make: Nombre de la marca.
 * - model: Nombre del modelo.
 * - fuel: Tipo de combustible.
 * - year: Año de primera matriculación.
 * - month: Mes de primera matriculación.
 * - mileage: Kilometraje actual.
 *
 * Se envía una solicitud POST a la URL:
 * https://apiv2.autobiz.com/quotation/v1/identification
 *
 * La respuesta exitosa contendrá, entre otros, la clave "quotation".
 */

function ajax_calcular_tasacion() {
   // Recoger y sanitizar parámetros
   $versionId = isset($_POST['version']) ? intval($_POST['version']) : 0;
   $doors     = isset($_POST['doors']) ? intval($_POST['doors']) : 0;
   $year      = isset($_POST['year']) ? intval($_POST['year']) : 0;
   $mileage   = isset($_POST['mileage']) ? intval(str_replace('.', '', $_POST['mileage'])) : 0;

   // Depuración: Log de parámetros recibidos
   error_log('Parametros tasacion: versionId=' . $versionId . ', year=' . $year . ', mileage=' . $mileage);


   if (!$versionId || !$year || !$mileage) {
       wp_send_json_error(['message' => 'Faltan parámetros obligatorios para calcular la tasación.']);
   }

   // Vehículos no tasables: más de 9 años o más de 140.000 km
   if ( (intval(date('Y')) - $year) >= 9 || $mileage > 140000 ) {
      wp_send_json_error(['message' => 'not_eligible']);
  }

   // Obtener el token
   $token = obtener_autobiz_token();
   if (!$token) {
       wp_send_json_error(['message' => 'No se pudo obtener el token de autenticación.']);
   }

   // Endpoint de la API para la tasación
   $url = "https://apiv2.autobiz.com/quotation/v1/version/{$versionId}/year/{$year}/mileage/{$mileage}/quotation";

   // Preparar la petición GET
   $args = array(
      'method'  => 'GET',
      'timeout' => 30,
      'headers' => array(
         'Content-Type'  => 'application/json',
         'Authorization' => 'Bearer ' . $token
      )
   );

   $response = wp_remote_get($url, $args);
   if (is_wp_error($response)) {
       wp_send_json_error(['message' => 'Error en la solicitud a la API: ' . $response->get_error_message()]);
   }

   $result = json_decode(wp_remote_retrieve_body($response), true);
   error_log('Respuesta API tasacion: ' . print_r($result, true));

   // Verificamos el valor de _quotation.tradeIn
   if (isset($result['_quotation']['tradeIn']) && $result['_quotation']['tradeIn'] > 0) {
       wp_send_json_success(array(
           'quotation' => $result['_quotation']['tradeIn'],
           'source'    => '_quotation.tradeIn',
           'data'      => $result
       ));
   } else {
       wp_send_json_error('No se encontró el valor de tasación (tradeIn) en la respuesta.');
   }
}

add_action('wp_ajax_calcular_tasacion', 'ajax_calcular_tasacion');
add_action('wp_ajax_nopriv_calcular_tasacion', 'ajax_calcular_tasacion');
