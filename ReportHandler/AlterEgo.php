<?php

class LiamW_AlterEgoDetector_ReportHandler_AlterEgo extends XenForo_ReportHandler_Abstract
{

	public function getReportDetailsFromContent(array $content)
	{
		$user_id1 = isset($content[0]['user_id']) ? $content[0]['user_id'] : 0;
		$user_id2 = isset($content[1]['user_id']) ? $content[1]['user_id'] : 0;

		return array(
			$user_id1 . "&" . $user_id2,
			$user_id1,
			array(
				$content
			)
		);
	}

	public function getVisibleReportsForUser(array $reports, array $viewingUser)
	{
		foreach ($reports AS $reportId => $report)
		{
			if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'aedviewreport'))
			{
				unset($reports[$reportId]);
			}
		}

		return $reports;
	}

	public function getContentLink(array $report, array $contentInfo)
	{
		return null;
	}

	public function getContentTitle(array $report, array $contentInfo)
	{
		return new XenForo_Phrase('aed_thread_subject', array(
			'username' => @$report['extraContent'][0][0]['username']
		));
	}

}