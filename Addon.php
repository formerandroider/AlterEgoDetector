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
abstract class LiamW_AlterEgoDetector_Addon
{

	public static function install($installedAddon)
	{
		if (XenForo_Application::$versionId < 1030070)
		{
			throw new XenForo_Exception("Please upgrade XenForo. 1.3+ is required.", true);
		}

		$versionId = is_array($installedAddon) ? $installedAddon['version_id'] : 0;

		$contentTypeInstaller = LiamW_Shared_DatabaseSchema_Abstract2::create('LiamW_AlterEgoDetector_DatabaseSchema_ContentType');
		$contentTypeInstaller->install($versionId);
		$contentTypeFieldInstaller = LiamW_Shared_DatabaseSchema_Abstract2::create('LiamW_AlterEgoDetector_DatabaseSchema_ContentTypeField');
		$contentTypeFieldInstaller->install($versionId);

		// update cache
		$contentTypes = XenForo_Application::get('contentTypes');
		$contentTypes['alterego']['report_handler_class'] = 'LiamW_AlterEgoDetector_ReportHandler_AlterEgo';
		XenForo_Application::set('contentTypes', $contentTypes);
	}

	public static function uninstall()
	{
		$contentTypeInstaller = LiamW_Shared_DatabaseSchema_Abstract2::create('LiamW_AlterEgoDetector_DatabaseSchema_ContentType');
		$contentTypeInstaller->uninstall();
		$contentTypeFieldInstaller = LiamW_Shared_DatabaseSchema_Abstract2::create('LiamW_AlterEgoDetector_DatabaseSchema_ContentTypeField');
		$contentTypeFieldInstaller->uninstall();

		// update cache
		$contentTypes = XenForo_Application::get('contentTypes');
		unset($contentTypes['alterego']);
		XenForo_Application::set('contentTypes', $contentTypes);
	}

	public static function extendClass($class, array &$extend)
	{
		switch ($class)
		{
			case 'XenForo_ControllerPublic_Login':
				$extend[] = 'LiamW_AlterEgoDetector_Extend_ControllerPublic_Login';
				break;
			case 'XenForo_Model_SpamPrevention':
				$extend[] = 'LiamW_AlterEgoDetector_Extend_Model_SpamPrevention';
				break;
		}
	}
}