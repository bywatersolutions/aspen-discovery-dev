<?php
/* https://whatpwacando.today/barcode/ */

class AspenPWA_Scan extends Action {

	function launch() {
		global $interface;
		if (!UserAccount::isLoggedIn()) {
			header("Location: /MyAccount/Login");
			exit();
		}
		$user = UserAccount::getActiveUserObj();
		$linked = $user->linkedUsers;
		$hasLinkedUsers = count($linked) > 0;
		$interface->assign('displayName', $user->displayName);
		$interface->assign('hasLinkedUsers', $hasLinkedUsers);
		$interface->assign('linkedUsers', $linked);
		$this->display('scan.tpl', 'Scan & Go');
	}

	function getBreadcrumbs(): array {
		$breadcrumbs = [];
		$breadcrumbs[] = new Breadcrumb('', 'Scan');
		return $breadcrumbs;
	}

	function canView(): bool {
		return UserAccount::isLoggedIn() && !empty(UserAccount::getActiveRoles());
		//return UserAccount::userHasPermission('Permission Name Here');
	}
}