<?php

class SelfRegTerms extends Action {
	function launch($msg = null) {
		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
		header("Pragma: no-cache");
		header("Expires: 0");
		global $interface;
		global $library;

		$catalog = CatalogFactory::getCatalogConnectionInstance();
		$selfRegTerms = $catalog->getSelfRegistrationTerms();
		if ($selfRegTerms != null) {
			$interface->assign('tosBody', $selfRegTerms->terms);
			$interface->assign('tosDenialBody', $selfRegTerms->redirect);
			$this->display('selfRegistrationTerms.tpl', 'Terms of Service', '');
		}
	}

	function getBreadcrumbs(): array {
		$breadcrumbs = [];
		$breadcrumbs[] = new Breadcrumb('', 'Terms of Service');
		return $breadcrumbs;
	}
}