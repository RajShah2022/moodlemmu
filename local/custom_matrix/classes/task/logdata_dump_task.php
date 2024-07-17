<?php
namespace local_custom_matrix\task;

class logdata_dump_task extends \core\task\scheduled_task{
	
	public function get_name(){
		return get_string('logdata_dump','local_custom_matrix');
	}
	public function execute(){
		global $DB;
		$querylib = new \local_custom_matrix\querylib();
		//Below code for Internal Type for Monthly -- Start		
		$log_intrecords = $querylib->get_performancelog_groupby(array('type'=> 0,'period' => 'M'));
		foreach($log_intrecords as $logrec){
			$this->insert_data_monthly($logrec);
		}
		//Below code for Internal Type for Monthly -- End

		//Below code for External Type for Monthly -- Start		
		$log_extrecords = $querylib->performance_logs_records(array('type'=> 1,'period' => 'M'));
		foreach($log_extrecords as $logrec){
			$this->insert_data_monthly($logrec);
		}
		//Below code for External Type for Monthly -- End

		//Below code for Internal Type for Quarterly -- Start	
		$this->data_quarterly_internal('Q1');	
		$this->data_quarterly_internal('Q2');	
		$this->data_quarterly_internal('Q3');	
		$this->data_quarterly_internal('Q4');	
		
		//Below code for Internal Type for Quarterly -- End

		//Below code for External Type for Quarterly -- Start	
		$this->data_quarterly_external('Q1');	
		$this->data_quarterly_external('Q2');	
		$this->data_quarterly_external('Q3');	
		$this->data_quarterly_external('Q4');	
		//Below code for External Type for Quarterly -- End

	}
	public function insert_data_monthly($logrecobj){
		global $DB;		
		$querylib = new \local_custom_matrix\querylib();
		$monthlyrecord = $querylib->performance_monthly(array('userid' => $logrecobj->userid,'performancetype' => $logrecobj->performancetype,'month' => $logrecobj->month,'year' => $logrecobj->year));
		if($monthlyrecord){			
	        $logrecobj->id = $monthlyrecord->id;
	        $logrecobj->usermodified = 2;
	        $logrecobj->timemodified = time();
	        $DB->update_record('local_performance_monthly',$logrecobj);
		}else{			
			$DB->insert_record('local_performance_monthly',$logrecobj);  
		}

	}

	public function data_quarterly_internal($quater){
		$querylib = new \local_custom_matrix\querylib();
		$log_intrecords = $querylib->get_performancelog_groupby(array('type'=> 0,'period' => $quater));
		foreach($log_intrecords as $logrec){
			$this->insert_data_quarterly($logrec);
		}
	}
	public function data_quarterly_external($quater){
		$querylib = new \local_custom_matrix\querylib();
		$log_intrecords = $querylib->performance_logs_records(array('type'=> 1,'period' => $quater));
		foreach($log_intrecords as $logrec){
			$this->insert_data_quarterly($logrec);
		}
	}

	public function insert_data_quarterly($logrecobj){
		global $DB;		
		$querylib = new \local_custom_matrix\querylib();
		$quarterrecord = $querylib->performance_quarterly(array('userid' => $logrecobj->userid,'performancetype' => $logrecobj->performancetype,'month' => $logrecobj->month,'year' => $logrecobj->year));
		if($quarterrecord){			
	        $logrecobj->id = $quarterrecord->id;
	        $logrecobj->usermodified = 2;
	        $logrecobj->timemodified = time();
	        $DB->update_record('local_performance_quarterly',$logrecobj);
		}else{			
			$DB->insert_record('local_performance_quarterly',$logrecobj);  
		}
		
	}

}
