<?php /** @noinspection SqlResolve */

require_once ROOT_DIR . '/Drivers/Millennium.php';

class Sierra extends Millennium {
	protected $urlIdRegExp = "/.*\/([\d]*)$/";
	private $sierraToken = null;
	private $lastResponseCode;
	private /** @noinspection PhpPropertyOnlyWrittenInspection */
		$lastError;
	private $lastErrorMessage;

	private $_sierraDNAConnection;

	public function _connectToApi() {
		if ($this->sierraToken == null) {
			$apiVersion = $this->accountProfile->apiVersion;
			$tokenUrl = $this->getVendorOpacUrl() . "/iii/sierra-api/v{$apiVersion}/token/";
			$ch = curl_init($tokenUrl);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
			curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			$authInfo = base64_encode($this->accountProfile->oAuthClientId . ":" . $this->accountProfile->oAuthClientSecret);
			$headers = [
				'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
				'Authorization: Basic ' . $authInfo,
			];
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			$return = curl_exec($ch);
			$curl_info = curl_getinfo($ch);
			$responseCode = $curl_info['http_code'];
			curl_close($ch);
			ExternalRequestLogEntry::logRequest('sierra.connectToApi', 'POST', $tokenUrl, $headers, "grant_type=client_credentials", $responseCode, $return, []);

			$this->sierraToken = json_decode($return);
		}
		return $this->sierraToken;
	}

	public function __destruct() {
		$this->closeSierraDNAConnection();
	}

	public function _callUrl($requestType, $url) {
		$tokenData = $this->_connectToAPI();
		if ($tokenData) {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
			$host = parse_url($url, PHP_URL_HOST);
			$headers = [
				"Authorization: " . $tokenData->token_type . " {$tokenData->access_token}",
				"User-Agent: Aspen Discovery",
				//"X-Forwarded-For: " . IPAddress::getActiveIp(),
				"Host: " . $host,
			];
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 60);
			$return = curl_exec($ch);
			$curl_info = curl_getinfo($ch);
			$responseCode = $curl_info['http_code'];
			$this->lastResponseCode = $responseCode;

			ExternalRequestLogEntry::logRequest($requestType, 'GET', $url, $headers, '', $responseCode, $return, []);
			curl_close($ch);

			$returnVal = json_decode($return);
			if ($returnVal != null) {
				if (!isset($returnVal->message) || $returnVal->message != 'An unexpected error has occurred.') {
					return $returnVal;
				}
			}
		}
		return null;
	}

	public function _postPage($requestType, $url, $postParams) {

		$tokenData = $this->_connectToAPI();
		if ($tokenData) {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
			$host = parse_url($url, PHP_URL_HOST);
			$headers = [
				"Authorization: " . $tokenData->token_type . " {$tokenData->access_token}",
				"User-Agent: Aspen Discovery",
				"X-Forwarded-For: " . IPAddress::getActiveIp(),
				"Accept-Language: *",
				"Host: " . $host,
				'Content-Type: application/json',
				'Accept: application/json',
			];
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 60);
			curl_setopt($ch, CURLOPT_POST, true);
			if ($postParams != null) {
				if (is_string($postParams)) {
					$post_string = $postParams;
				} else {
					$post_string = http_build_query($postParams);
				}
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
			} else {
				$post_string = '';
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
				$headers[] = 'Content-Length: 0';
			}
			$return = curl_exec($ch);
			$curl_info = curl_getinfo($ch);
			$responseCode = $curl_info['http_code'];
			$this->lastResponseCode = $responseCode;
			$this->lastError = curl_errno($ch);
			$this->lastErrorMessage = curl_error($ch);
//			if ($responseCode == 400){
//				global $logger;
//				$logger->log("Got 400 error POSTING to '" . $url . "'", Logger::LOG_ERROR);
//				$logger->log(print_r($curl_info, true), Logger::LOG_ERROR);
//			}

			ExternalRequestLogEntry::logRequest($requestType, 'POST', $url, $headers, $post_string, $responseCode, $return, []);
			curl_close($ch);
			$returnVal = json_decode($return);
			if ($returnVal != null) {
				if (!isset($returnVal->message) || $returnVal->message != 'An unexpected error has occurred.') {
					return $returnVal;
				}
			}
		}
		return null;
	}

	public function _sendPage($requestType, $httpMethod, $url, $postParams = null) {

		$tokenData = $this->_connectToAPI();
		if ($tokenData) {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
			$host = parse_url($url, PHP_URL_HOST);
			$headers = [
				"Authorization: " . $tokenData->token_type . " {$tokenData->access_token}",
				"User-Agent: Aspen Discovery",
				//"X-Forwarded-For: " . IPAddress::getActiveIp(),
				"Host: " . $host,
			];
			if ($httpMethod == 'PUT') {
				$headers[] = 'Content-Type: application/json';
				if ($postParams === null || $postParams === false) {
					$headers[] = 'Content-Length: 0';
				} else {
					if (is_array($postParams)) {
						$postParams = json_encode($postParams);
					}
				}
			}
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 60);
			if ($httpMethod == 'GET') {
				curl_setopt($ch, CURLOPT_HTTPGET, true);
			} elseif ($httpMethod == 'POST') {
				curl_setopt($ch, CURLOPT_POST, true);
			} else {
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
			}
			if ($postParams != null) {
				if (is_array($postParams)) {
					$postFields = http_build_query($postParams);
				} else {
					$postFields = $postParams;
				}
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
			} else {
				$postFields = '';
			}
			$return = curl_exec($ch);
			$curl_info = curl_getinfo($ch);
			$responseCode = $curl_info['http_code'];
			$this->lastResponseCode = $responseCode;
			$this->lastError = curl_errno($ch);
			$this->lastErrorMessage = curl_error($ch);

			ExternalRequestLogEntry::logRequest($requestType, $httpMethod, $url, $headers, $postFields, $responseCode, $return, []);
			curl_close($ch);
			$returnVal = json_decode($return);
			if ($returnVal != null) {
				if (!isset($returnVal->message) || $returnVal->message != 'An unexpected error has occurred.') {
					return $returnVal;
				}
			}
		}
		return null;
	}

	/**
	 * Returns one of three values
	 * - none - No forgot password functionality exists
	 * - emailResetLink - A link to reset the pin is emailed to the user
	 * - emailPin - The pin itself is emailed to the user
	 * @return string
	 */
	function getForgotPasswordType() {
		if ($this->accountProfile == null) {
			return 'none';
		} else {
			if ($this->accountProfile->loginConfiguration == 'barcode_pin') {
				return 'emailAspenResetLink';
			} else {
				return 'none';
			}
		}
	}

	public function getHolds($patron): array {
		global $library;
		require_once ROOT_DIR . '/sys/User/Hold.php';
		$availableHolds = [];
		$unavailableHolds = [];
		$return = [
			'available' => $availableHolds,
			'unavailable' => $unavailableHolds,
		];

		$patronId = $patron->unique_ils_id;
		$sierraUrl = $this->accountProfile->vendorOpacUrl;

		$sierraUrl = $sierraUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/" . $patronId . "/holds";
		if ($this->accountProfile->apiVersion > 4) {
			$sierraUrl .= "?fields=default,priorityQueueLength&limit=1000";
		} else {
			$sierraUrl .= "?fields=default,frozen,priority,priorityQueueLength,notWantedBeforeDate,notNeededAfterDate&limit=1000";
		}
		$holds = $this->_callUrl('sierra.getHolds', $sierraUrl);

		if ($holds->total == 0) {
			return $return;
		}

		// these will be consistent for every hold
		$pickupLocations = $patron->getValidPickupBranches($this->accountProfile->recordSource);
		if (is_array($pickupLocations)) {
			if (count($pickupLocations) > 1) {
				$canUpdatePL = true;
			} else {
				$canUpdatePL = false;
			}
		} else {
			$canUpdatePL = false;
		}
		foreach ($holds->entries as $sierraHold) {
			$curHold = new Hold();
			$curHold->userId = $patron->id;
			$curHold->type = 'ils';
			$curHold->source = $this->accountProfile->getIndexingProfile()->name;

			$curHold->frozen = $sierraHold->frozen;
			$curHold->createDate = strtotime($sierraHold->placed); // date hold created
			// innreach holds don't include notNeededAfterDate
			$curHold->automaticCancellationDate = isset($sierraHold->notNeededAfterDate) ? strtotime($sierraHold->notNeededAfterDate) : null; // not needed after date
			$curHold->expirationDate = isset($sierraHold->pickupByDate) ? strtotime($sierraHold->pickupByDate) : false; // pick up by date // this isn't available in api v4

			if (isset($sierraHold->priority)) {
				if ($this->accountProfile->apiVersion == 4) {
					$holdPriority = (integer)$sierraHold->priority + 1;
				} else {
					$holdPriority = $sierraHold->priority;
				}
				$curHold->position = $holdPriority;
			}
			if (isset($sierraHold->priorityQueueLength)) {
				$curHold->holdQueueLength = $sierraHold->priorityQueueLength;
			}

			// cancel id
			preg_match($this->urlIdRegExp, $sierraHold->id, $m);
			$curHold->cancelId = $m[1];

			// status, cancelable, freezable
			$recordStatus = $sierraHold->status->code;
			// check item record status
			if (preg_match($this->urlIdRegExp, $sierraHold->record, $m)) {
				$recordId = $m[1];
			} else {
				$recordId = substr($sierraHold->record, strrpos($sierraHold->record, '/') + 1);
			}
			$isInnReach = false;
			if ($sierraHold->recordType == 'i') {
				$recordItemStatus = $sierraHold->status->code;
				// If this is an inn-reach exclude from check -- this comes later
				if (!strstr($recordId, "@")) {
					// if the item status is "on hold shelf" (!) but the hold record status is "on hold" (0) use "on hold" status
					// the "on hold shelf" status is for another patron.
					if ($recordItemStatus != "!" && $recordStatus != '0') {
						// check for in transit status see
						if ($recordItemStatus == 't') {
							if (isset($sierraHold->priority) && (int)$sierraHold->priority == 1) {
								$recordStatus = 't';
							}
						}
					}
				} else {
					// inn-reach status
					$isInnReach = true;
					$curHold->source = $library->interLibraryLoanName;
					$recordStatus = $recordItemStatus;
				}
			}

			$available = false;
			// type hint so '0' != false
			switch ((string)$recordStatus) {
				case '0':
				case '-':
					if ($sierraHold->frozen) {
						$status = "Frozen";
					} else {
						$status = 'On hold';
					}
					$freezeable = true;
					$cancelable = true;

					if ($canUpdatePL) {
						$updatePickup = true;
					} else {
						$updatePickup = false;
					}
					if ($isInnReach) {
						if (!empty($sierraHold->pickupByDate)) {
							$status = 'Ready For Pickup';
							$available = true;
							$updatePickup = false;
							$freezeable = false;
						}
					}
					break;
				case 'b':
				case 'j':
				case 'i':
				case '!':
					$status = 'Ready';
					$cancelable = true;
					$freezeable = false;
					$updatePickup = false;
					$available = true;
					break;
				case 't':
					$status = 'In transit';
					$cancelable = true;
					$freezeable = false;
					if ($canUpdatePL) {
						$updatePickup = true;
					} else {
						$updatePickup = false;
					}
					break;
				case "&": // inn-reach status
					$status = "Requested";
					$cancelable = true;
					$freezeable = false;
					$updatePickup = false;
					break;
				case "#": // inn-reach status
					$sierraHold->status->code = 'i';
					$status = 'Ready';
					$freezeable = false;
					$cancelable = false;
					$updatePickup = false;
					$available = true;
					break;
				default:
					if (isset($recordItemStatusMessage)) {
						$status = $recordItemStatusMessage;
					} else {
						$status = 'On hold';
					}
					$cancelable = false;
					$freezeable = false;
					$updatePickup = false;
			}
			$curHold->status = $status;
//			if (isset($curHold->holdQueueLength)) {
//				// if the patron is the only person on wait list hold can't be frozen
//				if (isset($curHold->position) && ($curHold->position == 1 && (int)$curHold->holdQueueLength == 1)) {
//					$freezeable = false;
//					// if there is no priority set but queueLength = 1
//				} elseif (!isset($curHold->position) && $curHold->holdQueueLength == 1) {
//					$freezeable = false;
//				}
//			}
			$curHold->canFreeze = $freezeable || $curHold->frozen;
			$curHold->cancelable = $cancelable;
			$curHold->locationUpdateable = $updatePickup;
			$curHold->available = $available;
			// unset for next round.
			unset($status, $freezeable, $cancelable, $updatePickup);

			// pick up location
			if (!empty($sierraHold->pickupLocation)) {
				$pickupBranch = new Location();
				$pickupBranch->code = $sierraHold->pickupLocation->code;
				if ($pickupBranch->find(true)) {
					$curHold->pickupLocationId = $pickupBranch->locationId;
					$curHold->pickupLocationName = $pickupBranch->displayName;
				} else {
					$curHold->pickupLocationId = false;
					$curHold->pickupLocationName = $sierraHold->pickupLocation->name;
				}
			} else {
				//This shouldn't happen but we have had examples where it did
				global $logger;
				$logger->log("Patron with barcode {$patron->getBarcode()} has a hold with out a pickup location ", Logger::LOG_ERROR);
				$curHold->pickupLocationId = false;
				$curHold->pickupLocationName = '';
			}

			// determine if this is an innreach hold
			// or if it's a regular ILS hold
			if (strstr($recordId, "@")) {
				$titleAuthor = $this->getTitleAndAuthorForInnReachHold($curHold->cancelId);
				if ($titleAuthor !== false) {
					$curHold->title = $titleAuthor['title'];
					$curHold->author = $titleAuthor['author'];
					$curHold->format = [];
				} else {
					$curHold->title = 'Unknown';
					$curHold->author = 'Unknown';
				}
				$curHold->sourceId = '';
				$curHold->recordId = '';
				$curHold->source = $library->interLibraryLoanName;
			} else {
				///////////////
				// ILS HOLD
				//////////////
				// record type and record id
				$recordType = $sierraHold->recordType;
				// for item level holds we need to grab the bib id.
				$id = $recordId; //$m[1];
				if ($recordType == 'i') {
					$itemId = ".i$id" . $this->getCheckDigit($id);
					$id = $this->getBibIdForItem($itemId, $id);
				} else {
					$recordXD = $this->getCheckDigit($id);
					$id = ".b$id$recordXD";
				}

				if ($id != false) {

					$curHold->recordId = $id;
					$curHold->sourceId = $curHold->recordId;

					// get more info from record
					require_once ROOT_DIR . '/RecordDrivers/MarcRecordDriver.php';
					$recordDriver = new MarcRecordDriver((string)$curHold->recordId);
					if ($recordDriver->isValid()) {
						$curHold->updateFromRecordDriver($recordDriver);

						if($recordType == 'i') {
							// Get volume for Item holds
							$relatedRecord = $recordDriver->getRelatedRecord();
							if ($relatedRecord != null) {
								$groupingItem = $relatedRecord->getItemById($itemId);
								if ($groupingItem != null) {
									$curHold->volume = $groupingItem->volume;
									$curHold->callNumber = $groupingItem->callNumber;
								}
							}
						}
					}
				} else {
					$curHold->sourceId = '';
					$curHold->recordId = '';
				}
			}
			if ($available) {
				$return['available'][] = $curHold;
			} else {
				$return['unavailable'][] = $curHold;
			}
		}

		return $return;
	}

	public function getReadingHistory($patron, $page = 1, $recordsPerPage = -1, $sortOption = "checkedOut") {
		$readingHistoryEnabled = false;
		$patronId = $patron->unique_ils_id;

		$sierraUrl = $this->accountProfile->vendorOpacUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/" . $patronId . "/checkouts/history/activationStatus";

		$readingHistoryEnabledResponse = $this->_callUrl('sierra.getReadingHistoryStatus', $sierraUrl);

		if (!empty($readingHistoryEnabledResponse)) {
			$readingHistoryEnabled = $readingHistoryEnabledResponse->readingHistoryActivation;
		}
		$readingHistoryTitles = [];

		//Sierra does not report reading history enabled properly so we should always get it.
		if (true || $readingHistoryEnabled) {
			ini_set('memory_limit', '2G');
			set_time_limit(0);

			$numProcessed = 0;
			$totalToProcess = 1000;
			while ($numProcessed < $totalToProcess) {
				$getReadingHistoryUrl = $this->accountProfile->vendorOpacUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/" . $patronId . "/checkouts/history?limit=100&offset=$numProcessed&sortField=outDate&sortOrder=desc";

				$readingHistoryResponse = $this->_callUrl('sierra.getReadingHistory', $getReadingHistoryUrl);
				if ($readingHistoryResponse && $readingHistoryResponse->total > 0) {
					$totalToProcess = $readingHistoryResponse->total;
					foreach ($readingHistoryResponse->entries as $historyEntry) {
						$curTitle = [];
						preg_match($this->urlIdRegExp, $historyEntry->bib, $matches);
						$bibId = ".b{$matches[1]}" . $this->getCheckDigit($matches[1]);
						$curTitle['id'] = $bibId;
						$curTitle['shortId'] = "{$matches[1]}";
						$curTitle['recordId'] = $bibId;
						$curTitle['checkout'] = strtotime($historyEntry->outDate);
						$curTitle['checkin'] = null; //Polaris doesn't indicate when things are checked in
						$curTitle['ratingData'] = null;
						$curTitle['permanentId'] = null;
						$curTitle['linkUrl'] = null;
						$curTitle['coverUrl'] = null;
						require_once ROOT_DIR . '/RecordDrivers/MarcRecordDriver.php';
						$recordDriver = new MarcRecordDriver($this->accountProfile->recordSource . ':' . $curTitle['recordId']);
						if ($recordDriver->isValid()) {
							$curTitle['ratingData'] = $recordDriver->getRatingData();
							$curTitle['permanentId'] = $recordDriver->getPermanentId();
							$curTitle['linkUrl'] = $recordDriver->getGroupedWorkDriver()->getLinkUrl();
							$curTitle['coverUrl'] = $recordDriver->getBookcoverUrl('medium', true);
							$curTitle['title'] = $recordDriver->getTitle();
							$curTitle['format'] = $recordDriver->getFormats();
							$curTitle['author'] = $recordDriver->getPrimaryAuthor();
						} else {
							//get title and author by looking up the bib
							$getBibResponse = $this->_callUrl('sierra.getBib', $this->accountProfile->vendorOpacUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/bibs/{$curTitle['shortId']}");
							if ($getBibResponse) {
								if (isset($getBibResponse->deleted) && $getBibResponse->deleted == true) {
									$curTitle['title'] = 'Deleted from catalog';
									$curTitle['author'] = 'Unknown';
									$curTitle['format'] = 'Unknown';
								} else {
									if (isset($getBibResponse->title)) {
										$curTitle['title'] = $getBibResponse->title;
									} else {
										$curTitle['title'] = 'Unknown';
									}
									if (isset($getBibResponse->author)) {
										$curTitle['author'] = $getBibResponse->author;
									} else {
										$curTitle['author'] = 'Unknown';
									}
									$curTitle['format'] = isset($getBibResponse->materialType->value) ? $getBibResponse->materialType->value : 'Unknown';
								}
							} else {
								$curTitle['title'] = 'Unknown';
								$curTitle['author'] = 'Unknown';
								$curTitle['format'] = 'Unknown';
							}
							$getBibResponse = null;
						}
						$recordDriver->__destruct();
						$recordDriver = null;

						$readingHistoryTitles[] = $curTitle;
					}
					$numProcessed += count($readingHistoryResponse->entries);
				} else {
					break;
				}
			}
		}

		return [
			'historyActive' => $readingHistoryEnabled,
			'titles' => $readingHistoryTitles,
			'numTitles' => count($readingHistoryTitles),
		];
	}

	public function getCheckouts(User $patron): array {
		require_once ROOT_DIR . '/sys/User/Checkout.php';
		$checkedOutTitles = [];
		global $library;

		$patronId = $patron->unique_ils_id;

		$numProcessed = 0;
		$total = -1;

		while ($numProcessed < $total || $total == -1) {
			$sierraUrl = $this->accountProfile->vendorOpacUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/" . $patronId . "/checkouts?fields=default,barcode,callNumber&limit=100&offset={$numProcessed}";
			$checkouts = $this->_callUrl('sierra.getCheckouts', $sierraUrl);
			if ($total == -1) {
				$total = $checkouts->total;
			}

			foreach ($checkouts->entries as $i => $entry) {
				preg_match($this->urlIdRegExp, $entry->id, $m);
				$checkoutId = $m[1];

				$curCheckout = new Checkout();
				$curCheckout->type = 'ils';
				$curCheckout->source = $this->getIndexingProfile()->name;
				$curCheckout->userId = $patron->id;
				$curCheckout->dueDate = strtotime($entry->dueDate);
				$curCheckout->checkoutDate = strtotime($entry->outDate);
				$curCheckout->renewCount = $entry->numberOfRenewals;
				$curCheckout->canRenew = true;
				$curCheckout->renewalId = $checkoutId;
				$curCheckout->renewIndicator = $checkoutId;
				if (isset($entry->barcode)) {
					$curCheckout->barcode = $entry->barcode;
				}
				if (strpos($entry->item, "@") !== false) {
					$curCheckout->source = $library->interLibraryLoanName;
					$curCheckout->sourceId = '';
					$curCheckout->recordId = '';
					$titleAuthor = $this->getTitleAndAuthorForInnReachCheckout($checkoutId);
					if ($titleAuthor != false) {
						$curCheckout->title = $titleAuthor['title'];
						$curCheckout->author = $titleAuthor['author'];
						$curCheckout->formats = ['Unknown'];
					} else {
						$curCheckout->title = 'Unknown';
						$curCheckout->author = 'Unknown';
						$curCheckout->formats = ['Unknown'];
					}
				} else {
					preg_match($this->urlIdRegExp, $entry->item, $m);
					$itemIdShort = $m[1];
					$itemId = ".i" . $itemIdShort . $this->getCheckDigit($itemIdShort);
					$bibId = $this->getBibIdForItem($itemId, $itemIdShort);

					$curCheckout->callNumber = $entry->callNumber;

					$curCheckout->itemId = $itemId;
					if ($bibId != false) {
						$curCheckout->sourceId = $bibId;
						$curCheckout->recordId = $bibId;
						require_once ROOT_DIR . '/RecordDrivers/MarcRecordDriver.php';
						$recordDriver = new MarcRecordDriver((string)$curCheckout->recordId);
						if ($recordDriver->isValid()) {
							$curCheckout->updateFromRecordDriver($recordDriver);
							$relatedRecord = $recordDriver->getRelatedRecord();
							if ($relatedRecord != null) {
								//Check to see if we have volume info for the item
								foreach ($relatedRecord->getItems() as $item) {
									if ($item->itemId == $itemId) {
										if (!empty($item->volume)) {
											$curCheckout->volume = $item->volume;
										}
										if ($item->callNumber != $curCheckout->callNumber) {
											$curCheckout->callNumber = $item->callNumber;
										}
										break;
									}
								}
							}
						} else {
							$bibIdShort = substr(str_replace('.b', 'b', $bibId), 0, -1);
							$getBibResponse = $this->_callUrl('sierra.getBib', $this->accountProfile->vendorOpacUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/bibs/{$bibIdShort}");
							if ($getBibResponse) {
								$curCheckout->title = isset($getBibResponse->title) ? $getBibResponse->title : 'Unknown';
								$curCheckout->author = isset($getBibResponse->author) ? $getBibResponse->author : 'Unknown';
								$curCheckout->formats = [isset($getBibResponse->materialType->value) ? $getBibResponse->materialType->value : 'Unknown'];
							} else {
								$curCheckout->title = 'Unknown';
								$curCheckout->author = 'Unknown';
								$curCheckout->formats = ['Unknown'];
							}
						}
					} else {
						$curCheckout->sourceId = '';
						$curCheckout->recordId = '';
					}
				}
				$index = $i + $numProcessed;
				$sortKey = "{$curCheckout->source}_{$curCheckout->sourceId}_$index";
				$checkedOutTitles[$sortKey] = $curCheckout;
			}
			$numProcessed += $checkouts->total;
		}

		return $checkedOutTitles;
	}

	function renewCheckout($patron, $recordId, $itemId = null, $itemIndex = null) {
		$sierraUrl = $this->accountProfile->vendorOpacUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/checkouts/{$itemId}/renewal";
		$renewResponse = $this->_postPage('sierra.renewCheckout', $sierraUrl, '');

		if ($this->lastResponseCode == 200 || $this->lastResponseCode == 204) {
			require_once ROOT_DIR . '/RecordDrivers/MarcRecordDriver.php';
			$recordDriver = new MarcRecordDriver($this->accountProfile->recordSource . ":" . $recordId);
			if ($recordDriver->isValid()) {
				$title = $recordDriver->getTitle();
			} else {
				$title = false;
			}

			$return = ['success' => true];
			if ($title) {
				$return['message'] = translate([
					'text' => '%1% has been renewed.',
					1 => $title,
					'isPublicFacing' => true,
				]);
				$return['api']['message'] = translate([
					'text' => '%1% has been renewed.',
					1 => $title,
					'isPublicFacing' => true,
				]);
			} else {
				$return['api']['message'] = translate([
					'text' => 'Your item has been renewed',
					'isPublicFacing' => true,
				]);
			}
			$return['api']['title'] = translate([
				'text' => 'Checkout renewed successfully',
				'isPublicFacing' => true,
			]);

			$patron->clearCachedAccountSummaryForSource($this->getIndexingProfile()->name);
			$patron->forceReloadOfCheckouts();
		} else {
			$message = translate([
				'text' => "Unable to renew your checkout",
				'isPublicFacing' => true,
			]);
			if (!empty($renewResponse) && !empty($renewResponse->description)) {
				$message .= '<br/>' . translate([
						'text' => $renewResponse->description,
						'isPublicFacing' => true,
					]);
			}
			return [
				'success' => false,
				'message' => $message,
				'api' => [
					'title' => translate([
						'text' => 'Error renewing',
						'isPublicFacing' => true,
					]),
					'message' => $message,
				]
			];
		}

		return $return;
	}

	private function getTitleFromItemLink(string $itemLink) {
		$bibId = $this->getBibIdFromItemLink($itemLink);
		$title = '';
		if ($bibId != false) {
			require_once ROOT_DIR . '/RecordDrivers/MarcRecordDriver.php';
			$recordDriver = new MarcRecordDriver((string)$bibId);
			if ($recordDriver->isValid()) {
				$title = $recordDriver->getTitle();
			} else {
				$bibIdShort = substr(str_replace('.b', '', $bibId), 0, -1);
				$getBibResponse = $this->_callUrl('sierra.getBib', $this->accountProfile->vendorOpacUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/bibs/{$bibIdShort}");
				if ($getBibResponse) {
					$title = $getBibResponse->title;
				}
			}
		}
		return $title;
	}
	private function getTitleByItemId(string $itemId, string $itemShortId){
		$bibId = $this->getBibIdForItem($itemId, $itemShortId);
		$title = '';
		if ($bibId != false) {
			require_once ROOT_DIR . '/RecordDrivers/MarcRecordDriver.php';
			$recordDriver = new MarcRecordDriver((string)$bibId);
			if ($recordDriver->isValid()) {
				$title = $recordDriver->getTitle();
			} else {
				$bibIdShort = substr(str_replace('.b', 'b', $bibId), 0, -1);
				$getBibResponse = $this->_callUrl('sierra.getBib', $this->accountProfile->vendorOpacUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/bibs/{$bibIdShort}");
				if ($getBibResponse) {
					$title = $getBibResponse->title;
				}
			}
		}
		return $title;
	}

	/**
	 * @param string $itemId
	 * @return string|false
	 */
	private function getBibIdForItem(string $itemId, ?string $shortId) {
		require_once ROOT_DIR . '/sys/Grouping/GroupedWorkItem.php';
		require_once ROOT_DIR . '/sys/Grouping/GroupedWorkRecord.php';
		$groupedWorkItem = new GroupedWorkItem();
		$groupedWorkItem->itemId = $itemId;
		$id = false;
		if ($groupedWorkItem->find(true)) {
			$groupedWorkRecord = new GroupedWorkRecord();
			$groupedWorkRecord->id = $groupedWorkItem->groupedWorkRecordId;
			if ($groupedWorkRecord->find(true)) {
				$id = $groupedWorkRecord->recordIdentifier;
			}
		}
		if ($id == false && !empty($shortId)) {
			//Lookup the bib id from the Sierra APIs
			$sierraUrl = $this->accountProfile->vendorOpacUrl;
			$sierraUrl .= "/iii/sierra-api/v{$this->accountProfile->apiVersion}/items/$shortId";
			$id = $this->getBibIdFromItemLink($sierraUrl);
		}
		return $id;
	}

	private function getBibIdFromItemLink(string $itemLink) {
		$itemInfo = $this->_callUrl('sierra.getItemInfo', $itemLink);
		if (!empty($itemInfo)) {
			if (empty($itemInfo->bibIds)) {
				$id = false;
			}else if (is_array($itemInfo->bibIds)) {
				$id = reset($itemInfo->bibIds);
				$id = '.b' . $id . $this->getCheckDigit($id);
			}else if (is_string($itemInfo->bibIds)) {
				$id = $itemInfo->bibIds;
				$id = '.b' . $id . $this->getCheckDigit($id);
			}else{
				$id = false;
			}
		} else {
			$id = false;
		}
		return $id;
	}

	function freezeHold($patron, $recordId, $itemToFreezeId, $dateToReactivate): array {
		$sierraUrl = $this->accountProfile->vendorOpacUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/holds/{$itemToFreezeId}";
		$params = [
			'freeze' => true,
		];
		$freezeResponse = $this->_sendPage('sierra.freezeHold', 'PUT', $sierraUrl, $params);
		if (!$freezeResponse) {
			$patron->forceReloadOfHolds();
			return [
				'success' => true,
				'message' => translate([
					'text' => "Hold frozen successfully.",
					'isPublicFacing' => true,
				]),
				'api' => [
					'title' => translate([
						'text' => 'Hold frozen',
						'isPublicFacing' => true,
					]),
					'message' => translate([
						'text' => 'Hold frozen successfully',
						'isPublicFacing' => true,
					]),
				],
			];
		} else {
			$return = [
				'success' => false,
				'message' => translate([
					'text' => 'Unable to freeze your hold.',
					'isPublicFacing' => true,
				]),
				'api' => [
					'title' => translate([
						'text' => 'Unable to freeze hold',
						'isPublicFacing' => true,
					]),
					'message' => translate([
						'text' => 'Unable to freeze your hold.',
						'isPublicFacing' => true,
					]),
				],
			];
			$return['message'] .= ' ' . translate([
					'text' => trim(str_replace('WebPAC Error : ', '', $freezeResponse->description)),
					'isPublicFacing' => true,
				]);
			$return['api']['message'] .= ' ' . translate([
					'text' => trim(str_replace('WebPAC Error : ', '', $freezeResponse->description)),
					'isPublicFacing' => true,
				]);
			return $return;
		}
	}

	function thawHold($patron, $recordId, $itemToThawId): array {
		$sierraUrl = $this->accountProfile->vendorOpacUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/holds/{$itemToThawId}";
		$params = [
			'freeze' => false,
		];
		$thawResponse = $this->_sendPage('sierra.thawHold', 'PUT', $sierraUrl, json_encode($params));
		if (!$thawResponse) {
			$patron->forceReloadOfHolds();
			return [
				'success' => true,
				'message' => translate([
					'text' => 'Hold thawed successfully.',
					'isPublicFacing' => true,
				]),
				'api' => [
					'title' => translate([
						'text' => 'Hold thawed',
						'isPublicFacing' => true,
					]),
					'message' => translate([
						'text' => 'Hold thawed successfully.',
						'isPublicFacing' => true,
					]),
				],
			];
		} else {
			$return = [
				'success' => true,
				'message' => translate([
					'text' => "Unable to thaw your hold.",
					'isPublicFacing' => true,
				]),
				'api' => [
					'title' => translate([
						'text' => 'Error thawing hold',
						'isPublicFacing' => true,
					]),
					'message' => translate([
						'text' => 'Unable to thaw your hold.',
						'isPublicFacing' => true,
					]),
				],
			];
			$return['message'] .= ' ' . translate([
					'text' => trim(str_replace('WebPAC Error : ', '', $thawResponse->description)),
					'isPublicFacing' => true,
				]);
			$return['api']['message'] .= ' ' . translate([
					'text' => trim(str_replace('WebPAC Error : ', '', $thawResponse->description)),
					'isPublicFacing' => true,
				]);
			return $return;
		}
	}

	function changeHoldPickupLocation(User $patron, $recordId, $itemToUpdateId, $newPickupLocation, $newPickupSublocation = null): array {
		$sierraUrl = $this->accountProfile->vendorOpacUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/holds/{$itemToUpdateId}";
		$params = [
			'pickupLocation' => $newPickupLocation,
		];
		$changePickupResponse = $this->_sendPage('sierra.changePickupLocation', 'PUT', $sierraUrl, json_encode($params));
		if (!$changePickupResponse) {
			$patron->forceReloadOfHolds();
			$result['success'] = true;
			$result['message'] = translate([
				'text' => 'The pickup location of your hold was changed successfully.',
				'isPublicFacing' => true,
			]);

			// Result for API or app use
			$result['api']['title'] = translate([
				'text' => 'Pickup location updated',
				'isPublicFacing' => true,
			]);
			$result['api']['message'] = translate([
				'text' => 'The pickup location of your hold was changed successfully.',
				'isPublicFacing' => true,
			]);

			return $result;
		} else {
			$message = translate([
					'text' => 'Sorry, the pickup location of your hold could not be changed.',
					'isPublicFacing' => true,
				]) . " {$changePickupResponse->ErrorMessage}";;
			$result['success'] = false;
			$result['message'] = $message;

			// Result for API or app use
			$result['api']['title'] = translate([
				'text' => 'Unable to update pickup location',
				'isPublicFacing' => true,
			]);
			$result['api']['message'] = trim(str_replace('WebPAC Error : ', '', $changePickupResponse->ErrorMessage));

			return $result;
		}
	}

	public function cancelHold($patron, $recordId, $cancelId = null, $isIll = false): array {
		$sierraUrl = $this->accountProfile->vendorOpacUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/holds/{$cancelId}";
		$cancelHoldResponse = $this->_sendPage('sierra.cancelHold', 'DELETE', $sierraUrl, '');
		if (!$cancelHoldResponse) {
			$patron->forceReloadOfHolds();
			return [
				'success' => true,
				'message' => translate([
					'text' => 'Hold cancelled successfully.',
					'isPublicFacing' => true,
				]),
				'api' => [
					'title' => translate([
						'text' => 'Hold cancelled',
						'isPublicFacing' => true,
					]),
					'message' => translate([
						'text' => 'Hold cancelled successfully.',
						'isPublicFacing' => true,
					]),
				],
			];
		} else {
			$return = [
				'success' => true,
				'message' => translate([
					'text' => "Unable to cancel your hold.",
					'isPublicFacing' => true,
				]),
				'api' => [
					'title' => translate([
						'text' => 'Error cancelling hold',
						'isPublicFacing' => true,
					]),
					'message' => translate([
						'text' => 'Unable to cancel your hold. ',
						'isPublicFacing' => true,
					]),
				],
			];
			$return['message'] .= ' ' . translate([
					'text' => trim(str_replace('WebPAC Error : ', '', $cancelHoldResponse->description)),
					'isPublicFacing' => true,
				]);
			$return['api']['message'] .= ' ' . translate([
					'text' => trim(str_replace('WebPAC Error : ', '', $cancelHoldResponse->description)),
					'isPublicFacing' => true,
				]);
			return $return;
		}
	}

	public function placeHold($patron, $recordId, $pickupBranch = null, $cancelDate = null) {
		/** @noinspection PhpArrayIndexImmediatelyRewrittenInspection */
		$hold_result = [
			'success' => false,
			'message' => translate([
				'text' => 'There was an error placing your hold.',
				'isPublicFacing' => true,
			]),
			'api' => [
				'title' => translate([
					'text' => 'Unable to place hold',
					'isPublicFacing' => true,
				]),
				'message' => translate([
					'text' => 'There was an error placing your hold.',
					'isPublicFacing' => true,
				]),
			],
		];

		if (strpos($recordId, ':')) {
			[
				,
				$recordId,
			] = explode(':', $recordId);
		}

		$recordType = substr($recordId, 1, 1);
		$recordNumber = substr($recordId, 2, -1);

		$params = [
			'recordType' => $recordType,
			'recordNumber' => (int)$recordNumber,
			'pickupLocation' => $pickupBranch,
		];

		if ($cancelDate != null) {
			$params['neededBy'] = $cancelDate;
		}

		require_once ROOT_DIR . '/RecordDrivers/RecordDriverFactory.php';
		$record = RecordDriverFactory::initRecordDriverById($this->accountProfile->recordSource . ':' . $recordId);
		$hold_result['bib'] = $recordId;
		if (!$record) {
			$title = null;
		} else {
			$title = $record->getTitle();
			$hold_result['title'] = $title;
		}

		$sierraUrl = $this->accountProfile->vendorOpacUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/{$patron->unique_ils_id}/holds/requests";
		$placeHoldResponse = $this->_postPage('sierra.placeHold', $sierraUrl, json_encode($params));
		if ($placeHoldResponse == null && ($this->lastResponseCode == 200 || $this->lastResponseCode = 204)) {
			$hold_result['success'] = true;
			$hold_result['message'] = translate([
				'text' => "Your hold was placed successfully.",
				'isPublicFacing' => true,
			]);

			$hold_result['api']['title'] = translate([
				'text' => 'Hold placed successfully',
				'isPublicFacing' => true,
			]);
			$hold_result['api']['message'] = translate([
				'text' => 'Your hold was placed successfully.',
				'isPublicFacing' => true,
			]);
			$hold_result['api']['action'] = translate([
				'text' => 'Go to Holds',
				'isPublicFacing' => true,
			]);

			$patron->clearCachedAccountSummaryForSource($this->getIndexingProfile()->name);
			$patron->forceReloadOfHolds();
		} else {
			//Get the hold form
			$message = isset($placeHoldResponse->description) ? $placeHoldResponse->description : $placeHoldResponse->name;
			$hold_result['success'] = false;
			$hold_result['message'] = translate([
				'text' => $message,
				'isPublicFacing' => true,
			]);

			$hold_result['api']['title'] = translate([
				'text' => $message,
				'isPublicFacing' => true,
			]);
			$hold_result['api']['message'] = translate([
				'text' => $message,
				'isPublicFacing' => true,
			]);
			if (isset($placeHoldResponse->code) && isset($placeHoldResponse->details->itemsAsVolumes)) {
				$items = [];
				foreach ($placeHoldResponse->details->itemsAsVolumes as $itemFromSierra) {
					$status = $itemFromSierra->status->display;
					if ($itemFromSierra->status->code == '-' && !empty( $itemFromSierra->status->duedate)) {
						$status = 'CHECKED OUT';
					}
					$items[] = [
						'itemNumber' => '.i' . $itemFromSierra->id . $this->getCheckDigit($itemFromSierra->id),
						'location' => $itemFromSierra->location->name,
						'callNumber' => $itemFromSierra->callNumber,
						'status' => $status,
					];
				}
				$hold_result['items'] = $items;
			}
		}

		return $hold_result;
	}

	public function placeItemHold($patron, $recordId, $itemId, $pickupBranch, $cancelDate = null, $pickupSublocation = null) {
		return $this->placeHold($patron, $itemId, $pickupBranch, $cancelDate);
	}

	public function placeVolumeHold(User $patron, $recordId, $volumeId, $pickupBranch, $pickupSublocation = null) {
		return parent::placeVolumeHold($patron, $recordId, $volumeId, $pickupBranch, $pickupSublocation);
		// TODO: Use Sierra APIs to place volume holds
	}

	function allowFreezingPendingHolds() {
		return false;
	}

	public function hasFastRenewAll(): bool {
		return false;
	}

	public function patronLogin($username, $password, $validatedViaSSO) {
		global $library;
		$username = trim($username);
		$password = trim($password);
		if ($this->accountProfile == null) {
			return false;
		}else {
			$loginMethod = $this->accountProfile->loginConfiguration;
		}
		if ($loginMethod == 'barcode_pin' || $loginMethod == 'name_barcode') {
			//If we use user names, we may need to lookup the barcode by the user name.
//			$params = [
//				'varFieldTag' => 'i',
//				'varFieldContent' => $username,
//				'fields' => 'id,barcodes'
//			];
//			$sierraUrl = $this->accountProfile->vendorOpacUrl;
//			$sierraUrl .= "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/find?";
//			$sierraUrl .= http_build_query($params);
//			$patronInfo = $this->_callUrl('sierra.getPatronByUsername', $sierraUrl);
//			if (!empty($patronInfo->barcodes)){
//				$username = reset($patronInfo->barcodes);
//			}

			//No validate the barcode and pin
			$params = [
				'barcode' => $username,
				'pin' => $password,
				'caseSensitivity' => false,
			];

			$sierraUrl = $this->accountProfile->vendorOpacUrl;
			$sierraUrl .= "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/validate";
			$this->_postPage('sierra.validatePatron', $sierraUrl, json_encode($params));
			if ($this->lastResponseCode != 204) {
				return false;
			}


		} else { // $loginMethod == 'name_barcode'
			//TODO: Do validation using name_barcode login
			return false;
		}

		//We've passed validation, get information for the patron
		$patronInfo = $this->getPatronInfoByBarcode($username);

		if (!$patronInfo) {
			return false;
		}

		$userExistsInDB = false;
		$user = new User();
		$user->source = $this->accountProfile->name;
		$user->username = $patronInfo->id;
		$user->unique_ils_id = $patronInfo->id;
		if ($user->find(true)) {
			$userExistsInDB = true;
		}
		$barcodeField = null;
		$usernameField = null;
		if (!empty($patronInfo->varFields)) {
			foreach ($patronInfo->varFields as $varField) {
				if ($varField->fieldTag == 'b') {
					$barcodeField = $varField;
				}else if ($varField->fieldTag == $library->usernameField) {
					$usernameField = $varField;
				}
			}
		}
		if ($barcodeField != null) {
			$user->cat_username = $barcodeField->content;
			$user->ils_barcode = $barcodeField->content;
		} else {
			$user->cat_username = $username;
			$user->ils_barcode = $username;
		}
		$user->cat_password = $password;
		$user->ils_password = $password;
		if ($usernameField != null) {
			$user->ils_username = $usernameField->content;
		}

		$forceDisplayNameUpdate = false;
		$primaryName = reset($patronInfo->names);
		if (strpos($primaryName, ',') !== false) {
			[
				$lastName,
				$firstName,
			] = explode(',', $primaryName, 2);
		} else {
			$lastName = $primaryName;
			$firstName = '';
		}
		$firstName = trim($firstName);
		$lastName = trim($lastName);
		if ($user->firstname != $firstName) {
			$user->firstname = $firstName;
			$forceDisplayNameUpdate = true;
		}
		if ($user->lastname != $lastName) {
			$user->lastname = isset($lastName) ? $lastName : '';
			$forceDisplayNameUpdate = true;
		}
		if ($forceDisplayNameUpdate) {
			$user->displayName = '';
		}

		$this->loadContactInformationFromApiResult($user, $patronInfo);

		if ($userExistsInDB) {
			$user->update();
		} else {
			$user->created = date('Y-m-d');
			if (!$user->insert()) {
				return null;
			}
		}
		return $user;
	}

	public function getPatronInfoByBarcode($barcode) {
		$params = [
			'varFieldTag' => 'b',
			'varFieldContent' => $barcode,
			'fields' => 'id,names,deleted,suppressed,addresses,phones,emails,expirationDate,homeLibraryCode,moneyOwed,patronType,patronCodes,blockInfo,message,pMessage,langPref,fixedFields,varFields,updatedDate,createdDate',
		];

		$sierraUrl = $this->accountProfile->vendorOpacUrl;
		$sierraUrl .= "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/find?";
		$sierraUrl .= http_build_query($params);

		$response = $this->_callUrl('sierra.findPatronByBarcode', $sierraUrl);
		if (!$response) {
			return false;
		} else {
			if (!empty($response->deleted) || !empty($response->suppressed) || (!empty($response->httpStatus) && $response->httpStatus == 404)) {
				return false;
			} else {
				return $response;
			}
		}
	}

	public function getPatronInfoByUsername($username) {
		global $library;
		$params = [
			'varFieldTag' => $library->usernameField,
			'varFieldContent' => $username,
			'fields' => 'id,names,deleted,suppressed,addresses,phones,emails,expirationDate,homeLibraryCode,moneyOwed,patronType,patronCodes,blockInfo,message,pMessage,langPref,fixedFields,varFields,updatedDate,createdDate',
		];

		$sierraUrl = $this->accountProfile->vendorOpacUrl;
		$sierraUrl .= "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/find?";
		$sierraUrl .= http_build_query($params);

		$response = $this->_callUrl('sierra.findPatronByBarcode', $sierraUrl);
		if (!$response) {
			return false;
		} else {
			if ($response->deleted || $response->suppressed) {
				return false;
			} else {
				return $response;
			}
		}
	}

	public function findNewUser($patronBarcode, $patronUsername) {
		global $library;
		if (!empty($patronBarcode)) {
			$patronInfo = $this->getPatronInfoByBarcode($patronBarcode);
		}else{
			$patronInfo = $this->getPatronInfoByUsername($patronUsername);
		}

		if (!$patronInfo) {
			return false;
		}

		$userExistsInDB = false;
		$user = new User();
		$user->source = $this->accountProfile->name;
		$user->username = $patronInfo->id;
		$user->unique_ils_id = $patronInfo->id;
		if ($user->find(true)) {
			$userExistsInDB = true;
		}
		$barcodeField = null;
		$usernameField = null;
		if (!empty($patronInfo->varFields)) {
			foreach ($patronInfo->varFields as $varField) {
				if ($varField->fieldTag == 'b') {
					$barcodeField = $varField;
				}else if ($varField->fieldTag == $library->usernameField) {
					$usernameField = $varField;
				}
			}
		}
		if ($barcodeField != null) {
			$user->cat_username = $barcodeField->content;
			$user->ils_barcode = $barcodeField->content;
		} else {
			if (!empty($patronBarcode)) {
				$user->cat_username = $patronBarcode;
				$user->ils_barcode = $patronBarcode;
			}
		}
		if ($usernameField != null) {
			$user->ils_username = $usernameField->content;
		} else {
			if (!empty($patronUsername)) {
				$user->ils_username = $patronUsername;
			}
		}

		$forceDisplayNameUpdate = false;
		if ($patronInfo->names != null) {
			$primaryName = reset($patronInfo->names);
		}
		if (strpos($primaryName, ',') !== false) {
			[
				$lastName,
				$firstName,
			] = explode(',', $primaryName, 2);
		} else {
			$lastName = $primaryName;
			$firstName = '';
		}
		$firstName = trim($firstName);
		$lastName = trim($lastName);
		if ($user->firstname != $firstName) {
			$user->firstname = $firstName;
			$forceDisplayNameUpdate = true;
		}
		if ($user->lastname != $lastName) {
			$user->lastname = isset($lastName) ? $lastName : '';
			$forceDisplayNameUpdate = true;
		}
		if ($forceDisplayNameUpdate) {
			$user->displayName = '';
		}

		$this->loadContactInformationFromApiResult($user, $patronInfo);

		if ($userExistsInDB) {
			$user->update();
		} else {
			$user->created = date('Y-m-d');
			if (!$user->insert()) {
				return null;
			}
		}

		return $user;
	}

	public function findNewUserByEmail($patronEmail): mixed {
		return false;
	}

	public function getAccountSummary(User $patron): AccountSummary {
		require_once ROOT_DIR . '/sys/User/AccountSummary.php';
		$summary = new AccountSummary();
		$summary->userId = $patron->id;
		$summary->source = 'ils';
		$summary->resetCounters();
		$patronInfo = $this->getPatronInfoByBarcode($patron->getBarcode());
		if ($patronInfo) {
			$checkouts = $this->getCheckouts($patron);
			$summary->numCheckedOut = count($checkouts);
			foreach ($checkouts as $checkout) {
				if ($checkout->isOverdue()) {
					$summary->numOverdue++;
				}
			}

			$holds = $this->getHolds($patron);
			$summary->numAvailableHolds = count($holds['available']);
			$summary->numUnavailableHolds = count($holds['unavailable']);

			$summary->totalFines = $patronInfo->moneyOwed;

			if (!empty($patronInfo->expirationDate)) {
				[
					$yearExp,
					$monthExp,
					$dayExp,
				] = explode("-", $patronInfo->expirationDate);
				$summary->expirationDate = strtotime($monthExp . "/" . $dayExp . "/" . $yearExp);
			}else{
				//No expiration date set, just set something 20 years in the future
				//$now = time();
				$summary->expirationDate = 0;
			}
		}

		return $summary;
	}

	public function updatePatronInfo($patron, $canUpdateContactInfo, $fromMasquerade): array {
		$result = [
			'success' => false,
			'messages' => [],
		];

		if ($canUpdateContactInfo) {
			global $library;
			$params = [];

			if (isset($_REQUEST['email'])) {
				$patron->email = $_REQUEST['email'];
				$params['emails'] = [$_REQUEST['email']];
			}
			if ($library->allowPatronPhoneNumberUpdates) {
				$params['phones'] = [];
				if (isset($_REQUEST['phone'])) {
					$patron->phone = $_REQUEST['phone'];
					$tmpPhone = new stdClass();
					$tmpPhone->type = 'p';
					$tmpPhone->number = $_REQUEST['phone'];
					$params['phones'][] = $tmpPhone;
				}
			}
			if ($library->allowPatronWorkPhoneNumberUpdates) {
				if (!array_key_exists('phones', $params)) {
					$params['phones'] = [];
				}
				if (isset($_REQUEST['workPhone'])) {
					$patron->_workPhone = $_REQUEST['workPhone'];
					$tmpPhone = new stdClass();
					$tmpPhone->type = 't';
					$tmpPhone->number = $_REQUEST['workPhone'];
					$params['phones'][] = $tmpPhone;
				}
			}
			if ($library->allowPatronAddressUpdates) {
				$params['addresses'] = [];
				$address = new stdClass();
				$address->lines = [];
				$address->type = 'a';
				$address->lines[] = $_REQUEST['address1'];
				$cityStateZip = $_REQUEST['city'] . ', ' . $_REQUEST['state'] . ' ' . $_REQUEST['zip'];
				$address->lines[] = $cityStateZip;

				$params['addresses'][] = $address;
			}

			if (isset($_REQUEST['notices']) && !empty($_REQUEST['notices'])) {
				$params['fixedFields'] = [];
				$noticeField = new stdClass();
				$fieldValue = new stdClass();
				$fieldValue->label = 'Notice Preference';
				$fieldValue->value = $_REQUEST['notices'];
				$noticeField->{'268'} = $fieldValue;
				$params['fixedFields']['268'] = $fieldValue;
			}

			$sierraUrl = $this->accountProfile->vendorOpacUrl;
			$sierraUrl = $sierraUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/" . $patron->unique_ils_id;
			$updatePatronResponse = $this->_sendPage('sierra.updatePatron', 'PUT', $sierraUrl, json_encode($params));

			if ($this->lastResponseCode == 204) {
				$result['success'] = true;
				$result['messages'][] = 'Your account was updated successfully.';
				$patron->update();
			} else {
				$result['messages'][] = 'Unable to update patron. ' . $this->lastErrorMessage;
			}
		} else {
			$result['messages'][] = 'You do not have permission to update profile information.';
		}

		return $result;
	}

	public function getSelfRegistrationTerms() {
		global $library;

		if (!empty($library->selfRegistrationFormId)) {
			require_once ROOT_DIR . '/sys/SelfRegistrationForms/SierraSelfRegistrationForm.php';
			$selfRegistrationForm = new SierraSelfRegistrationForm();
			$selfRegistrationForm->id = $library->selfRegistrationFormId;
			if ($selfRegistrationForm->find(true)) {
				$tosId = $selfRegistrationForm->termsOfServiceSetting;
				require_once ROOT_DIR . '/sys/SelfRegistrationForms/SelfRegistrationTerms.php';
				$tos = new SelfRegistrationTerms();
				$tos->id = $tosId;
				if ($tosId != -1){
					if ($tos->find(true)) {
						return $tos;
					}
				}
			}
			return null;
		}
		return null;
	}

	public function getSelfRegistrationFields() {
		global $library;

		$pickupLocations = [];
		$location = new Location();
		//0 = no restrictions (ignore location setting)
		if ($library->selfRegistrationLocationRestrictions == 1) {
			//All Library Locations (ignore location setting)
			$location->libraryId = $library->libraryId;
		} elseif ($library->selfRegistrationLocationRestrictions == 2) {
			//Valid pickup locations
			$location->whereAdd('validSelfRegistrationBranch <> 2');
			$location->orderBy('isMainBranch DESC, displayName');
		} elseif ($library->selfRegistrationLocationRestrictions == 3) {
			//Valid pickup locations
			$location->libraryId = $library->libraryId;
			$location->whereAdd('validSelfRegistrationBranch <> 2');
			$location->orderBy('isMainBranch DESC, displayName');
		}
		if ($location->find()) {
			while ($location->fetch()) {
				$pickupLocations[$location->code] = $location->displayName;
			}
			if (count($pickupLocations) > 1) {
				array_unshift($pickupLocations, translate([
					'text' => 'Please select a location',
					'isPublicFacing' => true,
				]));
			}
		}

		global $library;
		$hasCustomSelfRegistrationFrom = false;

		if (!empty($library->selfRegistrationFormId)) {
			require_once ROOT_DIR . '/sys/SelfRegistrationForms/SierraSelfRegistrationForm.php';
			$selfRegistrationForm = new SierraSelfRegistrationForm();
			$selfRegistrationForm->id = $library->selfRegistrationFormId;
			if ($selfRegistrationForm->find(true)) {
				$customFields = $selfRegistrationForm->getFields();
				if ($customFields != null && count($customFields) > 0) {
					$hasCustomSelfRegistrationFrom = true;
				}
			}
		}

		$pickupLocationField = [
			'property' => 'pickupLocation',
			'type' => 'enum',
			'label' => 'Home Library',
			'description' => 'Please choose the Library location you would prefer to use',
			'values' => $pickupLocations,
			'required' => true,
		];

		$fields = [];
		if ($hasCustomSelfRegistrationFrom) {
			$hiddenDefault = false;
			$fields['librarySection'] = [
				'property' => 'librarySection',
				'type' => 'section',
				'label' => 'Library',
				'hideInLists' => true,
				'expandByDefault' => true,
				'properties' => [],
			];
			$fields['identitySection'] = [
				'property' => 'identitySection',
				'type' => 'section',
				'label' => 'Identity',
				'hideInLists' => true,
				'expandByDefault' => true,
				'properties' => [],
			];
			$fields['mainAddressSection'] = [
				'property' => 'mainAddressSection',
				'type' => 'section',
				'label' => 'Main Address',
				'hideInLists' => true,
				'expandByDefault' => true,
				'properties' => [],
			];
			$fields['contactInformationSection'] = [
				'property' => 'contactInformationSection',
				'type' => 'section',
				'label' => 'Contact Information',
				'hideInLists' => true,
				'expandByDefault' => true,
				'properties' => [],
			];
			//Use self registration fields
			/** @var SelfRegistrationFormValues $customField */
			foreach ($customFields as $customField) {
				if ($customField->ilsName == 'library') {
					if (count($pickupLocations) == 1) {
						$fields['librarySection'] = [
							'property' => 'librarySection',
							'type' => 'section',
							'label' => 'Library',
							'hideInLists' => true,
							'expandByDefault' => true,
							'properties' => [
								$customField->ilsName => $pickupLocationField,
							],
							'hiddenByDefault' => true,
						];
					} else {
						$fields['librarySection'] = [
							'property' => 'librarySection',
							'type' => 'section',
							'label' => 'Library',
							'hideInLists' => true,
							'expandByDefault' => true,
							'properties' => [
								$customField->ilsName => $pickupLocationField,
							],
						];
					}
				} elseif ($customField->ilsName == 'zip' && !empty($library->validSelfRegistrationZipCodes)) {
					$fields[$customField->section]['properties'][] = [
						'property' => $customField->ilsName,
						'type' => $customField->fieldType,
						'label' => $customField->displayName,
						'required' => $customField->required,
						'note' => $customField->note,
						'validationPattern' => $library->validSelfRegistrationZipCodes,
						'validationMessage' => translate([
							'text' => 'Please enter a valid zip code',
							'isPublicFacing' => true,
						]),
					];
				} elseif ($customField->ilsName == 'state') {
					if (!empty($library->validSelfRegistrationStates)){
						$validStates = explode('|', $library->validSelfRegistrationStates);
						$validStates = array_combine($validStates, $validStates);
						$fields[$customField->section]['properties'][] = [
							'property' => $customField->ilsName,
							'type' => 'enum',
							'values' => $validStates,
							'label' => $customField->displayName,
							'required' => $customField->required,
							'note' => $customField->note,
						];
					} else {
						$fields[$customField->section]['properties'][] = [
							'property' => $customField->ilsName,
							'type' => $customField->fieldType,
							'label' => $customField->displayName,
							'required' => $customField->required,
							'note' => $customField->note,
							'maxLength' => 2,
						];
					}
				} else {
					$fields[$customField->section]['properties'][] = [
						'property' => $customField->ilsName,
						'type' => $customField->fieldType,
						'label' => $customField->displayName,
						'required' => $customField->required,
						'note' => $customField->note
					];
				}
			}
			foreach ($fields as $section) {
				if ($section['type'] == 'section') {
					if (empty($section['properties'])) {
						unset ($fields[$section['property']]);
					}
				}
			}
		}
		return $fields;
	}

	public function selfRegister(): array {
		global $library;
		$selfRegResult = [
			'success' => false,
			'message' => 'Unknown Error while registering your account'
		];

		$selfRegistrationForm = null;
		$formFields = null;
		if ($library->selfRegistrationFormId > 0){
			$selfRegistrationForm = new SierraSelfRegistrationForm();
			$selfRegistrationForm->id = $library->selfRegistrationFormId;
			if ($selfRegistrationForm->find(true)) {
				$formFields = $selfRegistrationForm->getFields();
			}else {
				$selfRegistrationForm = null;
			}
		}

		$params = [];

		if ($formFields != null) {
			foreach ($formFields as $fieldObj){
				$field = $fieldObj->ilsName;
				if ($field == 'firstName') {
					if (isset($_REQUEST['middleName'])) {
						$fullName = $_REQUEST['firstName'] . ' ' . $_REQUEST['middleName'] . ' ' . $_REQUEST['lastName'];
					} else {
						$fullName = $_REQUEST['firstName'] . ' ' . $_REQUEST['lastName'];
					}
					$params['names'] = [$fullName];
				}
				elseif ($field == 'birthDate') {
					$params['birthDate'] = $_REQUEST['birthDate'];
				}
				elseif ($field == 'email') {
					$params['emails'] = [$_REQUEST['email']];
					if ($selfRegistrationForm->selfRegEmailBarcode) {
						$params['barcodes'] = [$_REQUEST['email']];
					}
				}
				elseif ($field == 'phone') {
					$tmpPhone = new stdClass();
					$tmpPhone->type = 'p';
					$tmpPhone->number = $_REQUEST['phone'];
					$params['phones'][] = $tmpPhone;
				}
				elseif ($field == 'street') {
					$params['addresses'] = [];
					$address = new stdClass();
					$address->lines = [];
					$address->type = 'a';
					$address->lines[] = $_REQUEST['street'];
					$cityStateZip = $_REQUEST['city'] . ', ' . $_REQUEST['state'] . ' ' . $_REQUEST['zip'];
					$address->lines[] = $cityStateZip;

					$params['addresses'][] = $address;
				}
				elseif ($field == 'barcode') {
					$params['barcodes'] = [$_REQUEST['barcode']];
				}
				elseif ($field == 'pin') {
					$params['pin'] = $_REQUEST['pin'];
				}
			}
			$params['homeLibraryCode'] = $_REQUEST['pickupLocation'];
			$params['patronType'] = (int)$selfRegistrationForm->selfRegPatronCode;
		}

		$sierraUrl = $this->accountProfile->vendorOpacUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/";
		$this->_postPage('sierra.createPatron', $sierraUrl, json_encode($params));

		if ($this->lastResponseCode == 200) {
			$selfRegResult = [
				'success' => true,
				'barcode' => $params['barcodes'][0],
				'password' => $params['pin']
			];
		}

		return $selfRegResult;
	}

	public function getFines($patron = null, $includeMessages = false): array {
		$fines = [];

		$params = [
			'fields' => 'default,assessedDate,itemCharge,processingFee,billingFee,chargeType,paidAmount,datePaid,description,returnDate,location,description,invoiceNumber',
		];

		$patronId = $patron->unique_ils_id;
		$sierraUrl = $this->accountProfile->vendorOpacUrl;
		$sierraUrl = $sierraUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/" . $patronId . "/fines?";
		$sierraUrl .= http_build_query($params);

		$finesResponse = $this->_callUrl('sierra.getFines', $sierraUrl);
		if ($finesResponse && $finesResponse->total > 0) {
			foreach ($finesResponse->entries as $fineEntry) {
				$fineUrl = $fineEntry->id;
				$fineId = substr($fineUrl, strrpos($fineUrl, '/') + 1);
				$fineAmount = $fineEntry->itemCharge + $fineEntry->processingFee + $fineEntry->billingFee;
				$message = '';
				if (isset($fineEntry->description)) {
					$message = $fineEntry->description;
				} else {
					if (isset($fineEntry->item)) {
						preg_match($this->urlIdRegExp, $fineEntry->item, $m);
						$itemIdShort = $m[1];
						$itemId = ".i" . $itemIdShort . $this->getCheckDigit($itemIdShort);
						if ($itemIdShort !=null ) {
							$message = $this->getTitleByItemId($itemId, $itemIdShort);
						} else {
							$message = translate([
								'text' => 'Title not available - contact library. ',
								'isPublicFacing' => true,
								'inAttribute' => true,
							]);
						}
					}
				}
				$fines[] = [
					'fineId' => $fineId,
					'reason' => $fineEntry->chargeType->display,
					'type' => $fineEntry->chargeType->display,
					'amount' => $fineAmount,
					'amountVal' => $fineAmount,
					'message' => $message,
					'amountOutstanding' => $fineAmount - $fineEntry->paidAmount,
					'amountOutstandingVal' => $fineAmount - $fineEntry->paidAmount,
					'date' => date('M j, Y', strtotime($fineEntry->assessedDate)),
					'invoiceNumber' => $fineEntry->invoiceNumber,
				];
			}
		}
		return $fines;
	}

	function showOutstandingFines() {
		return true;
	}

	public function completeFinePayment(User $patron, UserPayment $payment) {
		/** @noinspection PhpArrayIndexImmediatelyRewrittenInspection */
		$result = [
			'success' => false,
			'message' => '',
		];

		$userFines = $this->getFines($patron);

		//Before adding payments, we need to

		$paymentParams = [
			'payments' => [],
		];

		$finePayments = explode(',', $payment->finesPaid);
		foreach ($finePayments as $finePayment) {
			[
				$fineId,
				$paymentAmount,
			] = explode('|', $finePayment);

			//Find the fine in the list of user payments so we can tell if it's fully paid or partially paid
			$fineInvoiceNumber = '';
			foreach ($userFines as $userFine) {
				if ($userFine['fineId'] == $fineId) {
					$fineInvoiceNumber = $userFine['invoiceNumber'];
					break;
				}
			}

			$paymentType = 1; //Fully or partially paid, do not waive the remainder

			$tmpPayment = new stdClass();
			$tmpPayment->amount = (int)(round((float)$paymentAmount * 100));
			$tmpPayment->paymentType = $paymentType;
			$tmpPayment->invoiceNumber = (string)$fineInvoiceNumber;
			$tmpPayment->initials = 'aspen';

			$paymentParams['payments'][] = $tmpPayment;
		}

		$patronId = $patron->unique_ils_id;
		$sierraUrl = $this->accountProfile->vendorOpacUrl;
		$sierraUrl = $sierraUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/" . $patronId . "/fines/payment";

		$makePaymentResponse = $this->_sendPage('sierra.addPayment', 'PUT', $sierraUrl, json_encode($paymentParams));

		if ($this->lastResponseCode == 200 || $this->lastResponseCode == 204) {
			$result['success'] = true;
		} else {
			$result['success'] = false;
			if (isset($makePaymentResponse->description)) {
				$result['message'] = $makePaymentResponse->description;
			} else {
				$result['message'] = 'Could not record fine payment.';
			}
		}

		$patron->clearCachedAccountSummaryForSource($this->getIndexingProfile()->name);
		return $result;
	}

	/** @noinspection PhpRedundantMethodOverrideInspection */
	function importListsFromIls($patron) {
		//There is no way to do this from the APIs so we need to resort to screen scraping.
		return parent::importListsFromIls($patron);
	}

	public function loadContactInformation(User $user) {
		$patronInfo = $this->getPatronInfoByBarcode($user->getBarcode());

		if (!$patronInfo) {
			return;
		}
		$this->loadContactInformationFromApiResult($user, $patronInfo);
	}

	private function loadContactInformationFromApiResult(User $user, stdClass $patronInfo) {
		$user->_fullname = reset($patronInfo->names);
		if (!empty($patronInfo->addresses)) {
			$primaryAddress = reset($patronInfo->addresses);
			$user->_address1 = $primaryAddress->lines[0];
			if (array_key_exists(1, $primaryAddress->lines)) {
				$line2 = $primaryAddress->lines[1];
				if (strpos($line2, ',')) {
					$user->_city = substr($line2, 0, strrpos($line2, ','));
					$stateZip = trim(substr($line2, strrpos($line2, ',') + 1));
					$user->_state = substr($stateZip, 0, strrpos($stateZip, ' '));
					$user->_zip = substr($stateZip, strrpos($stateZip, ' '));
				} else {
					$user->_city = $line2;
				}
			}
		}
		if (!empty($patronInfo->phones)) {
			foreach ($patronInfo->phones as $phoneInfo) {
				if ($phoneInfo->type == 'p') {
					$user->phone = $phoneInfo->number;
				}elseif ($phoneInfo->type == 't') {
					$user->_workPhone = $phoneInfo->number;
				}
			}
		}
		if (!empty($patronInfo->emails)) {
			$user->email = reset($patronInfo->emails);
		}

		$homeLocationCode = $patronInfo->homeLibraryCode;
		$location = new Location();
		$location->code = $homeLocationCode;
		if (!$location->find(true)) {
			unset($location);
		}

		if (empty($user->homeLocationId) || (isset($location) && $user->homeLocationId != $location->locationId)) { // When homeLocation isn't set or has changed
			if (empty($user->homeLocationId) && !isset($location)) {
				// homeBranch Code not found in location table and the user doesn't have an assigned homelocation,
				// try to find the main branch to assign to user
				// or the first location for the library
				global $library;

				$location = new Location();
				$location->libraryId = $library->libraryId;
				$location->orderBy('isMainBranch desc'); // gets the main branch first or the first location
				if (!$location->find(true)) {
					// Seriously no locations even?
					global $logger;
					$logger->log('Failed to find any location to assign to user as home location', Logger::LOG_ERROR);
					unset($location);
				}
			}
			if (isset($location)) {
				$user->homeLocationId = $location->locationId;
				if (empty($user->myLocation1Id)) {
					$user->myLocation1Id = ($location->nearbyLocation1 > 0) ? $location->nearbyLocation1 : $location->locationId;
					//Get display name for preferred location 1
					$myLocation1 = new Location();
					$myLocation1->locationId = $user->myLocation1Id;
					if ($myLocation1->find(true)) {
						$user->_myLocation1 = $myLocation1->displayName;
					}
				}

				if (empty($user->myLocation2Id)) {
					$user->myLocation2Id = ($location->nearbyLocation2 > 0) ? $location->nearbyLocation2 : $location->locationId;
					//Get display name for preferred location 2
					$myLocation2 = new Location();
					$myLocation2->locationId = $user->myLocation2Id;
					if ($myLocation2->find(true)) {
						$user->_myLocation2 = $myLocation2->displayName;
					}
				}
			}
		}

		if (isset($location)) {
			//Get display names that aren't stored
			$user->_homeLocationCode = $location->code;
			$user->_homeLocation = $location->displayName;
		}

		if (!empty($patronInfo->expirationDate)) {
			$user->_expires = $patronInfo->expirationDate;
			[
				$yearExp,
				$monthExp,
				$dayExp,
			] = explode("-", $user->_expires);
			$timeExpire = strtotime($monthExp . "/" . $dayExp . "/" . $yearExp);
			$timeNow = time();
			$timeToExpire = $timeExpire - $timeNow;
			if ($timeToExpire <= 30 * 24 * 60 * 60) {
				if ($timeToExpire <= 0) {
					$user->_expired = 1;
				}
				$user->_expireClose = 1;
			}
		}

		$finesVal = $patronInfo->moneyOwed;
		$user->_fines = sprintf('$%01.2f', $finesVal);
		$user->_finesVal = $finesVal;
		$user->patronType = $patronInfo->patronType;
		$user->_notices = $patronInfo->fixedFields->{'268'}->value;
		switch ($user->_notices) {
			case '-':
				$user->_noticePreferenceLabel = 'none';
				break;
			case 'a':
				$user->_noticePreferenceLabel = 'Mail';
				break;
			case 'p':
				$user->_noticePreferenceLabel = 'Telephone';
				break;
			case 'z':
				$user->_noticePreferenceLabel = 'Email';
				break;
			default:
				$user->_noticePreferenceLabel = 'none';
		}
	}

//	function getPasswordPinValidationRules() : array {
//		return [
//			'minLength' => 4,
//			'maxLength' => 60,
//			'onlyDigitsAllowed' => false,
////		'requireStrongPassword' => false
//		];
//	}

	function updatePin(User $patron, ?string $oldPin, string $newPin): array {
		if ($patron->getPasswordOrPin() != $oldPin) {
			return [
				'success' => false,
				'message' => translate([
					'text' => 'The old PIN provided is incorrect.',
					'isPublicFacing' => true,
				]),
			];
		}
		/** @noinspection PhpArrayIndexImmediatelyRewrittenInspection */
		$result = [
			'success' => false,
			'message' => "Unknown error updating password.",
		];
		$params = [
			'pin' => $newPin,
		];
		$sierraUrl = $this->accountProfile->vendorOpacUrl;
		$sierraUrl = $sierraUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/" . $patron->unique_ils_id;
		$updatePatronResponse = $this->_sendPage('sierra.updatePatron', 'PUT', $sierraUrl, json_encode($params));
		if ($this->lastResponseCode == 204) {
			$result['success'] = true;
			$result['message'] = 'Your password was updated successfully.';
			$patron->cat_password = $newPin;
			$patron->update();
		} else {
			$message = translate([
				'text' => 'Unable to update PIN. ',
				'isPublicFacing' => true,
				'inAttribute' => true,
			]);
			if (!empty($this->lastErrorMessage)) {
				$message .= translate([
					'text' => $this->lastErrorMessage,
					'isPublicFacing' => true,
					'inAttribute' => true,
				]);
			}
			if (!empty($updatePatronResponse) && !empty($updatePatronResponse->description)) {
				$message .= '<br/>' . translate([
						'text' => $updatePatronResponse->description,
						'isPublicFacing' => true,
						'inAttribute' => true,
					]);
			}
			$result['message'] = $message;
		}
		return $result;
	}

	public function connectToSierraDNA() {
		if ($this->_sierraDNAConnection == null) {
			$accountProfile = $this->accountProfile;
			$this->_sierraDNAConnection = pg_connect("host={$accountProfile->databaseHost} port={$accountProfile->databasePort} dbname={$accountProfile->databaseName} user={$accountProfile->databaseUser} password={$accountProfile->databasePassword}");
		}
		return $this->_sierraDNAConnection;
	}

	public function closeSierraDNAConnection() {
		if ($this->_sierraDNAConnection != null) {
			pg_close($this->_sierraDNAConnection);
			$this->_sierraDNAConnection = null;
		}
	}

	public function getTitleAndAuthorForInnReachCheckout($checkoutId) {
		/** @noinspection SqlResolve */
		$checkoutInfoSql = "SELECT 
			  bib_record_property.best_title as title,
			  bib_record_property.best_author as author,
			  bib_record_property.best_title_norm as sort_title
			FROM 
			  sierra_view.checkout, 
			  sierra_view.bib_record_item_record_link, 
			  sierra_view.bib_record_property
			WHERE 
			  sierra_view.checkout.id = $1
			  AND checkout.item_record_id = bib_record_item_record_link.item_record_id
			  AND bib_record_item_record_link.bib_record_id = bib_record_property.bib_record_id";
		$innReachConnection = $this->connectToSierraDNA();
		$res = pg_query_params($innReachConnection, $checkoutInfoSql, [$checkoutId]);
		$titleAndAuthor = pg_fetch_array($res, 0);
		return $titleAndAuthor;
	}

	public function getTitleAndAuthorForInnReachHold(string $holdId): array {
		/** @noinspection SqlResolve */
		$holdInfoSql = "SELECT 
			  bib_record_property.best_title as title,
			  bib_record_property.best_author as author,
			  bib_record_property.best_title_norm as sort_title
			FROM 
			  sierra_view.hold, 
			  sierra_view.bib_record_item_record_link, 
			  sierra_view.bib_record_property
			WHERE 
			  sierra_view.hold.id = $1
					AND sierra_view.hold.is_ir=true
					AND sierra_view.hold.record_id = bib_record_item_record_link.item_record_id
					AND bib_record_item_record_link.bib_record_id = bib_record_property.bib_record_id";
		$innReachConnection = $this->connectToSierraDNA();
		$res = pg_query_params($innReachConnection, $holdInfoSql, [$holdId]);
		$titleAndAuthor = pg_fetch_array($res, 0);
		return $titleAndAuthor;
	}

	public function showHoldPosition(): bool {
		return true;
	}

	public function showTimesRenewed(): bool {
		return true;
	}

	function updateHomeLibrary(User $patron, string $homeLibraryCode) {
		$result = [
			'success' => false,
			'messages' => [],
		];

		if ($patron->getHomeLibrary()->allowHomeLibraryUpdates) {
			$params = [];

			if (isset($_REQUEST['homeLocation'])) {
				$location = new Location();
				$location->code = $_REQUEST['homeLocation'];
				if (!$location->find(true)) {
					$result['messages'][] = 'Could not find that home location.';
					return $result;
				}
				$patron->homeLocationId = $location->locationId;
				$params['homeLibraryCode'] = $_REQUEST['homeLocation'];
			}

			$sierraUrl = $this->accountProfile->vendorOpacUrl;
			$sierraUrl = $sierraUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/" . $patron->unique_ils_id;
			$updatePatronResponse = $this->_sendPage('sierra.updatePatronHomeLocation', 'PUT', $sierraUrl, json_encode($params));

			if ($this->lastResponseCode == 204) {
				$result['success'] = true;
				$result['messages'][] = translate([
					'text' => 'Your home library was updated successfully.',
					'isPublicFacing' => true,
				]);
				$patron->update();
			} else {
				$result['messages'][] = 'Unable to update patron. ' . $this->lastErrorMessage;
			}
		} else {
			$result['messages'][] = 'You do not have permission to update profile information.';
		}

		return $result;
	}

	public function supportsLoginWithUsername() : bool {
		return true;
	}

	/**
	 * Returns true if reset username is a separate page independent of the patron information page
	 *
	 * @return bool
	 */
	public function showResetUsernameLink(): bool {
		global $library;
		if ($library->allowUsernameUpdates){
			return true;
		}
		return false;
	}

	public function getUsernameValidationRules(): array {
		return [
			'minLength' => 4,
			'maxLength' => 75,
			'additionalRequirements' => translate([
				'text' => 'The username may only contain letters and numbers.',
				'isPublicFacing' => true,
			]),
		];
	}

	public function updateEditableUsername(User $patron, string $username): array {
		global $library;
		/** @noinspection PhpArrayIndexImmediatelyRewrittenInspection */
		$result = [
			'success' => false,
			'message' => 'Unknown error updating username',
		];

		$params = [];
		$params['varFields'] = [];
		$usernameField = new stdClass();
		$usernameField->fieldTag = $library->usernameField;
		$usernameField->content = $username;
		$params['varFields'][] = $usernameField;

		$sierraUrl = $this->accountProfile->vendorOpacUrl;
		$sierraUrl = $sierraUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/" . $patron->unique_ils_id;
		/** @noinspection PhpUnusedLocalVariableInspection */
		$updatePatronResponse = $this->_sendPage('sierra.updateEditableUsername', 'PUT', $sierraUrl, json_encode($params));

		if ($this->lastResponseCode == 204) {
			$result['success'] = true;
			$result['message'] = 'Your account was updated successfully.';
			$patron->ils_username = $username;
			$patron->update();
		} else {
			$result['message'] = 'Unable to update patron. ' . $this->lastErrorMessage;
		}

		return $result;
	}

	public function hasAPICheckout() : bool {
		return true;
	}

	public function checkoutByAPI(User $patron, $barcode, Location $currentLocation): array {
		$result = [
			'success' => false,
			'message' => translate([
				'text' => 'There was an error checking out this title.',
				'isPublicFacing' => true,
			]),
			'title' => translate([
				'text' => 'Unable to checkout title',
				'isPublicFacing' => true,
			]),
			'api' => [
				'title' => translate([
					'text' => 'Unable to checkout title',
					'isPublicFacing' => true,
				]),
				'message' => translate([
					'text' => 'There was an error checking out this title.',
					'isPublicFacing' => true,
				]),
			],
			'itemData' => []
		];

		//Find the correct stat group to use
		$doCheckout = false;
		require_once ROOT_DIR . '/sys/AspenLiDA/SelfCheckSetting.php';
		$scoSettings = new AspenLiDASelfCheckSetting();
		$checkoutLocationSetting = $scoSettings->getCheckoutLocationSetting($currentLocation->code);
		if ($checkoutLocationSetting == 0) {
			//Use the active location, no change needed
			$doCheckout = true;
		}elseif ($checkoutLocationSetting == 1) {
			//Use home location for the user
			$currentLocation = $patron->getHomeLocation();
			$doCheckout = true;
		}else {
			//Use the current location for the item
			//To get the current location, we need to determine if the item is already on hold.
			//If it is, make sure it is on hold for the active user and use the pickup_location
			//If it is not on hold, use the current location for the item
			$sierraDnaConnection = $this->connectToSierraDNA();

			$getItemIdByBarcodeStmt = "SELECT * from sierra_view.item_view where barcode = $1";

			//Lookup the item by barcode
			$getItemRS = pg_query_params($sierraDnaConnection, $getItemIdByBarcodeStmt, [$barcode]);
			if ($getItemRS === false || pg_num_rows($getItemRS) === 0) {
				$result['message'] = translate([
					'text' => 'Unable to checkout this item. Cannot find item for barcode %1%.',
					1 => $barcode,
					'isPublicFacing' => true,
				]);
				$result['api']['message'] = translate([
					'text' => 'Unable to checkout this item. Cannot find item for barcode %1%.',
					1 => $barcode,
					'isPublicFacing' => true,
				]);
			}else{
				if (pg_num_rows($getItemRS) > 1) {
					$result['message'] = translate([
						'text' => 'Unable to complete checkout because more than one item was found for barcode %1%.',
						1 => $barcode,
						'isPublicFacing' => true,
					]);
					$result['api']['message'] = translate([
						'text' => 'Unable to complete checkout because more than one item was found for barcode %1%.',
						1 => $barcode,
						'isPublicFacing' => true,
					]);
				}else{
					$itemInfo = pg_fetch_array($getItemRS, 0);
					$itemRecordIdentifier = $itemInfo['id'];
					$itemHomeLocation = $itemInfo['location_code'];

					//Check to see if the record has a hold
					$getItemHoldStmt = "SELECT * from sierra_view.hold where record_id = $1 and status IN ('b', 'j', 'i')";
					$getHoldRS = pg_query_params($sierraDnaConnection, $getItemHoldStmt, [$itemRecordIdentifier]);
					if ($getHoldRS !== false && pg_num_rows($getHoldRS) == 1) {
						$holdInfo = pg_fetch_array($getHoldRS, 0);

						//Get the patron_record_id
						$getPatronIdStmt = "SELECT id from sierra_view.record_metadata where record_type_code = 'p' and record_num = $1";
						$getPatronIdRS = pg_query_params($sierraDnaConnection, $getPatronIdStmt, [$patron->unique_ils_id]);
						if ($getPatronIdRS !== false && pg_num_rows($getPatronIdRS) == 1) {
							$patronInfo = pg_fetch_array($getPatronIdRS, 0);
							$patronRecordId = $patronInfo['id'];
							if ($holdInfo['patron_record_id'] == $patronRecordId) {
								$checkoutLocationCode = $holdInfo['pickup_location_code'];
								$doCheckout = true;
							}else{
								$result['message'] = translate([
									'text' => 'Unable to complete checkout, this title is on hold for another patron.',
									'isPublicFacing' => true,
								]);
								$result['api']['message'] = translate([
									'text' => 'Unable to complete checkout, this title is on hold for another patron.',
									'isPublicFacing' => true,
								]);
							}
						}else{
							$result['message'] = translate([
								'text' => 'Unable to complete checkout, could not find the patron id.',
								'isPublicFacing' => true,
							]);
							$result['api']['message'] = translate([
								'text' => 'Unable to complete checkout, could not find the patron id.',
								'isPublicFacing' => true,
							]);
						}
					}else{
						//Use the home location for the item
						$checkoutLocationCode = $itemHomeLocation;
						$doCheckout = true;
					}

					if ($doCheckout && !empty($checkoutLocationCode)) {
						//Find the appropriate branch in the branches table. This requires a bit of finesse since the
						//Sierra code in Aspen may not match the code in the home location exactly
						$tmpCurrentLocation = $this->getAspenLocationForSierraLocationCode($checkoutLocationCode);
						if ($tmpCurrentLocation != null) {
							$currentLocation = $tmpCurrentLocation;
						}
					}
				}
			}

			$this->closeSierraDNAConnection();
		}

		if ($doCheckout) {
			$sierraUrl = $this->accountProfile->vendorOpacUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/checkout";

			//On some systems, PHP is not properly encoding the integer statgroup, so we will manually encode it.
			//This is important because Sierra can't handle the statgroup if it is encoded as string
			$jsonEncodedParams = '{';
			$jsonEncodedParams .= '"patronBarcode": "' . $patron->ils_barcode . '",';
			$jsonEncodedParams .= '"itemBarcode": "' . $barcode . '",';
			if (!empty($patron->ils_password)) {
				$jsonEncodedParams .= '"patronPin": "' . $patron->ils_password . '",';
			}
			if (!empty($currentLocation->circulationUsername)) {
				$jsonEncodedParams .= '"username": "' . $currentLocation->circulationUsername . '",';
			}
			if (!empty($currentLocation->statGroup) && $currentLocation->statGroup != -1) {
				$jsonEncodedParams .= '"statgroup": ' . $currentLocation->statGroup . ',';
			}
			if (str_ends_with($jsonEncodedParams, ',')) {
				$jsonEncodedParams = substr($jsonEncodedParams, 0, strlen($jsonEncodedParams) - 1);
			}
			$jsonEncodedParams .= '}';

			$checkoutResult = $this->_postPage('sierra.checkout', $sierraUrl, $jsonEncodedParams);
			if ($this->lastResponseCode == 200) {
				$checkoutLink = $checkoutResult->id;

				$result['success'] = true;
				$result['message'] = translate([
					'text' => 'You have successfully checked out this title.',
					'isPublicFacing' => true,
				]);
				$result['api']['title'] = translate([
					'text' => 'Checkout successful',
					'isPublicFacing' => true,
				]);
				$result['api']['message'] = translate([
					'text' => 'You have successfully checked out this title.',
					'isPublicFacing' => true,
				]);

				//Get information about the checkout
				$checkout = $this->getCheckoutDataFromLink($checkoutLink);
				if ($checkout != null) {
					$itemUrl = $checkout->item;
					$title = $this->getTitleFromItemLink($itemUrl);

					$result['itemData'] = [
						'title' => $title,
						'due' => $checkout->dueDate ?? null,
						'barcode' => $barcode,
					];
				}
			} elseif ($this->lastResponseCode == 405) {
				$result['message'] = translate([
					'text' => 'Checkouts cannot be performed by the provided API user.',
					'isPublicFacing' => true,
				]);
				$result['api']['title'] = translate([
					'text' => 'Checkouts cannot be performed by the provided API user.',
					'isPublicFacing' => true,
				]);
				$result['api']['message'] = translate([
					'text' => 'Checkouts cannot be performed by the provided API user.',
					'isPublicFacing' => true,
				]);
			} elseif ($this->lastResponseCode == 500) {
				$result['message'] = translate([
					'text' => $checkoutResult->name,
					'isPublicFacing' => true,
				]);
				$result['api']['message'] = translate([
					'text' => $checkoutResult->name,
					'isPublicFacing' => true,
				]);
			} elseif (!empty($checkoutResult)) {
				$result['title'] = translate([
					'text' => $checkoutResult->name,
					'isPublicFacing' => true,
				]);
				$result['message'] = translate([
					'text' => $checkoutResult->description,
					'isPublicFacing' => true,
				]);
				$result['api']['title'] = translate([
					'text' => $checkoutResult->name,
					'isPublicFacing' => true,
				]);
				$result['api']['message'] = translate([
					'text' => $checkoutResult->description,
					'isPublicFacing' => true,
				]);
			}
		}

		return $result;
	}

	public function hasAPICheckIn() {
		return true;
	}

	public function checkInByAPI(User $patron, $barcode, Location $currentLocation): array {
		$result = [
			'success' => false,
			'message' => translate([
				'text' => 'There was an error checking in this title.',
				'isPublicFacing' => true,
			]),
			'title' => translate([
				'text' => 'Unable to check in title',
				'isPublicFacing' => true,
			]),
			'api' => [
				'title' => translate([
					'text' => 'Unable to check in title',
					'isPublicFacing' => true,
				]),
				'message' => translate([
					'text' => 'There was an error checking in this title.',
					'isPublicFacing' => true,
				]),
			],
			'itemData' => []
		];

		//Find the correct stat group to use
		$doCheckout = false;
		require_once ROOT_DIR . '/sys/AspenLiDA/SelfCheckSetting.php';
		$scoSettings = new AspenLiDASelfCheckSetting();
		$checkInLocationSetting = $scoSettings->getCheckoutLocationSetting($currentLocation->code);
		if ($checkInLocationSetting == 0) {
			//Use the active location, no change needed
			$doCheckIn = true;
		}elseif ($checkInLocationSetting == 1) {
			//Use home location for the user
			$currentLocation = $patron->getHomeLocation();
			$doCheckIn = true;
		}else {
			//Use the current location for the item
			//To get the current location, we need to determine if the item is already on hold.
			//If it is, make sure it is on hold for the active user and use the pickup_location
			//If it is not on hold, use the current location for the item
			$sierraDnaConnection = $this->connectToSierraDNA();

			$getItemIdByBarcodeStmt = "SELECT * from sierra_view.item_view where barcode = $1";

			//Lookup the item by barcode
			$getItemRS = pg_query_params($sierraDnaConnection, $getItemIdByBarcodeStmt, [$barcode]);
			if ($getItemRS === false || pg_num_rows($getItemRS) === 0) {
				$result['message'] = translate([
					'text' => 'Unable to check in this item. Cannot find item for barcode %1%.',
					1 => $barcode,
					'isPublicFacing' => true,
				]);
				$result['api']['message'] = translate([
					'text' => 'Unable to check in this item. Cannot find item for barcode %1%.',
					1 => $barcode,
					'isPublicFacing' => true,
				]);
			}else{
				if (pg_num_rows($getItemRS) > 1) {
					$result['message'] = translate([
						'text' => 'Unable to complete check in because more than one item was found for barcode %1%.',
						1 => $barcode,
						'isPublicFacing' => true,
					]);
					$result['api']['message'] = translate([
						'text' => 'Unable to complete check in because more than one item was found for barcode %1%.',
						1 => $barcode,
						'isPublicFacing' => true,
					]);
				}else{
					//For check in, we don't need to check holds since it is already in possession of the patron
					$itemInfo = pg_fetch_array($getItemRS, 0);
					$itemHomeLocation = $itemInfo['location_code'];

					$tmpCurrentLocation = $this->getAspenLocationForSierraLocationCode($itemHomeLocation);
					if ($tmpCurrentLocation != null) {
						$currentLocation = $tmpCurrentLocation;
					}
					$doCheckIn = true;
				}
			}

			$this->closeSierraDNAConnection();
		}

		if ($doCheckIn) {
			$sierraUrl = $this->accountProfile->vendorOpacUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/items/checkouts/{$barcode}";
			if (!empty($currentLocation->statGroup) && $currentLocation->statGroup != -1) {
				$sierraUrl .= '?statgroup=' . $currentLocation->statGroup;
			}
			if (!empty($currentLocation->circulationUsername)) {
				if (strpos($sierraUrl, '?') === false) {
					$sierraUrl .= '?';
				}else{
					$sierraUrl .= '&';
				}
				$sierraUrl .= 'username=' . $currentLocation->circulationUsername;
			}

			$checkoutResult = $this->_sendPage( 'sierra.checkin', 'DELETE', $sierraUrl);
			if ($this->lastResponseCode >= 200 && $this->lastResponseCode < 300) {
				$result['success'] = true;
				$result['message'] = translate([
					'text' => 'You have successfully checked in this title.',
					'isPublicFacing' => true,
				]);
				$result['api']['title'] = translate([
					'text' => 'Check in successful',
					'isPublicFacing' => true,
				]);
				$result['api']['message'] = translate([
					'text' => 'You have successfully checked in this title.',
					'isPublicFacing' => true,
				]);
			} elseif (!empty($checkoutResult)) {
				$result['message'] = translate([
					'text' => $checkoutResult->name,
					'isPublicFacing' => true,
				]);
				$result['api']['message'] = translate([
					'text' => $checkoutResult->name,
					'isPublicFacing' => true,
				]);
			}
		}

		return $result;
	}

	public function getCheckoutDataFromLink($checkoutLink) {
		$checkout = $this->_callUrl('sierra.getCheckouts', $checkoutLink);

		if ($this->lastResponseCode == 200) {
			return $checkout;
		}else{
			return null;
		}
	}

	public function getCheckoutDataById($checkoutId) {
		$sierraUrl = $this->accountProfile->vendorOpacUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/patrons/checkouts/$checkoutId";
		return $this->getCheckoutDataFromLink($sierraUrl);
	}

	private function getAspenLocationForSierraLocationCode(string $locationCode) : ?Location {
		$locationFound = false;
		$tmpLocationCode = $locationCode;
		while (!$locationFound && !empty($tmpLocationCode)) {
			$location = new Location();
			$location->whereAdd("code LIKE " . $location->escape($tmpLocationCode . '%'));
			if ($location->find(true)) {
				return $location;
			}
			$tmpLocationCode = substr($tmpLocationCode, 0, strlen($tmpLocationCode) -1);
		}
		return null;
	}

	public function loadLibraries() : array {
		$result = [
			'success' => false,
			'message' => 'Could not load libraries from Sierra'
		];

		$sierraUrl = $this->accountProfile->vendorOpacUrl;
		$sierraUrl = $sierraUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/branches";
		$branches = $this->_callUrl('sierra.getBranches', $sierraUrl);
		if ($branches != null){
			$numUpdated = 0;
			$numErrors = 0;
			$firstTheme = new Theme();
			$firstTheme->orderBy('id');
			$firstTheme->find(true);

			$firstGroupedWorkDisplaySettings = new GroupedWorkDisplaySetting();
			$firstGroupedWorkDisplaySettings->orderBy('id');
			$firstGroupedWorkDisplaySettings->find(true);

			if ($branches->total > 0) {
				foreach ($branches->entries as $entry) {
					$library = new Library();
					$library->ilsCode = $entry->id;
					if (!$library->find(true)){
						$library->subdomain = $entry->id;
						$library->displayName = $entry->name;
						$libraryTheme = new LibraryTheme();
						$libraryTheme->themeId = $firstTheme->id;
						$library->__set('themes', [$libraryTheme]);

						$recordsToInclude = [];
						$recordToInclude = new LibraryRecordToInclude();
						$recordToInclude->indexingProfileId = $this->getIndexingProfile()->id;
						$recordToInclude->markRecordsAsOwned = 1;
						$recordToInclude->location = $entry->id . ".*";
						$recordsToInclude[] = $recordToInclude;

						$recordToInclude = new LibraryRecordToInclude();
						$recordToInclude->indexingProfileId = $this->getIndexingProfile()->id;
						$recordToInclude->markRecordsAsOwned = 0;
						$recordToInclude->location = '.*';
						$recordsToInclude[] = $recordToInclude;

						$library->groupedWorkDisplaySettingId = $firstGroupedWorkDisplaySettings->id;

						$library->__set('recordsToInclude', $recordsToInclude);

						if ($library->insert()){
							$numUpdated++;
						}else{
							$numErrors++;
						}
					}
				}
			}
			if ($numErrors > 0){
				$result = [
					'success' => true,
					'message' => "Loaded $numUpdated libraries from Sierra, $numErrors had errors loading."
				];
			}else{
				$result = [
					'success' => true,
					'message' => "Loaded $numUpdated libraries from Sierra"
				];
			}
		}

		return $result;
	}

	public function loadLocations() : array {
		$result = [
			'success' => false,
			'message' => 'Unknown error loading locations from Sierra'
		];
		$sierraUrl = $this->accountProfile->vendorOpacUrl;
		$sierraUrl = $sierraUrl . "/iii/sierra-api/v{$this->accountProfile->apiVersion}/branches";
		$branches = $this->_callUrl('sierra.getBranches', $sierraUrl);
		if ($branches != null){
			$numUpdated = 0;
			$numErrors = 0;
			if ($branches->total > 0) {
				foreach ($branches->entries as $entry) {
					$library = new Library();
					$library->ilsCode = $entry->id;
					if (!$library->find(true)){
						//Could not get the library
						$numErrors++;
					}else{
						//Got the library
						foreach ($entry->locations as $sierraLocation) {
							$locationCode = $sierraLocation->code;
							$location = new Location();
							$location->code = $locationCode;
							if (!$location->find(true)) {
								//This location has not been added to the database yet.
								$location = new Location();
								$location->code = $locationCode;
								$location->displayName = $sierraLocation->name;
								$location->createSearchInterface = 1;
								$location->showInSelectInterface = 1;
								$location->enableAppAccess = 1;
								$location->theme = -1;
								$location->useLibraryThemes = 1;
								$location->languageAndDisplayInHeader = 1;
								$location->displayExploreMoreBarInSummon = 1;
								$location->displayExploreMoreBarInEbscoEds = 1;
								$location->displayExploreMoreBarInEbscoHost = 1;
								$location->displayExploreMoreBarInCatalogSearch = 1;
								$location->showInLocationsAndHoursList = 1;
								$location->validHoldPickupBranch = 1;
								$location->validSelfRegistrationBranch = 1;
								$location->showHoldButton = 1;
								$location->publicListsToInclude = 4;
								$location->automaticTimeoutLength = 90;
								$location->automaticTimeoutLengthLoggedOut = 450;
								$location->showEmailThis = 1;
								$location->showShareOnExternalSites = 1;
								$location->showFavorites = 1;
								$location->includeLibraryRecordsToInclude = 1;

								$recordToInclude = new LocationRecordToInclude();
								$recordToInclude->indexingProfileId = $this->getIndexingProfile()->id;
								$recordToInclude->markRecordsAsOwned = 1;
								$recordToInclude->location = $locationCode . ".*";
								$recordsToInclude = [$recordToInclude];

								//Figure out the library for the location
								$location->libraryId = $library->libraryId;

								$location->__set('recordsToInclude', $recordsToInclude);

								if ($location->insert()){
									$numUpdated++;
								}else{
									$numErrors++;
								}
							}
						}

					}

				}
			}
			if ($numErrors > 0){
				$result = [
					'success' => true,
					'message' => "Loaded $numUpdated libraries from Sierra, $numErrors had errors loading."
				];
			}else{
				$result = [
					'success' => true,
					'message' => "Loaded $numUpdated libraries from Sierra"
				];
			}
		}

		return $result;
	}
}