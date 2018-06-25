<?php
spl_autoload_register(function ($name) {
	$fileName = str_replace("\\", "/", $name).".php";
	if (file_exists($fileName)) {
		include_once($fileName);
	}
});