<?php
@test sample
$inputArr=array(
	array(2,3,1,4,5),
	array(2,1,4),
	array(2,3,5),
	array(3,4,5),
	array(2,3,1,4),
	array(2,3,1),
);
$obj = new FPGrowth();
$re = $obj->findFrequentItemSets($inputArr,3,1);
var_dump($re);
