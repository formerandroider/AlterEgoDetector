<?php

/**
 * Copyright 2014 Liam Williams
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
class LiamW_AlterEgoDetector_Extend_Model_SpamPrevention extends XFCP_LiamW_AlterEgoDetector_Extend_Model_SpamPrevention
{
	public function logScore($phrase, $score, $data = array())
	{
		$data['reason'] = $phrase;

		if (is_numeric($score))
		{
			$data['score'] = sprintf('%+d', $score);
		}
		else
		{
			$data['score'] = '+' . $score;
		}

		$this->_resultDetails[] = array(
			'phrase' => $phrase,
			'data' => $data
		);
	}

	public function allowRegistration(array $user, Zend_Controller_Request_Http $request)
	{
		$result = parent::allowRegistration($user, $request);

		$userModel = $this->_getUserModel();

		$cookie = $this->getCookieValue();
		$this->_debug('$inituser (start): ' . $cookie);
		$type = XenForo_Application::getOptions()->aedregistrationmode;
		// $user['user_id'] && $visitor->getUserId(); are current empty at this stage

		$session = XenForo_Application::getSession();
		if ($cookie = $session->get('aedOriginalUser'))
		{
			$this->_debug('$inituser (in if): ' . $cookie);
		}

		$action = XenForo_Model_SpamPrevention::RESULT_ALLOWED;
		if ($cookie)
		{
			$this->_debug('Cookie true');

			$originalUserId = $cookie;
			$originalUser = $userModel->getUserById($originalUserId, array(
				'join' => XenForo_Model_User::FETCH_USER_PERMISSIONS
			));
			// cookie exists but the user doesn't
			if (is_array($originalUser) && isset($originalUser['username']))
			{
				$originalUser['permissions'] = XenForo_Permission::unserializePermissions($originalUser['global_permission_cache']);
				$originalUsername = $originalUser['username'];

				$bypassCheck = XenForo_Permission::hasPermission($originalUser['permissions'], 'general', 'aedbypass');
				if ($bypassCheck)
				{
					$type = 0;
				}
				$this->_debug('Action register ae detected.');
				switch ($type)
				{
					case 0:
						$this->_debug('Action register ae detected case 0');
						$this->logScore('aed_detectspamreg_accept', 0, array(
							'username' => $originalUsername,
							'user_id' => $originalUserId
						));
						break;
					case 1:
						$this->_debug('Action register ae detected case 1');
						$this->logScore('aed_detectspamreg_moderate', 0, array(
							'username' => $originalUsername,
							'user_id' => $originalUserId
						));
						$action = XenForo_Model_SpamPrevention::RESULT_MODERATED;
						break;
					case 2:
						$this->_debug('Action register ae detected case 2');
						$this->logScore('aed_detectspamreg_reject', 0, array(
							'username' => $originalUsername,
							'user_id' => $originalUserId
						));
						$action = XenForo_Model_SpamPrevention::RESULT_DENIED;
						break;
				}
			}
		}


		if ($action == XenForo_Model_SpamPrevention::RESULT_DENIED)
		{
			$result = XenForo_Model_SpamPrevention::RESULT_DENIED;
		}
		elseif (($result == XenForo_Model_SpamPrevention::RESULT_ALLOWED) && ($action == XenForo_Model_SpamPrevention::RESULT_MODERATED))
		{
			$result = XenForo_Model_SpamPrevention::RESULT_MODERATED;
		}

		$this->_lastResult = $result;

		return $result;
	}

	public function processAlterEgoDetection($originalUser, $alterEgoUser)
	{
		$userModel = $this->_getUserModel();
		$options = XenForo_Application::getOptions();

		if (!$originalUser || !$alterEgoUser || !isset($originalUser['user_id']) || !isset($alterEgoUser['user_id'])) // if any of the users don't exist, skip checking altogether and delete cookie.
		{
			$this->setCookieValue(false);

			return;
		}

		if ($alterEgoUser['user_id'] == $originalUser['user_id'])
		{
			return;
		}

		$newUserId = $alterEgoUser['user_id'];
		// ensure consistent ordering
		if ($alterEgoUser['user_id'] < $originalUser['user_id'])
		{
			$tmp = $originalUser;
			$originalUser = $alterEgoUser;
			$alterEgoUser = $tmp;
		}

		$originalUsername = $originalUser['username'];
		$alterEgoUsername = $alterEgoUser['username'];

		$userLink1 = XenForo_Link::buildPublicLink('full:members', $originalUser);
		$userLink2 = XenForo_Link::buildPublicLink('full:members', $alterEgoUser);

		$title = new XenForo_Phrase('aed_thread_subject', array(
			'username' => $alterEgoUsername
		));
		$message = new XenForo_Phrase('aed_thread_message', array(
			'username1' => $originalUsername,
			'username2' => $alterEgoUsername,
			'userLink1' => $userLink1,
			'userLink2' => $userLink2
		));

		if ($options->aedcreatethread)
		{
			/* @var $threadDw XenForo_DataWriter_Discussion_Thread */
			$threadDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');

			$this->_debug('Initialised Thread DataWriter');

			$forumId = $options->aedforumid;
			$userId = $options->aeduserid;
			$username = $options->aedusername;

			$threadDw->bulkSet(array(
				'user_id' => $userId,
				'node_id' => $forumId,
				'title' => $title,
				'username' => $username
			));

			$firstPostDw = $threadDw->getFirstMessageDw();
			$firstPostDw->set('message', $message);

			$this->_debug('Line before thread datawriter save');
			$threadDw->save();
			$this->_debug('Thread datawriter saved');
		}

		if ($options->aedsendpm)
		{
			/* @var $conversationDw XenForo_DataWriter_ConversationMaster */
			$conversationDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');

			$this->_debug('Conversation datawriter initialised.');

			$conversationStarterId = $options->aedpmsenderid;
			$conversationStarterUsername = $options->aedpmusername;
			$conversationRecipientsOption = str_replace(array(
				"/r",
				"/r/n"
			), "/n", $options->aedpmrecipients);
			$conversationRecipients = explode("/n", $conversationRecipientsOption);

			$starterArray = $userModel->getFullUserById($conversationStarterId, array(
				'join' => XenForo_Model_User::FETCH_USER_FULL | XenForo_Model_User::FETCH_USER_PERMISSIONS
			));
			$starterArray['permissions'] = XenForo_Permission::unserializePermissions($starterArray['global_permission_cache']);

			if ($starterArray)
			{
				$conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER,
					$starterArray);
				$conversationDw->set('user_id', $conversationStarterId);
				$conversationDw->set('username', $conversationStarterUsername);
				$conversationDw->set('title', $title);
				$conversationDw->set('open_invite', 1);
				$conversationDw->set('conversation_open', 1);
				$conversationDw->addRecipientUserNames($conversationRecipients);

				$firstMessageDw = $conversationDw->getFirstMessageDw();
				$firstMessageDw->set('message', $message);

				$this->_debug('Line before conversation save');
				$conversationDw->save();
				$this->_debug('Line after conversation save');
			}
		}

		if ($options->aedreport)
		{
			$this->_debug('reporting initialised.');

			$reporterId = $options->liam_aed_reporter;

			/* @var $reportModel XenForo_Model_Report */
			$reportModel = XenForo_Model::create('XenForo_Model_Report');

			// ensure alter-ego detection doesn't nag
			$makeReport = true;

			$report = $reportModel->getReportByContent('alterego',
				$originalUser['user_id'] . "&" . $alterEgoUser['user_id']);
			if ($report)
			{
				$sendDuplicate = $options->aedreport_senddupe;

				if (isset($sendDuplicate[$report['report_state']]))
				{
					$makeReport = $sendDuplicate[$report['report_state']];
				}
				else
				{
					$makeReport = false;
				}

				$this->_debug('Report State:' . $report['report_state']);
				$this->_debug('Send Dupe State.' . $makeReport);
			}

			if ($makeReport)
			{
				$reportModel->reportContent('alterego', array(
					$originalUser,
					$alterEgoUser
				), "These 2 users appear to be alternate egos!", $userModel->getFullUserById($reporterId));
			}
			else
			{
				$this->_debug('Suppressing duplicate report.');
			}
		}

		if ($options->aedredeploycookie)
		{
			$this->setCookieValue($newUserId);
		}
	}

	/**
	 * Gets the cookie value, or null if the cookie isn't set.
	 *
	 * @return string|null
	 */
	public function getCookieValue()
	{
		$cookieName = XenForo_Application::getOptions()->liam_aed_cookiename;

		if (isset($_COOKIE[$cookieName]))
		{
			return $_COOKIE[$cookieName];
		}
		else
		{
			return null;
		}
	}

	/**
	 * Sets the AED cookie value.
	 *
	 * @param     $value string|boolean The cookie false. False to remove cookie.
	 * @param int $time  int How long the cookie is valid for, in seconds.
	 */
	public function setCookieValue($value, $time = 31536000)
	{
		$cookieName = XenForo_Application::getOptions()->liam_aed_cookiename;

		if ($value === false)
		{
			setcookie($cookieName, false, XenForo_Application::$time - 3600);
		}
		else
		{
			setcookie($cookieName, $value, XenForo_Application::$time + $time);
		}
	}

	/**
	 * @return XenForo_Model_User
	 */
	private function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
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
	class XFCP_LiamW_AlterEgoDetector_Extend_Model_SpamPrevention extends XenForo_Model_SpamPrevention
	{
	}
}