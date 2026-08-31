<?php
/* https://whatpwacando.today/barcode/ */

class AspenPWA_js extends Action {
    
	function launch() {
		header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
		header('content-type: text/javascript; charset=utf-8');
		http_response_code(200);
		// TODO fix this out of scanner_root
		//echo file_get_contents(ROOT_DIR . '/js/scan.js');
		echo file_get_contents($_SERVER['DOCUMENT_ROOT'].'/interface/themes/responsive/js/aspen/scan.js');
	}

    function getBreadcrumbs(): array {
		$breadcrumbs = [];
		$breadcrumbs[] = new Breadcrumb('', 'Scan');
		return $breadcrumbs;
	}
}