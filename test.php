<?php

$data = '{"mut_freq":"UK","cancer_rates":"UK","user_id":"mcguffk","pedigree_data":"##CanRisk 1.0\n##menarche=12\n##oc_use=N\n##mht_use=N\n##BMI=23.01\n##alcohol=44.8\n##height=180.34\n##FamID\tName\tTarget\tIndivID\tFathID\tMothID\tSex\tMZtwin\tDead\tAge\tYob\tBC1\tBC2\tOC\tPRO\tPAN\tAshkn\tBRCA1\tBRCA2\tPALB2\tATM\tCHEK2\tRAD51D\tRAD51C\tBRIP1\tER:PR:HER2:CK14:CK56\nXXXX\tpa\t0\tm21\t0\t0\tM\t0\t0\t0\t0\t0\t0\t0\t0\t0\t0\t0:0\t0:0\t0:0\t0:0\t0:0\t0:0\t0:0\t0:0\t0:0:0:0:0\nXXXX\tma\t0\tf21\t0\t0\tF\t0\t0\t0\t0\t0\t0\t0\t0\t0\t0\t0:0\t0:0\t0:0\t0:0\t0:0\t0:0\t0:0\t0:0\t0:0:0:0:0\nXXXX\tme\t1\tch1\tm21\tf21\tF\t0\t0\t42\t1977\t38\t0\t0\t0\t0\t0\tS:N\tS:N\tS:N\tS:N\t0:0\t0:0\t0:0\t0:0\t0:0:0:0:0\n"}';

$output = $module->sendRequest($data);

echo "<br /><pre>";
var_dump($output);
echo "</pre><br />";