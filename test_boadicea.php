<?php
/** @var $module \Vanderbilt\BoadiceaCanrisk\BoadiceaCanrisk */
include_once(\ExternalModules\ExternalModules::getProjectHeaderPath());

if($_POST['testBoadicea']) {
	$score = $module->sendBoadiceaRequest($_POST['testBoadicea']);
}

if($score) {
	$response = json_decode($score, true);
	echo "<h2>Result from BOADICEA</h2>";
	
	$boadiceaErrors = "";
	
	foreach($response as $responseKey => $responseRow) {
		if(strpos($responseKey,"Error") !== false) {
			$foundError = true;
			$boadiceaErrors .= var_export($responseRow,true);
		}
	}
	
	if($boadiceaErrors != "") {
		echo "<br /><pre>";
		var_dump($boadiceaErrors);
		echo "</pre><br />";
	}
	else {
		echo "<h4>Lifetime Cancer Risk: ".$response["pedigree_result"][0]["lifetime_cancer_risk"][0]["breast cancer risk"]["percent"]."</h4>";
	}
}

?>
<form method="post">
	<div style="width:100%;">
		<textarea name='testBoadicea' class='notesbox' style="height:400px"><?=htmlspecialchars($_POST['testBoadicea'],ENT_QUOTES)?></textarea>
	</div>
	<input type="submit" />
</form>