<?php

/** @var $module \Vanderbilt\BoadiceaCanrisk\BoadiceaCanrisk */
//echo "<br /><pre>";
//var_dump($module->calculateChdPrs(50,"M","EUR",250,30,130,0,0,0,0));
//var_dump($module->calculateChdPrs(55,"F","AA",250,30,130,0,0,0,1));
//var_dump($module->calculateChdPrs(60,"M","HIS",300,30,130,0,0,0,1.5));
//var_dump($module->calculateChdPrs(60,"M","AA",300,30,130,0,0,0,1.5));
//var_dump($module->calculateChdPrs(60,"M","EUR",200,20,130,0,0,0,-1.5));
//var_dump($module->calculateChdPrs(65,"F","EUR",200,40,140,1,0,0,2));
//var_dump($module->calculateChdPrs(70,"F","AA",250,40,140,1,1,0,2.5));
//var_dump($module->calculateChdPrs(50,"M","OTHER",250,30,120,1,0,1,1.3));
//var_dump($module->calculateChdPrs(45,"M","HIS",280,50,125,0,1,1,0.8));
//echo "</pre><br />";
//
//die();
$pedigreeData = "##CanRisk 1.0
##menarche=14
##parity=2
##first_live_birth=16
##oc_use=F:2
##BMI=18.31
##alcohol=0
##height=155
##FamID	Name	Target	IndivID	FathID	MothID	Sex	MZtwin	Dead	Age	Yob	BC1	BC2	OC	PRO	PAN	Ashkn	BRCA1	BRCA2	PALB2	ATM	CHEK2	RAD51D	RAD51C	BRIP1	ER:PR:HER2:CK14:CK56
XXXX	0	0	Vfkr	0	0	M	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
XXXX	1	0	Bpes	0	0	F	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
XXXX	2	0	QDSv	0	0	M	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
XXXX	3	0	IUCS	0	0	F	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
XXXX	4	0	Bcrj	0	0	M	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
XXXX	5	0	RvMk	Vfkr	Bpes	F	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
XXXX	6	0	HYjN	0	0	M	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
XXXX	7	0	NAvB	QDSv	IUCS	F	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
XXXX	8	0	m21	HYjN	NAvB	M	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
XXXX	9	0	ySRJ	0	0	M	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
XXXX	10	0	f21	Bcrj	RvMk	F	0	1	78	1920	1	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
XXXX	11	0	bMGu	Bcrj	RvMk	M	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
XXXX	12	0	pFam	Bcrj	RvMk	M	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
XXXX	13	1	ch1	m21	f21	F	0	0	43	1977	0	0	0	0	0	0	S:P	S:P	T:N	T:N	T:N	T:N	T:N	T:N	0:0:0:0:0
XXXX	14	0	mvIZ	m21	f21	F	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
XXXX	15	0	Fxsy	m21	f21	M	0	0	0	0	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
XXXX	16	0	jXjW	ySRJ	ch1	F	0	0	22	1999	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0
XXXX	17	0	gDbD	ySRJ	ch1	M	0	0	28	1993	0	0	0	0	0	0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0	0:0:0:0:0";

//$data = '{"mut_freq":"UK","cancer_rates":"UK","user_id":"mcguffk","pedigree_data":"##CanRisk 1.0\n##menarche=12\n##oc_use=N\n##mht_use=N\n##BMI=23.01\n##alcohol=44.8\n##height=180.34\n##FamID\tName\tTarget\tIndivID\tFathID\tMothID\tSex\tMZtwin\tDead\tAge\tYob\tBC1\tBC2\tOC\tPRO\tPAN\tAshkn\tBRCA1\tBRCA2\tPALB2\tATM\tCHEK2\tRAD51D\tRAD51C\tBRIP1\tER:PR:HER2:CK14:CK56\nXXXX\tpa\t0\tm21\t0\t0\tM\t0\t0\t0\t0\t0\t0\t0\t0\t0\t0\t0:0\t0:0\t0:0\t0:0\t0:0\t0:0\t0:0\t0:0\t0:0:0:0:0\nXXXX\tma\t0\tf21\t0\t0\tF\t0\t0\t0\t0\t0\t0\t0\t0\t0\t0\t0:0\t0:0\t0:0\t0:0\t0:0\t0:0\t0:0\t0:0\t0:0:0:0:0\nXXXX\tme\t1\tch1\tm21\tf21\tF\t0\t0\t42\t1977\t38\t0\t0\t0\t0\t0\tS:N\tS:N\tS:N\tS:N\t0:0\t0:0\t0:0\t0:0\t0:0:0:0:0\n"}';

$output = $module->sendBoadiceaRequest($pedigreeData);

echo "<br /><pre>";
var_dump($output);
echo "</pre><br />";