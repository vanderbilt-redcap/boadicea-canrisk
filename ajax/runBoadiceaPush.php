<?php
header('Content-Type: application/json');

$project_id = (int)trim($_GET['pid']);
$record_id  = (int)trim($_GET['id']);

$run = $module->runBoadiceaPush($project_id,$record_id);

$response = [];
if($run || is_null($run)) {
    $response['result'] = true;
} else {
    $response['result'] = false;
}

echo json_encode($response, JSON_UNESCAPED_SLASHES);
