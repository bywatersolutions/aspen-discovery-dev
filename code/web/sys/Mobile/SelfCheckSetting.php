<?php /** @noinspection PhpMissingFieldTypeInspection */
require_once ROOT_DIR . '/sys/Mobile/SelfCheckBarcode.php';

class MobileSelfCheckSetting extends DataObject {
	public $__table = 'aspen_lida_self_check_settings';
	public $id;
	public $name;
	public $isEnabled;
	public $checkoutLocation;
	public $barcodeEntryKeyboardType;

	private $_locations;
	private $_barcodeStyles;

	public function saveLocations() : void {
		if (isset ($this->_locations) && is_array($this->_locations)) {
			$locationsList = [];
			$location = new Location();
			$location->selectAdd();
			$location->selectAdd('locationId');
			$location->selectAdd('displayName');
			$location->orderBy('displayName');
			if (!UserAccount::userHasPermission('Administer All Locations')) {
				$homeLibrary = Library::getPatronHomeLibrary();
				$location->libraryId = $homeLibrary->libraryId;
			}
			$location->find();
			while ($location->fetch()) {
				$locationsList[$location->locationId] = $location->displayName;
			}
			foreach ($locationsList as $locationId => $displayName) {
				$location = new Location();
				$location->locationId = $locationId;
				$location->find(true);
				if (in_array($locationId, $this->_locations)) {
					//We want to apply the scope to this library
					if ($location->lidaSelfCheckSettingId != $this->id) {
						$location->lidaSelfCheckSettingId = $this->id;
						$location->update();
					}
				} else {
					//It should not be applied to this scope. Only change if it was applied to the scope
					if ($location->lidaSelfCheckSettingId == $this->id) {
						$location->lidaSelfCheckSettingId = -1;
						$location->update();
					}
				}
			}
			unset($this->_locations);
		}
	}
	public function __get($name) {
		if ($name == 'locations') {
			if (!isset($this->_locations) && $this->id) {
				$this->_locations = [];
				$obj = new Location();
				$obj->lidaSelfCheckSettingId = $this->id;
				$obj->find();
				while ($obj->fetch()) {
					$this->_locations[$obj->locationId] = $obj->locationId;
				}
			}
			return $this->_locations;
		} elseif ($name == 'barcodeStyles') {
			return $this->getBarcodeStyles();
		} else {
			return parent::__get($name);
		}
	}

	public function __set($name, $value) {
		if ($name == 'locations') {
			$this->_locations = $value;
		} elseif ($name == 'barcodeStyles') {
			$this->_barcodeStyles = $value;
		} else {
			parent::__set($name, $value);
		}
	}

	public function update(string $context = '') : int|bool {
		$ret = parent::update();
		if ($ret !== FALSE) {
			$this->saveLocations();
			$this->saveBarcodeStyles();
		}
		return true;
	}

	public function insert(string $context = '') : int|bool {
		$ret = parent::insert();
		if ($ret !== FALSE) {
			$this->saveLocations();
			$this->saveBarcodeStyles();
		}
		return $ret;
	}

	public function getBarcodeStyles() : ?array {
		if (!isset($this->_barcodeStyles) && $this->id) {
			$this->_barcodeStyles = [];

			$barcodeStyle = new MobileSelfCheckBarcode();
			$barcodeStyle->selfCheckSettingsId = $this->id;
			if ($barcodeStyle->find()) {
				while ($barcodeStyle->fetch()) {
					$this->_barcodeStyles[$barcodeStyle->id] = clone $barcodeStyle;
				}
			}

		}
		return $this->_barcodeStyles;
	}

	public function saveBarcodeStyles() : void {
		if (isset ($this->_barcodeStyles) && is_array($this->_barcodeStyles)) {
			$this->saveOneToManyOptions($this->_barcodeStyles, 'selfCheckSettingsId');
			unset($this->_barcodeStyles);
		}
	}

	/**
	 * @param string $locationId The location code for the active location
	 * @return false|int
	 */
	public function getCheckoutLocationSetting(string $locationId) : false|int {
		$location = new Location();
		$location->code = $locationId;
		if ($location->find(true)) {
			$scoSettings = new MobileSelfCheckSetting();
			$scoSettings->id = $location->lidaSelfCheckSettingId;
			if ($scoSettings->find(true)) {
				return $scoSettings->checkoutLocation;
			}
		}

		return false;
	}

	/** @noinspection PhpUnusedParameterInspection */
	public function getEditLink(string $context): string {
		return '/AspenLiDA/SelfCheckSettings?objectAction=edit&id=' . $this->id;
	}
}