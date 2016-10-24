<?php

require_once 'Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ScanAssessment/classes/model/class.ilScanAssessmentTestConfiguration.php';

class ilScanAssessmentLayoutConfiguration extends ilScanAssessmentTestConfiguration
{
	protected $uploaded_file;

	protected $path_to_layout;
	/**
	 * @param int $test_obj_id
	 */
	public function __construct($test_obj_id)
	{
		if($test_obj_id > 0)
		{
			$this->setTestId($test_obj_id);
			$this->read();
			$this->path_to_layout = ilUtil::getDataDir() . '/scanAssessment/tst_' . $test_obj_id . '/layout';
		}
	}

	public function read()
	{

	}

	public function setValuesFromPost()
	{
		$this->uploaded_file = ilUtil::stripSlashesRecursive($_POST['layout_upload']);
	}

	public function save()
	{
		$this->ensureSavePathExists($this->path_to_layout);
		if(file_exists($this->uploaded_file['tmp_name']))
		{
			ilUtil::moveUploadedFile($this->uploaded_file['tmp_name'], $this->uploaded_file['name'], $this->path_to_layout .'/'. $this->uploaded_file['name']);
			global $ilUser;
			ilScanAssessmentLog::getInstance()->info(sprintf('File: %s was added to test with id %s by user with the id: %s', $this->uploaded_file['name'], $this->test_id,  $ilUser->getId()));
		}
	}

	/**
	 * @param $path
	 */
	protected function ensureSavePathExists($path)
	{
		if( ! is_dir($path))
		{
			ilUtil::makeDirParents($path);
		}
	}

	/**
	 * @return string
	 */
	public function getPathToLayout()
	{
		return $this->path_to_layout;
	}

}