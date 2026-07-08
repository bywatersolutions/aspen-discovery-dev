<?php

require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/AspenPWA/SelfCheckSetting.php';

class AspenPWA_SelfCheckSettings extends ObjectEditor {
	function getObjectType(): string {
		return 'AspenPWASelfCheckSetting';
	}

	function getToolName(): string {
		return 'SelfCheckSettings';
	}

	function getModule(): string {
		return 'AspenPWA';
	}

	function getPageTitle(): string {
		return 'Self-Check Settings';
	}

	function getAllObjects(int $page, int $recordsPerPage): array {
		$list = [];

		$object = new AspenPWASelfCheckSetting();
		$object->orderBy($this->getSort());
		$this->applyFilters($object);
		$object->limit(($page - 1) * $recordsPerPage, $recordsPerPage);
		$object->find();
		while ($object->fetch()) {
			$list[$object->id] = clone $object;
		}

		return $list;
	}

	function getDefaultSort(): string {
		return 'name asc';
	}

	function getObjectStructure($context = ''): array {
		return AspenPWASelfCheckSetting::getObjectStructure($context);
	}

	function getPrimaryKeyColumn(): string {
		return 'id';
	}

	function getIdKeyColumn(): string {
		return 'id';
	}

	function getBreadcrumbs(): array {
		$breadcrumbs = [];
		$breadcrumbs[] = new Breadcrumb('/Admin/Home', 'Administration Home');
		$breadcrumbs[] = new Breadcrumb('/Admin/Home#aspen_pwa', 'Aspen PWA');
		$breadcrumbs[] = new Breadcrumb('/AspenPWA/SelfCheckSettings', 'Self-Check Settings');
		return $breadcrumbs;
	}

	function getActiveAdminSection(): string {
		return 'AspenPWA';
	}

	public function getViewPermissions() : array {
		return ['Administer Aspen PWA Self-Check Settings'];
	}

	public function getRequiredModule(): ?string {
		return 'Aspen PWA';
	}
}