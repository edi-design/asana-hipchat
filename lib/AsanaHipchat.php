<?php
/**
 * Class to retrieve results from asana
 * and compare them to formerly retrieved results
 *
 * @author		André Wiedemann (andre@hikewith.me)
 * @package 	asana-hipchat
 * @copyright 	2014 hikewith.me
 * @version 	1.0
  */

class AsanaHipchat
{
	/**
	 * @var Asana
	 */
	private $obj_asana;

	/**
	 * @var HipChat\HipChat
	 */
	private $obj_hipchat;
	/*
	 * script configs
	 */
	private $old_data_file = null;

	/*
	 * asana config
	 */
	private $asana_api_key = null;
	private $asana_workspace_id = null;

	/*
	 * hipchat config
	 */
	private $hipchat_api_key = null;
	private $hipchat_room_id = null;
	private $hipchat_notifications = true;
	private $hipchat_notifier = 'Asana';

	/**
	 * message container to be sent to hipchat
	 */
	private $arr_msg = array();

	/**
	 * array contains pre-formatted messages
	 * @var array
	 */
	private $messages = array(
		'new_workspace' => array(
			'msg' => 'A new workspace has been created.<br />(#%s , <a href="https://app.asana.com/0/%d">&rarr;</a>)',
			'color' => \HipChat\HipChat::COLOR_YELLOW
		),
		'new_project' => array(
			'msg' => 'A new project has been created.<br />(#%s , <a href="https://app.asana.com/0/%d">&rarr;</a>)',
			'color' => \HipChat\HipChat::COLOR_YELLOW
		),
		'new_task' => array(
			'msg' => '<strong>%s</strong> created a new task.<br />&raquo; %s (#%s, due on %s, assigned to %s, <a href="https://app.asana.com/0/%d">&rarr;</a>)',
			'color' => \HipChat\HipChat::COLOR_YELLOW
		),
		'changed_task' => array(
			'msg' => 'The following task has been changed.<br /><strong>&raquo; %s</strong> (#%s, due on %s, <a href="https://app.asana.com/0/%d">&rarr;</a>)<br />field %s was change from %s to %s.',
			'color' => \HipChat\HipChat::COLOR_GRAY
		),
		'assigned_task' => array(
			'msg' => '@<strong>%s</strong>, the following task has been assigned to you.<br />&raquo; %s (#%s, due on %s, [<a href="https://app.asana.com/0/%d">&rarr;</a>)',
			'color' => \HipChat\HipChat::COLOR_GREEN
		),
		'completed_task' => array(
			'msg' => 'The following task has been completed.<br /><strong>&diams; %s</strong> (#%s, due on %s, <a href="https://app.asana.com/0/%d">&rarr;</a>)',
			'color' => \HipChat\HipChat::COLOR_GREEN
		),
	);

	/**
	 * @param boolean $hipchat_notifications
	 */
	public function setHipchatNotifications($hipchat_notifications)
	{
		$this->hipchat_notifications = $hipchat_notifications;
	}

	/**
	 * @param string $hipchat_notifier
	 */
	public function setHipchatNotifier($hipchat_notifier)
	{
		$this->hipchat_notifier = $hipchat_notifier;
	}

	/**
	 * @param string $asana_api_key
	 */
	public function setAsanaApiKey($asana_api_key)
	{
		$this->asana_api_key = $asana_api_key;
	}

	/**
	 * @param int $asana_workspace_id
	 */
	public function setAsanaWorkspaceId($asana_workspace_id)
	{
		$this->asana_workspace_id = $asana_workspace_id;
	}

	/**
	 * @param string $hipchat_api_key
	 */
	public function setHipchatApiKey($hipchat_api_key)
	{
		$this->hipchat_api_key = $hipchat_api_key;
	}

	/**
	 * @param int $hipchat_room_id
	 */
	public function setHipchatRoomId($hipchat_room_id)
	{
		$this->hipchat_room_id = $hipchat_room_id;
	}

	/**
	 * @param null $old_data_file
	 */
	public function setOldDataFile($old_data_file)
	{
		$this->old_data_file = $old_data_file;
	}

	/**
	 * main function that calls the apis
	 * and compare the results.
	 * if something changed, hipchat will be notified
	 */
	public function run()
	{
		// first check dependencies
		$this->checkExternalLibs();

		// init connector
		$this->obj_asana = new Asana($this->asana_api_key);
		$this->obj_hipchat = new HipChat\HipChat(HIPCHAT_API_KEY);

		// get current data
		$arr_curr = $this->getCurrentData();

		// get old data
		$arr_old = $this->getOldData();

		// compare data
		$this->compareData($arr_curr, $arr_old);

		// save new data
		$this->saveData($arr_curr);

		// send first messages during second run to prevent spamming
		if (count($arr_old) > 0)
		{
			// send messages to hipchat
			$this->sendMessages();
		}
	}

	/**
	 * checks if external libraries are linked properly
	 * @throws Exception
	 */
	private function checkExternalLibs()
	{
		if (!file_exists(BASE_DIR . '/ext-lib/asana.php'))
		{
			throw new Exception('you have to copy https://raw.github.com/ajimix/asana-api-php-class/master/asana.php to ext-lib/asana.php');
		}

		if (!file_exists(BASE_DIR. '/ext-lib/HipChat.php'))
		{
			throw new Exception('you have to copy https://raw2.github.com/hipchat/hipchat-php/master/src/HipChat/HipChat.php to ext-lib/HipChat.php');
		}
	}

	/**
	 * sends messages to hipchat
	 */
	private function sendMessages()
	{
		foreach ($this->arr_msg as $msg)
		{
			$this->obj_hipchat->message_room($this->hipchat_room_id, $this->hipchat_notifier, $msg['msg'], $this->hipchat_notifications, $msg['color']);
		}
	}


	/**
	 * loads old data dump from disk
	 *
	 * @return array|mixed
	 */
	private function getOldData()
	{
		$arr_data = array();
		if (!file_exists($this->old_data_file))
		{
			// no existing data dump
			return $arr_data;
		}

		$json = file_get_contents($this->old_data_file);
		$arr_data = json_decode($json, true);

		return $arr_data;
	}

	/**
	 * saves data dump to disk
	 *
	 * @param $arr_data
	 * @throws Exception
	 * @return false|number of bytes written
	 */
	private function saveData($arr_data)
	{
		if (!is_writable($this->old_data_file) && !touch($this->old_data_file))
		{
			// file is not writable
			throw new Exception($this->old_data_file . ' has to be writeable');
		}

		$data = json_encode($arr_data);
		$return = file_put_contents($this->old_data_file, $data);

		return $return;
	}

	private function compareData($arr_curr, $arr_old)
	{
			$this->checkWorkspaces($arr_curr, $arr_old);
	}

	/**
	 * compares workspaces
	 *
	 * @param $arr_curr
	 * @param $arr_old
	 */
	private function checkWorkspaces($arr_curr, $arr_old)
	{
		foreach ($arr_curr as $workspace_id => $workspace)
		{
			if (!array_key_exists($workspace_id, $arr_old))
			{
				// new workspace added
				$this->arr_msg[] = array(
					'msg' => sprintf($this->messages['new_workspace']['msg'], $workspace['name'], $workspace_id),
					'color' => $this->messages['new_workspace']['color']
				);
				// mark all data as new
				$this->checkProjects($workspace['projects'], array());
			}
			else
			{
				// compare projects
				$this->checkProjects($workspace['projects'], $arr_old[$workspace_id]['projects']);
			}
		}
	}

	/**
	 * compares projects
	 *
	 * @param $arr_curr
	 * @param $arr_old
	 */
	private function checkProjects($arr_curr, $arr_old)
	{
		foreach ($arr_curr as $project_id => $project)
		{
			if (!array_key_exists($project_id, $arr_old))
			{
				// new workspace added
				$this->arr_msg[] = array(
					'msg' => sprintf($this->messages['new_project']['msg'], $project['name'], $project_id),
					'color' => $this->messages['new_project']['color']
				);
				// mark all data as new
				$this->checkTasks($project['name'], $project['tasks'], array());
			}
			else
			{
				$this->checkTasks($project['name'], $project['tasks'], $arr_old[$project_id]['tasks']);
			}
		}
	}

	/**
	 * compares task-list
	 *
	 * @param $project_name
	 * @param $arr_curr
	 * @param $arr_old
	 */
	private function checkTasks($project_name, $arr_curr, $arr_old)
	{
		foreach ($arr_curr as $task_id => $task)
		{
			if (!array_key_exists($task_id, $arr_old))
			{
				// new task was added, do not compare task content because it is new
				$this->arr_msg[] = array(
					'msg' => sprintf($this->messages['new_task']['msg'], $task['data']['creator'], $task['name'], $project_name, $task['data']['due_on'], $task['data']['assignee'], $task_id),
					'color' => $this->messages['new_task']['color']
				);
			}
			else
			{
				$this->checkSingleTask($task_id, $task['name'], $task['data'], $arr_old[$task_id]['data']);
			}
		}
	}

	/**
	 * compares the fields of a signle task
	 *
	 * @param $task_id
	 * @param $task_name
	 * @param $arr_curr
	 * @param $arr_old
	 */
	private function checkSingleTask($task_id, $task_name, $arr_curr, $arr_old)
	{
		foreach ($arr_curr as $key => $elem)
		{
			if ($elem != $arr_old[$key])
			{
				switch ($key)
				{
					case 'completed':
						$this->arr_msg[] = array(
							'msg' => sprintf($this->messages['completed_task']['msg'], $task_name, $arr_curr['project_name'], $arr_curr['due_on'], $task_id),
							'color' => $this->messages['completed_task']['color']
						);
						break;
					case 'completed_at':
					case 'modified_at':
						// just skip fields
						break;
					case ' assignee':
						if ($elem != 'not set')
						{
							// only in case a task was assigned, not the assigment revoked
							$this->arr_msg[] = array(
								'msg' => sprintf($this->messages['assigned_task']['msg'], $elem, $task_name, $arr_curr['project_name'], $arr_curr['due_on'], $task_id),
								'color' => $this->messages['assigned_task']['color']
							);
							break;
						}
					default:
						$this->arr_msg[] = array(
							'msg' => sprintf($this->messages['changed_task']['msg'], $task_name, $arr_curr['project_name'], $arr_curr['due_on'], $task_id, $key, $arr_old[$key] ,$elem),
							'color' => $this->messages['changed_task']['color']
						);
						break;
				}
			}

		}
	}


	/**
	 * collects all data from asan api and puts it into an array
	 *
	 * @return array
	 */
	private function getCurrentData()
	{
		$arr_data = array();

		// Get all workspaces
		$workspaces = $this->obj_asana->getWorkspaces();

		// As Asana API documentation says, when response is successful, we receive a 200 in response so...
		if($this->obj_asana->responseCode == "200" && !is_null($workspaces)){
			$workspacesJson = json_decode($workspaces);

			foreach ($workspacesJson->data as $workspace){
				$arr_data[$workspace->id] = array(
					'name' => $workspace->name,
					'projects' => array()
				);

				// Get all projects in the current workspace (all non-archived projects)
				$projects = $this->obj_asana->getProjectsInWorkspace($workspace->id, $archived = false);

				// As Asana API documentation says, when response is successful, we receive a 200 in response so...
				if($this->obj_asana->responseCode == "200" && !is_null($projects)){
					$projectsJson = json_decode($projects);

					foreach ($projectsJson->data as $project){
						$arr_data[$workspace->id]['projects'][$project->id] = array(
							'name' => $project->name,
							'tasks' => array()
						);

						// Get all tasks in the current project
						$tasks = $this->obj_asana->getProjectTasks($project->id);
						$tasksJson = json_decode($tasks);
						if($this->obj_asana->responseCode == "200" && !is_null($tasks)){
							foreach ($tasksJson->data as $task){
								$arr_data[$workspace->id]['projects'][$project->id]['tasks'][$task->id] = array(
									'name' => $task->name,
									'data' => array()
								);

								$this->getTask($project->name, $task->id, $arr_data[$workspace->id]['projects'][$project->id]['tasks'][$task->id]['data']);
							}
						} else {
							echo "Error while trying to connect to Asana, response code: {$this->obj_asana->responseCode}";
						}

					}

				} else {
					echo "Error while trying to connect to Asana, response code: {$this->obj_asana->responseCode}";
				}

			}

		} else {
			echo "Error while trying to connect to Asana, response code: {$this->obj_asana->responseCode}";
		}

		return $arr_data;
	}

	/**
	 * gets needed informations for a single task
	 * @param $project_name
	 * @param $task_id
	 * @param $arr_data
	 * @throws Exception
	 */
	private function getTask($project_name, $task_id, &$arr_data)
	{
		// Get all data for the current task
		$singletask = $this->obj_asana->getTask($task_id);
		if($this->obj_asana->responseCode == "200" && !is_null($singletask)){
			$singletaskJson = json_decode($singletask);
			$taskdata = $singletaskJson->data;

			$creator = htmlentities($taskdata->followers[0]->name, ENT_COMPAT, "UTF-8");

			$assignee = 'not set';
			if (!empty($taskdata->assignee))
			{
				if (!empty($taskdata->assignee->name))
				{
					$assignee = htmlentities($taskdata->assignee->name, ENT_COMPAT, "UTF-8");
				}
			}

			$due_on = $taskdata->due_on;
			if (empty($due_on))
			{
				$due_on = 'not set';
			}

			$arr_data = array(
				'name' => $taskdata->name,
				'project_name' => $project_name,
				'assignee' => $assignee,
				'completed' => $taskdata->completed,
				'completed_at' => $taskdata->completed_at,
				'due_on' => $due_on,
				'created_at' => $taskdata->created_at,
				'modified_at' => $taskdata->modified_at,
				'creator' => $creator // first follower is the creator of the task
			);
		} else {
			throw new Exception('problem getting task: ' . $task_id);
		}
	}

}