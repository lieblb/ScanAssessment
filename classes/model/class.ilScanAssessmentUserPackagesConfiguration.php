<?php

require_once 'Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ScanAssessment/classes/model/class.ilScanAssessmentTestConfiguration.php';

class ilScanAssessmentUserPackagesConfiguration
{
	/**
	 * @var int
	 */
	protected $tst_id;

	/**
	 * @var int
	 */
	protected $count_documents;

	/**
	 * @var boolean
	 */
	protected $matriculation_code;

	/**
	 * @var int
	 */
	protected $matriculation_style;

	/**
	 * @var int
	 */
	protected $download_style;

	/**
	 * @var boolean
	 */
	protected $personalised;

	/**
	 * @var int
	 */
	protected $documents_generated;

	/**
	 * @param int $test_obj_id
	 */
	public function __construct($test_obj_id)
	{
		if($test_obj_id > 0)
		{
			$this->setTestId($test_obj_id);
			$this->read();
		}
	}

	public function read()
	{
		/**
		 * @var $ilDB ilDB
		 */
		global $ilDB;

		$res = $ilDB->queryF(
			'SELECT * FROM pl_scas_user_packages WHERE tst_id = %s',
			array('integer'),
			array($this->getTestId())
		);
		$row = $ilDB->fetchAssoc($res);

		$this->setCountDocuments($row['count_documents']);
		$this->setMatriculationCode($row['matriculation_code']);
		$this->setMatriculationStyle($row['matriculation_style']);
		$this->setDownloadStyle($row['download_style']);
		$this->setPersonalised($row['personalised']);
		$this->setDocumentsGenerated($row['documents_generated']);
	}
	
	public function setValuesFromPost()
	{
		$this->setCountDocuments((int) $_POST['count_pdfs']);
		$this->setMatriculationCode((int) $_POST['matriculation']);
		$this->setMatriculationStyle((int) $_POST['coding']);
		$this->setDownloadStyle((int) $_POST['complete_download']);
		$this->setPersonalised((int) $_POST['personalised']);
	}

	public function save()
	{
		/**
		 * @var $ilDB ilDB
		 */

		global $ilDB;

		$ilDB->manipulate('DELETE FROM pl_scas_user_packages WHERE tst_id = '. (int)$this->getTestId());

		$ilDB->insert('pl_scas_user_packages',
			array(
				'tst_id'				=> array('integer', $this->getTestId()),
				'count_documents'		=> array('integer', $this->getCountDocuments()),
				'matriculation_code'	=> array('integer', $this->isMatriculationCode()),
				'matriculation_style'	=> array('integer', $this->getMatriculationStyle()),
				'download_style'		=> array('integer', $this->getDownloadStyle()),
				'personalised'			=> array('integer', $this->isPersonalised()),
				'documents_generated'	=> array('integer', $this->getDocumentsGenerated())
			));
	}

	/**
	 * @return int
	 */
	public function getTestId()
	{
		return $this->tst_id;
	}

	/**
	 * @param int $tst_id
	 */
	public function setTestId($tst_id)
	{
		$this->tst_id = $tst_id;
	}

	/**
	 * @return int
	 */
	public function getCountDocuments()
	{
		return $this->count_documents;
	}

	/**
	 * @param int $count_documents
	 */
	public function setCountDocuments($count_documents)
	{
		$this->count_documents = $count_documents;
	}

	/**
	 * @return boolean
	 */
	public function isMatriculationCode()
	{
		return $this->matriculation_code;
	}

	/**
	 * @param boolean $matriculation_code
	 */
	public function setMatriculationCode($matriculation_code)
	{
		$this->matriculation_code = $matriculation_code;
	}

	/**
	 * @return int
	 */
	public function getMatriculationStyle()
	{
		return $this->matriculation_style;
	}

	/**
	 * @param int $matriculation_style
	 */
	public function setMatriculationStyle($matriculation_style)
	{
		$this->matriculation_style = $matriculation_style;
	}

	/**
	 * @return int
	 */
	public function getDownloadStyle()
	{
		return $this->download_style;
	}

	/**
	 * @param int $download_style
	 */
	public function setDownloadStyle($download_style)
	{
		$this->download_style = $download_style;
	}

	/**
	 * @return boolean
	 */
	public function isPersonalised()
	{
		return $this->personalised;
	}

	/**
	 * @param boolean $personalised
	 */
	public function setPersonalised($personalised)
	{
		$this->personalised = $personalised;
	}

	/**
	 * @return int
	 */
	public function getDocumentsGenerated()
	{
		return $this->documents_generated;
	}

	/**
	 * @param int $documents_generated
	 */
	public function setDocumentsGenerated($documents_generated)
	{
		$this->documents_generated = $documents_generated;
	}
	
	
}