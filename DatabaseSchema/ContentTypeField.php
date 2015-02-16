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
class LiamW_AlterEgoDetector_DatabaseSchema_ContentTypeField extends LiamW_Shared_DatabaseSchema_Abstract2
{
	/**
	 * Get the install SQL.
	 *
	 * The array should be an associative array of fields, like so:
	 *
	 * versionId =>
	 *        array =>
	 *                SQL Strings
	 *
	 *
	 * @return array
	 */
	protected function _getInstallSql()
	{
		return array(
			10306 => "INSERT IGNORE INTO xf_content_type_field (content_type, field_name, field_value) VALUES ('alterego', 'report_handler_class', 'LiamW_AlterEgoDetector_ReportHandler_AlterEgo')"
		);
	}

	/**
	 * Get the uninstall SQL.
	 *
	 * Unlike the install SQL, this should return an array of SQL code to run. All code will be run.
	 *
	 * @return array
	 */
	protected function _getUninstallSql()
	{
		return array(
			0 => "DELETE FROM xf_content_type_field WHERE content_type='alterego'"
		);
	}
}