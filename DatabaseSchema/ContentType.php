<?php

class LiamW_AlterEgoDetector_DatabaseSchema_ContentType extends LiamW_Shared_DatabaseSchema_Abstract2
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
			10306 => array(
				"INSERT IGNORE INTO xf_content_type (content_type, addon_id, fields) VALUES ('alterego', 'liam_ae_detector', '')"
			)
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
			"DELETE FROM xf_content_type WHERE addon_id='liam_ae_detector'"
		);
	}
}