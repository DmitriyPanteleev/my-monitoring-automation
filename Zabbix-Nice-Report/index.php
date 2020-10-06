<?php
	error_reporting(E_ALL & ~E_NOTICE);
	
	require_once "conf.php"; 

	try {
		$dbh = new PDO('mysql:host='.$dbhost.';dbname='.$dbname.';charset=utf8', $dbuser, $dbpass,
						array(
							PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
							PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ//,
							//PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = 'UTC'"
							)
			);
	} catch (PDOException $e) {
		echo 'Подключение не удалось: ' . $e->getMessage();
		exit('Выполнение прервано.');
	}	
	
    $from_date = $_REQUEST['from_date'];
    $to_date = $_REQUEST['to_date'];
    $interval = intval($_REQUEST['interval']);	

    if ($from_date == '') $from_date = date('d.m.Y');
    if ($to_date == '') $to_date = $from_date;
	
	function date_to_mktime($date, $is_day_begin = true) {
		$dateparts = explode('.',$date);
		if ($is_day_begin) {
			return mktime(0,0,0,intval($dateparts[1]),intval($dateparts[0]),intval($dateparts[2]));	
		} else {
			return mktime(23,59,59,intval($dateparts[1]),intval($dateparts[0]),intval($dateparts[2]));	
		}
	}
	
    function date_selector() {

        global $from_date, $to_date, $filial_select, $dep_array, $is_invalid, $interval;

        echo "<FORM name='date_interval' action='' method='GET'>";
        echo "<FIELDSET>";
        echo "<LEGEND>Период</LEGEND>";
        echo "c <INPUT type='text' name='from_date' id='from_date' class='dp' value='".$from_date."'> по <INPUT type='text' name='to_date' id='to_date' class='dp' value='".$to_date."'>";
        echo "&nbsp;::&nbsp;";
        echo "<a href='?action=".$_REQUEST['action']."&from_date=".('01.'.date('m.Y'))."&to_date=".date('d.m.Y')."'><b>С начала текущего месяца</b></a>";
        echo "&nbsp;::&nbsp;";
        echo "<a href='?action=".$_REQUEST['action']."&from_date=".('01.01.'.date('Y'))."&to_date=".date('d.m.Y')."'><b>С начала года</b></a>";
        echo "</FIELDSET>";
        echo "<FIELDSET>";
        echo "<LEGEND>Интервал объединения событий (час.)</LEGEND>";
        echo "<INPUT type='text' name='interval' id='interval' value='".$interval."'>";
        echo "</FIELDSET>";
		echo "<INPUT type='submit' value='Отобрать'>";
        echo "</FORM>";
        echo '<SCRIPT>
                $(function() {

		 $( "input.dp" ).datepicker({
			 changeMonth: true,
			 changeYear: true,
			 constrainInput: true,
			 dateFormat: "dd.mm.yy",
			 dayNames: ["Воскресенье", "Понедельник", "Вторник", "Среда", "Четверг", "Пятница", "Суббота"],
			 dayNamesMin: ["Вс", "Пн", "Вт", "Ср", "Чт", "Пт", "Сб"],
			 dayNamesShort: ["Вс", "Пн", "Вт", "Ср", "Чт", "Пт", "Сб"],
			 firstDay: 1,
			 monthNames: ["Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"],
			 monthNamesShort: ["Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"],
			 hideIfNoPrevNext: true,
			 yearRange: "c-1:+1",
			 onClose: function(dateText, inst) {
			   var pattern = /^(\d\d.){2}\d{4}$/;
			   var text = $("#dp_begin").val();
			   if (dateText.match(pattern) == null) $this.val("");
			 }
		     });

                 //$("table").tableSort();

                });
            </SCRIPT>';
    }
	
	$sql = "
				select h.hostid, h.name, e.clock, e.eventid, e.value, concat(from_unixtime(a.clock,'%d.%m.%Y %H:%i:%s'), ', ', u.surname, ' ', u.name, ': ', a.message) msg 
				from hosts h
				left join hosts_groups hg on hg.hostid = h.hostid
				left join groups g on g.groupid = hg.groupid
				left join items i on h.hostid = i.hostid
				left join functions f on i.itemid = f.itemid
				left join triggers t on t.triggerid = f.triggerid
				left join events e on t.triggerid = e.objectid
				left join acknowledges a on a.eventid = e.eventid
				left join users u on u.userid = a.userid
				where g.name = 'Сетевое оборудование' 
				and	e.clock between ".(date_to_mktime($from_date)+$tz)." and ".(date_to_mktime($to_date, false)+$tz)."
				and t.expression like '%<0.3'
				-- and h.hostid in (10653)
				order by h.hostid, e.clock
	";
	
	$hosts = $dbh->query($sql)->fetchAll();
	
	$log = array();

	$prev_hostname = '';	
	$prev_value = '';
	$from_time = '';
	$to_time = '';
	$message = '';
	$eventid = '';	
	
	foreach($hosts as $host) {
		
		if ($prev_hostname == '') $prev_hostname = $host->name;

		if ($prev_hostname != $host->name) {

			if (($from_time != '') OR ($to_time != '')) {
				if ($from_time == '') $from_time = date_to_mktime($from_date)+$tz;
				if ($to_time == '') $to_time = date_to_mktime($to_date, false)+$tz;			
				$log[$prev_hostname][] = array($prev_hostname, $from_time, $to_time, $message, $eventid);
				$from_time = '';
				$to_time = '';
				$message = '';
				
			}
			
			$prev_hostname = $host->name;								
			
		}
		
		if ($host->value == 1) {
			$from_time = $host->clock;
			$prev_value = $host->value;
			$eventid = $host->eventid;
			$message .= $host->msg;
		}
		
		if ($host->value == 0) {
			$to_time = $host->clock;
			$prev_value = $host->value;
			$message .= $host->msg;			

			if (($from_time != '') OR ($to_time != '')) {
				if ($from_time == '') $from_time = date_to_mktime($from_date)+$tz;
				if ($to_time == '') $to_time = date_to_mktime($to_date, false)+$tz;			
				$log[$prev_hostname][] = array($prev_hostname, $from_time, $to_time, $message, $eventid);
			}

		$prev_value = '';
		$from_time = '';
		$to_time = '';
		$message = '';
		$eventid = '';

		}
	}

	if (($from_time != '') OR ($to_time != '')) {	
		if ($from_time == '') $from_time = date_to_mktime($from_date)+$tz;
		if ($to_time == '') $to_time = date_to_mktime($to_date, false)+$tz;			
		$log[$prev_hostname][] = array($prev_hostname, $from_time, $to_time, $message, $eventid);
	}
	
	ksort($log);
	
			if (isset($_REQUEST['XLS'])) {
				require_once ("./Classes/PHPExcel/IOFactory.php");
				$objPHPexcel = PHPExcel_IOFactory::load('./XLS_Templates/agents.xls');
				$objWorksheet = $objPHPexcel->getActiveSheet();
				$objWorksheet->getCell('A1')->setValue("Недоступность хостов с ".$from_date." по ".$to_date);
				$cur_row = 3;

				$i = 0;
				
				$RedBackground = array(
						'fill' => array(
										'style' => PHPExcel_Style_Fill::FILL_SOLID,
										'startcolor' => array('rgb' => 'FF0000')
						)
				);				
	
				$prev_hostname = '';	
				$from_time = '';
				$to_time = '';
				$message = '';
				$color = '';				
				
				foreach($log as $hostid=>$data) {
					foreach($data as $el) {
						
						if ($prev_hostname == '') {
							$prev_hostname = $el[0];	
							$from_time = $el[1];
							$to_time = $el[2];
							$message = $el[3];
							$color = '';
						}
						
						if (($prev_hostname != $el[0]) or (($el[1] - $from_time > $interval*3600) and ($el[2] != $to_time))) {
							$i++;
							$objWorksheet->setCellValueByColumnAndRow(0, $cur_row, $i);
							$objWorksheet->setCellValueByColumnAndRow(1, $cur_row, $prev_hostname);						
							$objWorksheet->setCellValueByColumnAndRow(2, $cur_row, date('d.m.Y H:i:s',($from_time-$tz)));						
							$objWorksheet->setCellValueByColumnAndRow(3, $cur_row, date('d.m.Y H:i:s',($to_time-$tz)));						
							$objWorksheet->setCellValueByColumnAndRow(4, $cur_row, floor(($to_time-$from_time)/60));												
							$objWorksheet->setCellValueByColumnAndRow(5, $cur_row, $message);																								
							
							$objWorksheet->getRowDimension($cur_row)->setRowHeight(-1);
							
							if ($color != '') {
								$objWorksheet->getStyle('A'.$cur_row.':F'.$cur_row)->applyFromArray($RedBackground);
							}
							
							$cur_row++;							
							
							$prev_hostname = $el[0];								
							$from_time = '';
							$to_time = '';
							$message = '';
							$color = '';				
						}
						
						if ($from_time == '') {
							$from_time = $el[1];
							$to_time = $el[2];
							$message = $el[3];
							$color = '';				
						};
						
						if (($el[1] - $from_time < $interval*3600) and ($el[2] != $to_time)) {
							$to_time = $el[2];					
							$message .= $el[3];					
							$color = 'red';				
						} else {
							$prev_hostname = $el[0];	
							$from_time = $el[1];
							$to_time = $el[2];
							$message = $el[3];
							$color = '';
						}

					}
				}
				
				if ($prev_hostname != '') {
							$i++;
							$objWorksheet->setCellValueByColumnAndRow(0, $cur_row, $i);
							$objWorksheet->setCellValueByColumnAndRow(1, $cur_row, $prev_hostname);						
							$objWorksheet->setCellValueByColumnAndRow(2, $cur_row, date('d.m.Y H:i:s',($from_time-$tz)));						
							$objWorksheet->setCellValueByColumnAndRow(3, $cur_row, date('d.m.Y H:i:s',($to_time-$tz)));						
							$objWorksheet->setCellValueByColumnAndRow(4, $cur_row, floor(($to_time-$from_time)/60));												
							$objWorksheet->setCellValueByColumnAndRow(5, $cur_row, $message);	
				}				
				
				if ($color != '') {
					$objWorksheet->getStyle('A'.$cur_row.':F'.$cur_row)->applyFromArray($RedBackground);
				}
				
				// внутренние границы между ячейками
				$borderCells = array(
						'borders' => array(
								'inside' => array(
										'style' => PHPExcel_Style_Border::BORDER_THIN,
										'color' => array('argb' => '00000000'),
								),
						),
				);
				// наружная граница всей таблицы
				$borderOveral = array(
						'borders' => array(
								'outline' => array(
										'style' => PHPExcel_Style_Border::BORDER_THICK,
										'color' => array('argb' => '00000000'),
								),
						),
				);

				// применение границ к ячейкам и таблице
				$objWorksheet->getStyle('A3:F'.$cur_row)->applyFromArray($borderCells);
				$objWorksheet->getStyle('A2:F'.$cur_row)->applyFromArray($borderOveral);
				$objWorksheet->getStyle('A3:F'.$cur_row)->getAlignment()->setWrapText(true);

				$objWorksheet->getPageSetup()->setPrintArea('A1:F'.$cur_row);

				// вывод файла в браузер
				header('Content-Type: application/vnd.ms-excel');
				header('Content-Disposition: attachment;filename="Недоступность хостов с '.$from_date.' по '.$to_date.'.xls"');
				header('Cache-Control: max-age=0');
				$objWriter = PHPExcel_IOFactory::createWriter($objPHPexcel, 'Excel5');
				$objWriter->save('php://output');
			}	

	include_once('page_head.php');		

	date_selector();
	
	echo "<a href='?from_date=".$from_date."&to_date=".$to_date."&interval=".$interval."&XLS'><img src='./images/xls.png' alt='Excel' title='Excel' border=0></a>";			

	echo "<TABLE class='data'>";
			echo "<TR>";
			echo "<TH>#</TD>";
			echo "<TH>Имя агента</TD>";			
			echo "<TH>Время начала простоя</TD>";			
			echo "<TH>Время окончания простоя</TD>";			
			echo "<TH>Продолжительность простоя (мин)</TD>";
			echo "<TH>Причина простоя</TD>";			
			echo "</TR>";			

	$i = 1;
	
	$prev_hostname = '';	
	$from_time = '';
	$to_time = '';
	$message = '';
	$color = '';
	
	foreach($log as $hostid=>$data) {
		foreach($data as $el) {
			
			if ($prev_hostname == '') {
				$prev_hostname = $el[0];	
				$from_time = $el[1];
				$to_time = $el[2];
				$message = $el[3];
				$color = '';
			}
			
			if (($prev_hostname != $el[0]) or (($el[1] - $from_time > $interval*3600) and ($el[2] != $to_time))) {
				echo "<TR bgcolor='".$color."'>";
				echo "<TH>".$i++."</TD>";									
				echo "<TD>".$prev_hostname."</TD>";						
				echo "<TD>".date('d.m.Y H:i:s',($from_time-$tz))."</TD>";			
				echo "<TD>".date('d.m.Y H:i:s',($to_time-$tz))."</TD>";			
				echo "<TD>".floor(($to_time-$from_time)/60)."</TD>";
				echo "<TD>".$message."</TD>";						
				echo "</TR>";					
				
				$prev_hostname = $el[0];								
				$from_time = '';
				$to_time = '';
				$message = '';
				$color = '';				
			}
			
			if ($from_time == '') {
				$from_time = $el[1];
				$to_time = $el[2];
				$message = $el[3];
				$color = '';				
			};
			
			if (($el[1] - $from_time < $interval*3600) and ($el[2] != $to_time)) {
				$to_time = $el[2];					
				$message .= $el[3];					
				$color = 'red';				
			} else {
				$prev_hostname = $el[0];	
				$from_time = $el[1];
				$to_time = $el[2];
				$message = $el[3];
				$color = '';
			}

		}
	}
	
	if ($prev_hostname != '') {
		echo "<TR bgcolor='".$color."'>";
		echo "<TH>".$i++."</TD>";									
		echo "<TD>".$prev_hostname."</TD>";						
		echo "<TD>".date('d.m.Y H:i:s',($from_time-$tz))."</TD>";			
		echo "<TD>".date('d.m.Y H:i:s',($to_time-$tz))."</TD>";			
		echo "<TD>".floor(($to_time-$from_time)/60)."</TD>";
		echo "<TD>".$message."</TD>";						
		echo "</TR>";	
	}
	
	echo "</TABLE>";
	
?>