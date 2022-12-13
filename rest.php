<?php

	require_once('constants.php');

	class Rest{

		protected $request;
		protected $serviceName;
		protected $param;

		public function __construct(){

			if ($_SERVER['REQUEST_METHOD'] != 'POST') {

				$this->throwError(REQUEST_METHOD_NOT_VALID, "Request Method is not valid");

			}

			$handler = fopen('php://input', 'r');

			$this->request = stream_get_contents($handler);
			$this->validateRequest();

		}

		public function validateRequest(){

			// if ($_SERVER['CONTENT_TYPE'] != 'application/json') {

			// 	$this->throwError(REQUEST_CONTENT_TYPE_NOT_VALID, 'Request content type is not valid');

			// 	$this->throwError($_SERVER);

			// }
			
			$data = json_decode($this->request, true);

			if (!isset($data['name']) || $data['name'] == "") {
				$this->throwError(API_NAME_REQUIRED, 'API name required');
			}

			$this->serviceName = $data["name"];

			if (!isset($data['param'])) {
				$this->throwError(API_PARAM_REQUIRED, 'API PARAM required');
			}

			$this->param = $data["param"];

		}

		public function processApi(){

			$api = new Api;

			if (!method_exists($api, $this->serviceName)) {

				$this->throwError(API_DOES_NOT_EXIST, 'API does not exists');

			}else{

				$rMethod = new reflectionMethod('API', $this->serviceName);

			}

			$rMethod->invoke($api);

		}

		public function validateParameter($fieldName, $value, $dataType, $required = true){

			/* Validar que todos los parametros tengan valor */
			if ($required == true && empty($value) == true) {

				$this->throwError(VALIDATE_PARAMETER_REQUIRED, $fieldName .  " parameter is required");

			}

			switch ($dataType) {
				case BOOLEAN:
					if (!is_bool($value)) {
							$this->throwError(VALIDATE_PARAMETER_DATATYPE, "Datatype is not valid for " . $fieldName . '.  It should be boolean');
					}
					break;
				case INTEGER:
					if (!is_numeric($value)) {
							$this->throwError(VALIDATE_PARAMETER_DATATYPE, "Datatype is not valid for " . $fieldName . '.  It should be numeric');
					}
					break;
				case STRING:
					if (!is_string($value)) {
							$this->throwError(VALIDATE_PARAMETER_DATATYPE, "Datatype is not valid for " . $fieldName . '.  It should be string');
					}
					break;
				default:
					// code...
					break;
			}

			return $value;

		}

		public function throwError($code, $message){

			/* Returna codigos de error en formato JSON */
			header("content-type: application/json");

			$errorMsg = json_encode(array("response" => array("status" => $code, "message" => $message)));

			echo $errorMsg;

			exit;
		}

		public function returnResponse($code, $data){

			header("content-type: application/json");

			$response = json_encode(array('response' => array('status' => $code, 'result' => $data)));

			echo $response;

			exit;

		}

	}

?>
