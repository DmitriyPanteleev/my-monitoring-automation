<?php

// Former by t.php

function diff_to_dhms($diff) {
	
	$day = 86400;
	$hour = 3600;
	$min = 60;
	
	$days = str_pad(floor($diff / $day),2,'0',STR_PAD_LEFT);
	
	$diff = $diff % $day;
	
	$hours = str_pad(floor($diff / $hour),2,'0',STR_PAD_LEFT);
	
	$diff = $diff % $hour;
	
	$mins = str_pad(floor($diff / $min),2,'0',STR_PAD_LEFT);
	
	$diff = $diff % $min;
	
	$secs = str_pad($diff,2,'0',STR_PAD_LEFT);
	
	return $days.':'.$hours.':'.$mins.':'.$secs;
	
}

$x = 1484626661;
echo date('d.m.Y H:i:s',$x);
echo '<BR>';
echo diff_to_dhms($x);

?>