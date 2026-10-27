<?php

require_once 'SessionInterface.php';
require_once ROOT_DIR . '/sys/Session/Session.php';

class MySQLSession extends SessionInterface implements SessionUpdateTimestampHandlerInterface {
	public function open($sess_path, $sess_name) {
		return true;
	}

	public function read($sess_id) {
		$s = new Session();
		$s->setSessionId($sess_id);

		if ($s->find(true)) {
			//global $logger;
			//$logger->log("Reading existing session $sess_id", Logger::LOG_DEBUG);
			if ($s->getRememberMe() == '1') {
				$earliestValidSession = time() - self::$rememberMeLifetime;
			} else {
				$earliestValidSession = time() - self::$lifetime;
			}
			if ($s->getLastUsed() < $earliestValidSession) {
				return "";
			}
			SessionInterface::$activeSessionObject = $s;
			return $s->getData();
		} else {
			return "";
		}
	}

	/**
	 * @param $sess_id
	 * @param $data
	 * @return bool
	 */
	public function write($sess_id, $data) : bool{
		//global $logger;
		if ($this->isAjaxRequestThatDoesNotSaveSession()) {
			return true;
		}

		$s = new Session();
		$s->setSessionId($sess_id);
		if ($s->find(true)) {
			//$logger->log("Updating session $sess_id {$_SERVER['REQUEST_URI']}", Logger::LOG_DEBUG);
			$s->setData($data);
			$s->setLastUsed(time());
			if (empty($s->getRememberMe())) {
				if (isset($_REQUEST['rememberMe']) && ($_REQUEST['rememberMe'] === true || $_REQUEST['rememberMe'] === "true")) {
					$s->setRememberMe(1);
				} else {
					$activeUser = UserAccount::getActiveUserObj();
					if (!empty($activeUser)) {
						if ($activeUser->bypassAutoLogout) {
							$s->setRememberMe(1);
						}
					}
				}
			}
			$s->update();
			$result = true;
		} else {
			//Don't bother to count sessions that are from bots. A session starts when a visitor arrives without the cookie,
			//whether or not anything is saved for it yet.
			global $isAJAX;
			if (!BotChecker::isRequestFromBot() && !$isAJAX && empty($_COOKIE[session_name()])) {
				global $aspenUsage;
				$aspenUsage->incSessionsStarted();
			}
			//An empty session reads back the same as no session, so it does not need a row. Crawlers almost never send the
			//cookie back, so their sessions are only kept when the request logged someone in.
			global $module;
			if (empty($data) || ($module != 'API' && BotChecker::isRequestFromBot() && empty($_SESSION['activeUserId']))) {
				return true;
			}
			//$logger->log("Inserting new session $sess_id", Logger::LOG_DEBUG);
			$s->setData($data);
			$s->setCreated(date('Y-m-d h:i:s'));
			$s->setLastUsed(time());
			$s->setRememberMe(0);
			$result = $s->insert();
		}
		//$logger->log(" Result = $result", Logger::LOG_DEBUG);
		return $result;
	}

	/**
	 * PHP calls this instead of write() when the session data did not change during the request,
	 * which is most page views, so it avoids rewriting the session row each time.
	 */
	public function updateTimestamp($id, $data) : bool {
		if ($this->isAjaxRequestThatDoesNotSaveSession()) {
			return true;
		}
		$activeSession = SessionInterface::$activeSessionObject;
		if ($activeSession == null || $activeSession->getSessionId() != $id) {
			//Without a row for this id there is only something to save if the session has data, for example after the id was regenerated
			return empty($data) || $this->write($id, $data);
		}
		//Refreshing last_used once a minute is plenty to keep the session alive
		if (time() - $activeSession->getLastUsed() >= 60) {
			$activeSession->setLastUsed(time());
			return $activeSession->update() !== false;
		}
		return true;
	}

	public function validateId($id) : bool {
		$s = new Session();
		$s->setSessionId($id);
		return $s->find();
	}

	private function isAjaxRequestThatDoesNotSaveSession() : bool {
		global $module;
		global $action;
		if ($module == 'AJAX' || $action == 'AJAX' || $action == 'JSON') {
			//Don't update sessions on AJAX and JSON calls
			if (isset($_REQUEST['method'])) {
				$method = $_REQUEST['method'];
				return (
					$method != 'loginUser' && $method != 'login' &&
					$method != 'initiateMasquerade' && $method != 'endMasquerade' &&
					$method != 'lockFacet' && $method != 'unlockFacet' &&
					$method != 'updateDisplaySettings' && !isset($_REQUEST['showCovers']) &&
					!isset($_REQUEST['sort']) && !isset($_REQUEST['availableHoldSort']) &&
					!isset($_REQUEST['unavailableHoldSort']) && !isset($_REQUEST['autologout']) &&
					// Do not skip session writes for AJAX calls that have set returnToModule or returnToAction.
					!(isset($_SESSION['returnToModule']) || isset($_SESSION['returnToAction']))
				);
			} else {
				return true;
			}
		}
		return false;
	}

	public function destroy($sess_id) {
		// Now do database-specific destruction:
		$s = new Session();
		$s->setSessionId($sess_id);
		if ($s->find(true)) {
			//global $logger;
			//$logger->log("Destroying session $sess_id {$_SERVER['REQUEST_URI']}", Logger::LOG_DEBUG);
			// Perform standard actions required by all session methods:
			parent::destroy($sess_id);

			$numDeleted = $s->delete();
			return $numDeleted == 1;
		} else {
			global $logger;
			$logger->log("Session $sess_id has already been destroyed {$_SERVER['REQUEST_URI']}", Logger::LOG_DEBUG);
			//Already deleted
			return false;
		}

	}

	public function gc($sess_maxlifetime) {
		$s = new Session();
		$earliestValidSession = time() - self::$lifetime;
		$s->setRememberMe('0');
		$s->whereAdd('last_used < ' . $earliestValidSession);
		$s->delete(true);
		//Delete any sessions where remember me was true
		$s2 = new Session();
		$earliestValidRememberMeSession = time() - self::$rememberMeLifetime;
		$s2->setRememberMe ('1');
		$s2->whereAdd('last_used < ' . $earliestValidRememberMeSession);
		$numRememberMeDeleted = $s2->delete(true);

		return true;
	}
}