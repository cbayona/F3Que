<?php

/*

	Que is a small job management library built on top the F3 cache engine

	while(true) {
		if ($job_id = $que->reserve('jobs')) {
			if (dostuff->($que->data($job_id)) {
				$que->complete($job_id);
			} else {
				$que->status($job_id,'failed');
			}
		}
		//Sleep
	}
*/

//! Que messaging system built off F3 Cache
class Que extends Prefab {

	private static $triggers;
	
	var $cache;

	const TTL=(60 * 60 * 48); //Cache TTL for job definitions

	static function setTrigger($channel,$script) {
		static::$triggers[$channel] = $script;
	}
	
	static function checkTriggers($channel) {
		foreach(static::$triggers as $chan=>$script) {
			if ($chan == $channel) {
				exec('bash -c "exec nohup {$script} > /dev/null 2>&1 &"'); //Fork trigger script into background
			}
		}
	}

	function __construct() {
		$this->cache = \Cache::instance();
	}

	function chanKey($channel) { //Cache Key formatting for que channels
		return 'q_'.$channel.'_que';
	}

	function jobID($chanKey) { //Cache key formatting for job ids
		$chanKeyParts = explode("_",$chanKey);
		$rand = md5(uniqid(rand(),true));
		array_splice($chanKeyParts,1,0,$rand);
		return join("_",$chanKeyParts);
	}

	function meta($job_id,$key,$value=NULL) { // Set/Get information about a job from channels job list
		$jobParts = explode("_",$job_id);
		$chanKey = $this->chanKey($jobParts[2]);
		if ($this->cache->exists($chanKey,$jobList)) {
			if (!$this->cache->exists($job_id)) { //Does the job even exist?
				$this->metaRemove($job_id); //If not then remove from our meta list
				return false;
			}
			foreach($jobList as $k=>$jobMeta) {
				if ($jobMeta['id'] == $job_id) {
					if (is_null($value)) return $jobMeta[$key];
					$jobMeta[$key] = $value;
					$jobList[$k] = $jobMeta;
					$this->cache->set($chanKey,$jobList);
					return true;
				}
			}
		} 
		return false;
	}

	function metaRemove($job_id) { //Removes a job from a channels job list
		$jobParts = explode("_",$job_id);
		$chanKey = $this->chanKey($jobParts[2]);
		if ($this->cache->exists($chanKey,$jobList)) {
			$this->cache->clear($job_id); //Remove the job too
			foreach($jobList as $k=>$jobMeta) {
				if ($job_id == $jobMeta['id']) {
					unset($jobList[$k]);
					if (count($jobList) == 0) {
						$this->cache->clear($chanKey);
					} else {
						$this->cache->set($chanKey,$jobList);
					}
					return true;
				}
			}
		} 
		return false;
	}

	function add($channel,$data) { //Add a job to channel with data, return the job_id
		$chanKey = $this->chanKey($channel);
		$job_id = $this->jobID($chanKey);
		$data['id'] = $job_id;
		if ($this->cache->exists($chanKey,$jobList)) {
			$jobList[] = array('id'=>$job_id,'created'=>time(),'status'=>'pending');
			$this->cache->set($chanKey,$jobList);
		} else {
			$this->cache->set($chanKey,array(array('id'=>$job_id,'created'=>time(),'status'=>'pending')));
		}
		$this->cache->set($job_id,$data, static::TTL );
		self::checkTriggers($channel);
		return $job_id;
	}

	function reserve($channel) { //reserves and returns job_id in channel, or false if no jobs.
		$chanKey = $this->chanKey($channel);
		if ($this->cache->exists($chanKey,$jobList)) {
			foreach($jobList as $jobMeta) {
				if ($jobMeta['reserved'] !== true) {
					$job_id = $jobMeta['id'];
					$this->meta($job_id,'reserved',true);
					$this->meta($job_id,'reserved_time',time());
					return $job_id;
				}
			}
		} 
		return false;
	}

	function unreserve($job_id) { //Put job back into que
		return $this->meta($job_id,'reserved',false);
	}

	function status($job_id,$status=NULL) {		
		if (is_null($status)) { //Reading
			$showStatus = $this->meta($job_id,'status');
			if ($this->meta($job_id,'reserved')) {
				$showStatus .= " ( processing ) ";
			}
			return $showStatus;
		} else { //Writing
			$this->meta($job_id,'status',$status);
		}
		
		return $this->meta($job_id,'status',$status);
	}

	function data($job_id) {
		if ($this->cache->exists($job_id,$job)) {
			return $job;
		}
		return false;
	}

	function all($channel) {
		$chanKey = $this->chanKey($channel);
		if ($this->cache->exists($chanKey,$jobList)) {
			$list = array();
			foreach($jobList as $k=>$job) {
				$job_data =  $this->data($job['id']);
				if ($job_data === false) {
					$this->metaRemove($job['id']);
				} else {
					$list[$job['id']] = $job_data;
					$list[$job['id']]['created'] = $job['created'];
				}
			}
			return $list;
		}
		return array();
	}

	function size($channel) {
		$chanKey = $this->chanKey($channel);
		if ($this->cache->exists($chanKey,$jobList)) return count($jobList);
		return 0;
	}

	function complete($job_id,$clear=false) {
		$jobParts = explode("_",$job_id);
		$chanKey = $this->chanKey($jobParts[2]);
		if ($this->cache->exists($chanKey,$jobList)) {
			if ($clear) {
				$this->metaRemove($job_id);
			} else {
				$this->status($job_id,'completed');
			}
		}
	}

	function resetChannel($channel) {
		$this->cache->reset($channel."_que");
	}

	function resetAll() {
		$this->cache->reset("_que");
	}

}
