<?php
namespace Vanderbilt\BoadiceaCanrisk;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class BoadiceaCanrisk extends AbstractExternalModule
{
	public function redcap_save_record( $project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance ) {
	
	}
	
	public function sendRequest($data) {
		$apiUrl = $this->getProjectSetting("api-url");
		$apiToken = $this->getProjectSetting("auth-token");
		echo $apiToken."<br />";
		$ch = curl_init($apiUrl);
		curl_setopt($ch,CURLOPT_HTTPHEADER, [
			"Authorization: Token ".$apiToken,
			"Content-Type: application/json"
		]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		$output = curl_exec($ch);
		curl_close($ch);
		
		return $output;
	}
}