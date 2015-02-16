<?php

class LiamW_AlterEgoDetector_Extend_ControllerPublic_Login extends XFCP_LiamW_AlterEgoDetector_Extend_ControllerPublic_Login
{
	public function actionLogin()
	{
		$parent = parent::actionLogin();

		/** @var LiamW_AlterEgoDetector_Extend_Model_SpamPrevention $spamModel */
		$spamModel = $this->_getSpamModel();
		$userModel = $this->_getUserModel();

		$cookie = $spamModel->getCookieValue();
		$visitor = XenForo_Visitor::getInstance();
		$originalUserId = $visitor->getUserId();
		if (!$originalUserId)
		{
			/* @var $session XenForo_Session */
			$session = XenForo_Application::getSession();
			$session->set('aedOriginalUser', $cookie);
			$this->_debug('Session set');

			return $parent;
		}

		$currentUser = $userModel->getUserById($originalUserId, array(
			'join' => XenForo_Model_User::FETCH_USER_PERMISSIONS
		));
		$currentUser['permissions'] = XenForo_Permission::unserializePermissions($currentUser['global_permission_cache']);

		$options = XenForo_Application::getOptions();
		$bypassCheck = XenForo_Permission::hasPermission($currentUser['permissions'], 'general', 'aedbypass');

		$isBannedCheck = ($options->aedcheckbanned ? !$visitor->get('is_banned') : true);

		$aeDetected = false;

		$userModel = $this->_getUserModel();

		if ($cookie && !$bypassCheck && $isBannedCheck)
		{
			if ($cookie != $originalUserId)
			{
				// AE DETECTED
				$originalUser = $userModel->getUserById($cookie, array(
					'join' => XenForo_Model_User::FETCH_USER_PERMISSIONS
				));
				if ($originalUser && isset($originalUser['user_id']))
				{
					if (isset($originalUser['global_permission_cache']))
					{
						$originalUser['permissions'] = XenForo_Permission::unserializePermissions($originalUser['global_permission_cache']);
						$bypassCheck = XenForo_Permission::hasPermission($originalUser['permissions'], 'general',
							'aedbypass');
					}
					else
					{
						$bypassCheck = false;
						// set a new cookie as the old account was deleted
						$spamModel->setCookieValue($originalUserId);
					}
					if (!$bypassCheck)
					{
						$spamModel->processAlterEgoDetection($originalUser, $currentUser);
					}
				}
			}
			$this->_debug('Line before return (1)');

			return $parent;
		}
		else if (!$bypassCheck)
		{
			// SET COOKIE
			$spamModel->setCookieValue($originalUserId);
		}

		if (!$aeDetected && !$bypassCheck)
		{
			$ipOption = $options->aedcheckips;

			if ($ipOption['checkIp'])
			{
				$users = $userModel->getUsersByIp($_SERVER['REMOTE_ADDR'], array(
					'join' => XenForo_Model_User::FETCH_USER_PERMISSIONS
				));
				if (sizeof($users) > 0)
				{
					foreach ($users as &$originalUser)
					{
						if ($originalUser['user_id'] == $currentUser['user_id'])
						{
							continue;
						}

						if ($originalUser['log_date'] > XenForo_Application::$time - $ipOption['minTime'] * 60)
						{
							$originalUser['permissions'] = XenForo_Permission::unserializePermissions($originalUser['global_permission_cache']);
							$bypassCheck = XenForo_Permission::hasPermission($originalUser['permissions'], 'general',
								'aedbypass');
							if (!$bypassCheck)
							{
								$spamModel->processAlterEgoDetection($originalUser, $currentUser);
							}

							break;
						}
					}
				}
			}
		}

		return $parent;
	}

	private function _getSpamModel()
	{
		return $this->getModelFromCache('XenForo_Model_SpamPrevention');
	}

	private function _debug($message)
	{
		if (XenForo_Application::getOptions()->aeddebugmessages)
		{
			XenForo_Error::debug($message);
		}
	}
}

if (false)
{
	class XFCP_LiamW_AlterEgoDetector_Extend_ControllerPublic_Login extends XenForo_ControllerPublic_Login
	{
	}
}