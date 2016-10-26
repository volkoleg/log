<?php
error_reporting(0); // :facepalm:

class Log {

	const cookie_domain = ''; // PUT DOMAIN "domain.com"

	const log_dir = ''; //PATH TO LOGS FOLDER "/var/log/"

	public $types_pattern = "/prefix-(.+)-[0-9]{4}-[0-9]{2}-[0-9]{2}.log/i";

	const errors_names = [
		"E_NOTICE",
		"E_DEPRECATED",
		"Fatal Error",
		"E_ERROR",
		"E_PARSE",
		"E_WARNING",
		'E_STRICT'
	];

	const errors_colored = [
		"<b class='e_notice'>E_NOTICE</b>",
		"<b class='e_dep'>E_DEPRECATED</b>",
		"<b class='fatal'>Fatal Error</b>",
		"<b class='fatal'>E_ERROR</b>",
		"<b class='e_parse'>E_PARSE</b>",
		"<b class='e_warn'>E_WARNING</b>",
		"<b class='e_str'>E_STRICT</b>"
	];

	public $log = [];
	public $files = [];
	public $files_load = [];
	public $dates = [];
	public $current_date = '';
	public $current_type = '';
	public $type_field = '__sign';
	public $pattern = '/([0-9]{4}-[0-9]{2}-[0-9]{2})/';

	public $include = '';
	public $exclude = '';

	public $last_rec = '';

	public $cb_dep = false;
	public $cb_notice = false;

	public $is_frame = false;

	public $types = [
		'default.error' => [
			'name' => 'PHP',
			'color' => '#ffc849'
		],
		'javascript' => [
			'name' => 'JavaScript',
			'color' => '#00ff00'
		],
		'mysql' => [
			'name' => 'MySQL',
			'color' => '#00c2ff'
		]
	];
	const filters = [
		'exclude',
		'include',
		'current_date',
		'current_type'
	];

	function __construct() {
		$this->cb_dep = isset($_COOKIE['_cb_dep']) && $_COOKIE['_cb_dep'] == 1;
		$this->cb_notice = isset($_COOKIE['_cb_notice']) && $_COOKIE['_cb_notice'] == 1;

		$this->is_frame = !empty($_GET['frame']);
		$this->last_rec = empty($_COOKIE['_last_rec']) ? '' : urldecode($_COOKIE['_last_rec']);

		$this->checkPurge();

		$this->loadAllLogFilesNames();
		$this->loadAllLogFilesDates();
		$this->checkFilters();
		$this->loadLogFilesNamesByDateAndType();
	}

	public function checkPurge() {
		if(!empty($_GET['purge_less_tpl'])) {
			$__purge_less_tpl = ``;
		}
	}

	public function checkFilters() {
		foreach(self::filters as $v) {
			$this->$v = empty($_COOKIE['_' . $v]) ? '' : urldecode($_COOKIE['_' . $v]);

			if(isset($_GET[$v])) {
				$value = $_GET[$v];
				setcookie('_' . $v, urlencode($value), time() + (86400 * 30), '/', self::cookie_domain);
				$this->$v = $value;
			}
		}

		if(empty($this->current_date) && !empty($this->dates[0])) {
			$this->current_date = $this->dates[0];
		}

	}

	public function display() {
		if($this->is_frame) {
			$this->displayIFrame();
		} else {
			$this->displayMain();
		}
	}

	public function displayMain() {
		$this->showForms();
	}

	public function displayIFrame() {
		$this->loadSelectedLogFilesData();
		$this->showLog();
	}


	public function showForms() {

		echo "<div class='forms'>";
		echo '<form id="form-filters" target="iframe-log" action="" method="get">';

		echo '<div class="dropdown">
			<div class="hamburger"></div>
			<div class="dropdown-content">
				<label><input '.($this->cb_dep ? 'checked':'').' id="cb_dep" onchange="setIgnore(\'cb_dep\')" type="checkbox" />Ignore E_DEPRECATED</label>
				<label><input '.($this->cb_notice ? 'checked':'').' id="cb_notice" onchange="setIgnore(\'cb_notice\')" type="checkbox" />Ignore E_NOTICE</label>
				<label><input type="submit" value="Save And Reload"></label>
			</div>
		</div>';

		echo '<input type="hidden" name="frame" value="1">';

		echo '<select id="log-type" name="current_type">';
		echo "<option value='all'>ALL</option>";
		foreach($this->types as $k => $v) {
			$current = !empty($this->current_type) && $this->current_type == $k ? 'selected' : '';
			echo "<option value='{$k}' {$current}>{$v['name']}</option>";
		}
		echo '</select>';

		echo '<select id="logs-date" name="current_date">';
		foreach($this->dates as $i => $date) {
			$current = $this->current_date == $date ? 'selected' : '';
			echo "<option value='{$date}' {$current}";
			echo ">{$date}</option>";
		};
		echo '</select>';

		echo "
		<input title='Use | for separate text' onfocus='this.value=this.value;' id='include-input' type='text' name='include' value='{$this->include}' placeholder='Include...'>
		<input title='Use | for separate text' id='exclude-input' type='text' name='exclude' value='{$this->exclude}' placeholder='Exclude...'>
		<input id='submit-ok-button' type='submit' value='RELOAD'>
		<input type='submit' name='purge_less_tpl' id='purge-button' value='Purge LESS+TPL' />
		</form>";

		echo "</div>";
	}

	public function getLogFileType($file) {
		$type = '';
		foreach($this->types as $k => $v) {
			if(strpos($file, $k) !== false) {
				$type = $k;
				break;
			}
		}
		return $type;
	}

	public function parseLogFile($file) {
		$log_data = [];
		$key_field = '@timestamp';
		$type_field = $this->type_field;

		$type = $this->getLogFileType($file);

		$this->exclude = $this->cb_dep ? $this->exclude . '|' . 'E_DEPRECATED' : $this->exclude;
		$this->exclude = $this->cb_notice ? $this->exclude . '|' . 'E_NOTICE' : $this->exclude;

		if(!empty($type)) {

			$handle = @fopen($file, "r");
			if ($handle) {
				while (($buffer = @fgets($handle)) !== false) {
					$json = json_decode($buffer);

					if(!empty($this->exclude) && $this->searching($this->exclude, $json)) {
						continue;
					} else {
						if((!empty($this->include) && $this->searching($this->include, $json)) || empty($this->include)) {
							$json->$type_field = $type;
							$log_data[$json->$key_field] = $json;
						}
					}
				}

				if (!feof($handle)) {
					//echo "Error: unexpected fail\n";
				}
				@fclose($handle);
			}
		}
		return $log_data;
	}

	function searching($search, $json) {
		$r = false;
		if(strpos($search, '|') !== false) {
			$x = explode('|', $search);
			foreach ($x as $s) {
				if(!empty(trim($s))) {
					$r = $this->findString($s, $json);
					if($r === true) break;
				}
			}
		} else {
			$r = $this->findString($search, $json);
		}
		return $r;
	}

	function findString($search, $json) {

		$r = false;
		if(is_object($json) || is_array($json)) {
			foreach($json as $k => $v) {
				if (is_object($v) || is_array($v)) {
					$r = $this->findString($search, $v);
				} else {
					if(strpos(strtolower($v), strtolower($search)) !== false) {
						return true;
					}
				}
			}
		}
		return $r;
	}

	public function loadLogFilesNamesByDateAndType() {

		foreach($this->files as $k => $file) {

			if(!empty($this->current_type) && $this->current_type != 'all') {
				$found = strpos($file, $this->current_type) !== false;
			} else {
				$found = true;
			}

			if($found && !empty($this->current_date)) {
				$found = strpos($file, $this->current_date) !== false;
			} else {
				$found = false;
			}

			if($found) {
				$this->files_load[] = $file;
			}
		}

	}

	public function loadAllLogFilesDates() {

		foreach($this->files as $k => $file) {
			$matches = [];
			preg_match($this->pattern, $file, $matches);

			if(!empty($matches[0])) {
				$this->dates[] = $matches[0];
			}

		}

		if(!empty($this->dates)) {
			$this->dates = array_unique($this->dates);
			rsort($this->dates);
		}
	}

	public function updateTypesList($file) {
		$matches = [];
		preg_match($this->types_pattern, $file, $matches);
		if(!empty($matches[1]) && empty($this->types[$matches[1]])) {
			$this->types[$matches[1]] = [
				'name' => '+' . ucfirst($matches[1]),
				'color' => '#ff0000'
			];
		}
	}

	public function loadAllLogFilesNames() {
		if ($handle = opendir(self::log_dir)) {
			while (false !== ($file = readdir($handle))) {
				if ($file != "." && $file != "..") {
					$this->files[] = $file;
					$this->updateTypesList($file);
				}
			}
			closedir($handle);
		}
	}

	public function loadSelectedLogFilesData() {
		foreach($this->files_load as $file) {
			$l = $this->parseLogFile(self::log_dir . $file);
			$this->log = array_merge($this->log, $l);
		}
		arsort($this->log);
	}

	function showLog() {
		if(empty($this->log)) {
			echo 'Not found.';
			return;
		}

		$t = '@timestamp';

		echo "<table>
				<thead>
					<tr>
						<th width='35'>Which</th>
						<th width='65'>When</th>
						<th>Who</th>
						<th width='50%'>What</th>
						<th width='50%'>Where</th>
					</tr>
				</thead>
			<tbody>";

		$index = 0;
		$log_count = count($this->log);
		$n = 0;
		$h = 0;
		$cur_date = date("Y-m-d");

		$new_line = $this->current_date == $cur_date && !empty($this->last_rec) ? 'new-line' : '';

		foreach($this->log as $k => $v) {
			$tr_style = ($index % 2) == 0 ? 'tr_hl' : '';

			$vt = $v->$t;

			if(!empty($this->last_rec)) {
				if($vt == $this->last_rec) {
					$new_line = '';
				}
			}

			echo "<tr class='list {$new_line} {$tr_style}' onclick='toggleLine(\"line-{$index}\")'>";
			echo '<td>';
				echo ($log_count - $index);
			echo '</td>';
			echo '<td>';
				echo '<nobr>';
				echo $this->parseDate($v->$t, $n, $h);
				echo '</nobr>';
			echo '</td>';
			echo '<td>';
				$sign = $v->__sign;
				echo '<i class="no-wrap" style="color: ' . $this->types[$sign]['color'] . ';">' . $this->types[$sign]['name'] . '</i>';
			echo '</td>';
			echo '<td class="nobr">';
				echo $this->parseErrorTypes($v);

			echo '</td>';
			echo '<td class="nobr">';
				echo $this->parseSource($v);
			echo '</td>';
			echo '</tr>';
//			echo "<div id='line-{$index}' style='display: none;'>".$this->buildTree($v)."</div";
			echo "<tr id='line-{$index}' class='{$tr_style}' style='display: none;'>
				<td colspan='5' class='parsed-code'>
					<div onclick='toggleLine(\"line-{$index}\")' class='close-parse-code'>CLOSE</div>
					".$this->buildTree($v)."
				</td>
			</tr>";
//			echo '<tr><td colspan="5"></td></tr>';

			$index++;
		};
		echo '</tbody></table>';

		if($this->current_date == $cur_date) {
			$last_rec = reset($this->log);
			$timestamp = $last_rec->$t;
			setcookie('_last_rec', urlencode($timestamp), time() + 86400, '/', self::cookie_domain);
		} else {
			setcookie('_last_rec', '', time() + 86400, '/', self::cookie_domain);
		}

	}
	function parseErrorTypes($v) {
		$m = '@message';
		$f = '@fields';
		$e = 'ctxt_error';
		$s = $v->$m;
		$r = str_replace(self::errors_names, self::errors_colored, $s);
		if($v->__sign == 'mysql' && !empty($v->$f->$e)) {
			$r .= ' MYSQL Error: ' . $v->$f->$e;
		}
		return $r;
	}
	function parseDate($s, &$n, &$h) {
		$x = 60;
		$h2 = date("H", strtotime($s));


		if($h2 != $h) {
			$n = $n + $x;
			$h = $h2;
		}
		if($n >= ($x * 4)) $n = 0;

		$dx = date("U", strtotime($s));
		$d1 = date("H", $dx);
		$d2 = date(":i:s", $dx);
		$d = '<span style="color: hsl(' . $n . ', 100%, 50%)">' . $d1 . '</span>' . $d2;

		$r = '<span title="'.$s.'">' . $d . '</span>';
		return $r;
	}
	function buildTree($obj) {
		return $this->highlight_syntax(print_r($obj, true));
//		return '<pre>' . $this->highlight_syntax(print_r($obj, true), true) . '</pre>';
	}
	function parseSource($v) {
		$log_file_type = $v->__sign;
		switch($log_file_type) {
			case 'mysql':
				$f = '@source_path';
				$r = '';
				if(!empty($v->$f)) {
					$r = $this->highlightFile($v->$f);
				}
				break;

			case 'default.error':
				$f = '@fields';
				$c = 'ctxt_file';
				$l = 'ctxt_line';
				$r = '';
				if(!empty($v->$f->$c)) {
					$r = $this->highlightFile($v->$f->$c);
				}
				if(!empty($v->$f->$l)) {
					$r .= ' (line: <b class="file_with_err_line_num_color">' . $v->$f->$l . '</b>)';
				}
//				$r = $this->highlightFile($v->$f->$c) . ' (line: <b class="file_with_err_line_num_color">' . $v->$f->$l . '</b>)';
				break;

			case 'javascript':
				$a = '@fields';
				$b = 'ctxt_data';
				$c1 = 'url';
				$c2 = 'row';
				$c3 = 'col';
				$r = $this->highlightFile($v->$a->$b->$c1) . ' (row: <b class="file_with_err_line_num_color">' . $v->$a->$b->$c2 .'</b>, col: <b class="file_with_err_line_num_color">' . $v->$a->$b->$c3 . '</b>)';
				break;

			default:
				$a = '@fields';
				$b = 'url';
				$r = $this->highlightFile($v->$a->$b);
				break;
		};

		return $r;
	}

	function highlight_syntax($s) {
		$p = [
			"/(\[\@message\])|(\[row\])|(\[url\])|(\[col\])|(\[customer_id\])|(\[institute_id\])/",
			"/stdClass Object/",
			"/(.+) => ([0-9]+)([\n\r])/",
			"/([\n\r])/",
			"(\s\[)",
			"/(\s\s)/",
			"(\])",
		];

		$r = [
			'<b style="color: red">$0</b>',
			'<span style="color: #a8d2ff;">stdClass Object</span>',
			"$1 => <span style='color: yellow'>$2</span>$3",
			"<br>",
			"<span style='color: #738fff'>[",
			"&nbsp;&nbsp;",
			"]</span>",
		];
		return preg_replace($p, $r, $s);
	}

	function highlightFile($s) {
		$f = basename($s);
		$r = str_replace($f, "<span class='file_with_err_color'>{$f}</span>", $s);
		return $r;
	}
}

$app = new Log();

ob_start();
$app->display();
$html = ob_get_clean();

?>
<!DOCTYPE html>
<html>
<head>
	<title>DevBox LogReader</title>
	<meta charset="utf-8" />
	<style>
		.default {
			color: red;
		}
		.javascript {
			color: green;
		}
		.mysql {
			color: blue;
		}
		nav {
			display: block;
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			height: 35px;
			padding: 0;
			background-color: #636363;
		}
		section {
			display: block;
			position: absolute;
			bottom: 0;
			top: 0;
			left: 0;
			right: 0;
			margin: 35px 0 0 0;
			padding: 0;
			z-index: -82;
		}
		iframe {
			position: relative;
			display: block;
			left: 0;
			right: 0;
			top: 0;
			bottom: 0;
			margin: 0;
			padding: 0;
			border: 0;
			width: 100%;
			height: 100%;
		}
		body {
			color: #dadada;
			background-color: #1e1e1e;
			margin: 0;
			padding: 0;
		}
		* {
			font-size: 14px;
			font-family: Tahoma, Arial, Helvetica, sans-serif;
		}
		.file_with_err_color {
			color: #ffbe58;
		}.file_with_err_line_num_color {
			 color: #2196f3;
		 }
		.lines_count {
			color: lime;
		}
		.e_notice {
			color: yellow;
		}
		.e_dep {
			color: lightgoldenrodyellow;
		}
		.fatal {
			color: red;
		}
		.e_parse {
			color: red;
		}
		.e_warn {
			color: orange;
		}
		.e_str {
			color: pink;
		}
		div.close-parse-code {
			display: block;
			position: absolute;
			right: 10px;
			top: 10px;
			cursor: pointer;

			border-radius: 4px;
			border: 0;
			font-size: 14px;
			padding: 4px 10px;
			background-color: #ff0000;
			color: white;

		}
		div.close-parse-code:hover {
			background-color: #d10000;
		}
		td.parsed-code {
			position: relative;
			background-color: #342d2d;
			border-top: 2px solid red;
			border-bottom: 2px solid red;
			padding: 5px;
		}
		.dropdown {
			position: relative;
			display: inline-block;
			float: left;
			cursor: pointer;
		}
		.dropdown-content {
			display: none;
			position: absolute;
			background-color: #23beff;
			min-width: 190px;
			padding: 10px;
			z-index: 1;
		}
		.dropdown-content label {
			display: block;
			float: none;
			color: black;
			cursor: pointer;
		}
		.dropdown-content input[type=submit] {
			display: block;
			float: none;
			margin: 10px 0 0 0;
		}
		.dropdown:hover .dropdown-content {
			display: block;
		}
		.dropdown:hover .hamburger {
			border-top: 0.2em solid #ffffff;
			border-bottom: 0.2em solid #ffffff;
		}
		.dropdown:hover .hamburger:before {
			border-top: 0.2em solid #ffffff;
		}
		.dropdown:hover {
			background-color: #23beff;
		}
		.hamburger {
			position: relative;
			display: inline-block;
			width: 16px;
			vertical-align: middle;
			height: 10px;
			margin: 5px 5px 10px 5px;
			border-top: 0.2em solid #23beff;
			border-bottom: 0.2em solid #23beff;
		}
		.hamburger:before {
			content: "";
			position: absolute;
			top: 0.3em;
			left: 0;
			width: 100%;
			border-top: 0.2em solid #23beff;
		}
		table {
			/*table-layout: fixed;*/
			width: 100%;
			border: 0;
			border-collapse: collapse;
		}
		select {
			float: left;
		}
		form {
			display: inline-block;
			float: left;
			margin: 0 20px 0 0;
			width: 100%;
		}
		select, input, button, textarea {
			outline: none;
		}
		select, input[type=text] {
			float: left;
			margin: 0 5px 0 0;
			font-size: 14px;
			border: 0;
			background-color: #dedede;
			color: #000000;
			padding: 2px 5px;
			border-radius: 4px;
			height: 20px;
		}
		input[type=text]:focus {
			background-color: #fdffd5;
			/*outline: solid;*/
			/*outline-color: yellow;*/
		}
		#log-type {
			width: 120px;
			margin-left: 5px;
		}
		#log-file {
			width: 400px;
		}
		select, input[type=button], input[type=submit] {
			cursor: pointer;
			height: 24px;
		}
		input[name=include], input[name=exclude] {
			width: 200px;
		}
		input[type=button] {
			display: inline-block;
			float: left;
		}
		#submit-ok-button {
			width: 125px;
		}
		input[type=submit] {
			display: inline-block;
			float: left;
			border-radius: 4px;
			border: 0;
			font-size: 14px;
			padding: 2px 10px;
			background-color: #77b300;
			color: white;
		}
		input[type=submit]:hover {
			background-color: #6ca200;
		}
		#purge-button {
			background-color: #ff9419;
			margin: 0 0 0 20px;
		}
		#purge-button:hover {
			background-color: #de7e18;
		}

		hr {
			display: block;
			clear: both;
			float: none;
		}
		tr.tr_hl {
			background-color: rgba(255, 255, 255, 0.08);
		}
		tr {
			border: 0;
		}
		tr.list:hover {
			cursor: pointer;
			background-color: #8c0000;
			color: #ffffff;
		}
		tr.new-line {
			background-color: rgba(0, 140, 255, 0.25);
		}
		th {
			font-weight: bold;
			font-size: 11px;
			color: #9e9e9e;
		}
		td, th {
			vertical-align: top;
			padding: 0 5px;
			text-align: left;
		}
		.nobr {
			word-break: break-word;
			word-wrap: break-word;
		}
		.no-wrap {
			white-space: nowrap;
		}
		ul {
			margin: 0 0 0 50px;
			padding: 0;
			list-style-type: none;
		}
		li {
			padding: 0;
			margin: 0;
		}
		.forms {
			display: block;
			/*overflow: hidden;*/
			float: none;
			clear: both;
			width: 100%;
			margin: 5px;
			padding: 0;
		}
	</style>
	<script>
		function setIgnore(name) {
			var is_checked = document.getElementById(name).checked ? 1 : 0;
			setCookie('_' + name, is_checked, 30);
		}
		function setCookie(cname, cvalue, exdays) {
			var d = new Date();
			d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
			var expires = "expires="+ d.toUTCString();
			document.cookie = cname + "=" + cvalue + "; " + expires;
		}
		function toggleLine(id) {
			var block = document.getElementById(id);
			block.style.display = block.style.display === 'none' ? '' : 'none';
		}
		function init() {
			var $el = document.getElementById("include-input");
			if($el !== null ) $el.focus();
		}
	</script>
</head>
<body onload="init()">
<?php
	if(!$app->is_frame) {
		echo "<nav>{$html}</nav><section><iframe id='iframe-log' name='iframe-log' src='?frame=1&current_date={$app->current_date}'></section>";

	} else {
		echo $html;
	}
?>
</body>
</html>
