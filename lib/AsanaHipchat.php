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
	private $sqlite_data_file = null;

	/*
	 * asana config
	 */
	private $asana_api_key = null;
	private $asana_sleep_interval = 1;
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
	 * @var PDO
	 */
	private $obj_db;

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
			'msg' => '<strong>%s</strong> created a new task.<br />&#9634; %s (#%s, due on %s, assigned to %s, <a href="https://app.asana.com/0/%d">&rarr;</a>)',
			'color' => \HipChat\HipChat::COLOR_YELLOW
		),
		'changed_task' => array(
			'msg' => 'The following task has been changed.<br /><strong>&#9634; %s</strong> (due on %s, <a href="https://app.asana.com/0/%d">&rarr;</a>)<br />Field [ %s ] was changed from "%s" to "%s".',
			'color' => \HipChat\HipChat::COLOR_GRAY
		),
		'assigned_task' => array(
			'msg' => '@<strong>%s</strong>, the following task has been assigned to you.<br />&#9634; %s (due on %s, <a href="https://app.asana.com/0/%d">&rarr;</a>)',
			'color' => \HipChat\HipChat::COLOR_GREEN
		),
		'completed_task' => array(
			'msg' => 'The following task has been completed.<br /><strong>&#10003; %s</strong> (due on %s, <a href="https://app.asana.com/0/%d">&rarr;</a>)',
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
	 * @param int $asana_sleep_interval
	 */
	public function setAsanaSleepInterval($asana_sleep_interval)
	{
		$this->asana_sleep_interval = $asana_sleep_interval;
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
	 * @param null $sqlite_data_file
	 */
	public function setSqliteDataFile($sqlite_data_file)
	{
		$this->sqlite_data_file = $sqlite_data_file;
	}

	/**
	 * @param null $sqlite_data_file
	 */
	public function setAllowedProjects($allowed_projects)
	{
		if (!is_null($allowed_projects)) {
			$this->allowed_projects = explode(" ", $allowed_projects);
		}
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

		// open sqlite-connection
		if ($obj_db = new PDO('sqlite:'. $this->sqlite_data_file)) {
			$this->obj_db = $obj_db;
		} else {
			throw new Exception('could not open sqlite database');
		}

		/** @var PDOStatement $obj_query */
		$obj_query = $this->obj_db->query('SELECT count(*) FROM `workspace`');
		$ws_count = $obj_query->fetchColumn();

		// compare data
		$this->compareData($arr_curr);

		// send first messages during second run to prevent spamming
		if ($ws_count > 0)
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
	 * start point for comparision
	 *
	 * @param $arr_curr
	 */
	private function compareData($arr_curr)
	{
			$this->checkWorkspaces($arr_curr);
	}

	/**
	 * compares workspaces
	 *
	 * @param $arr_curr
	 */
	private function checkWorkspaces($arr_curr)
	{
		foreach ($arr_curr as $workspace_id => $workspace)
		{
			/** @var PDOStatement $obj_query */
			$obj_query = $this->obj_db->query('SELECT `id` FROM `workspace` WHERE `id` = ' . $workspace_id);
			$id = $obj_query->fetchColumn();

			if ($id == false)
			{
				// add workspace to database
				$obj_stmt = $this->obj_db->prepare("INSERT INTO `workspace` VALUES (:id, :name)");
				$obj_stmt->bindParam(':id', $workspace_id);
				$obj_stmt->bindParam(':name', $workspace['name']);

				$obj_stmt->execute();

				// new workspace added
				$this->arr_msg[] = array(
					'msg' => sprintf($this->messages['new_workspace']['msg'], $workspace['name'], $workspace_id),
					'color' => $this->messages['new_workspace']['color']
				);
				// mark all data as new
				$this->checkProjects($workspace['projects'], false);
			}
			else
			{
				// compare projects
				$this->checkProjects($workspace['projects'], $workspace_id);
			}
		}
	}

	/**
	 * compares projects
	 *
	 * @param $arr_curr
	 * @param $workspace_id
	 */
	private function checkProjects($arr_curr, $workspace_id)
	{
		foreach ($arr_curr as $project_id => $project)
		{
			$id = false;
			// worksapce is false if it is set up new
			if ($workspace_id != false)
			{
				/** @var PDOStatement $obj_query */
				$obj_query = $this->obj_db->query('SELECT `id` FROM `project` WHERE `id` = ' . $project_id);
				$id = $obj_query->fetchColumn();
			}

			if ($id == false)
			{
				// add project to database
				$obj_stmt = $this->obj_db->prepare("INSERT INTO `project` VALUES (:id, :workspace_id, :name)");
				$obj_stmt->bindParam(':id', $project_id);
				$obj_stmt->bindParam(':workspace_id', $workspace_id);
				$obj_stmt->bindParam(':name', $project['name']);

				$obj_stmt->execute();

				// new project added
				$this->arr_msg[] = array(
					'msg' => sprintf($this->messages['new_project']['msg'], $project['name'], $project_id),
					'color' => $this->messages['new_project']['color']
				);
				// mark all data as new
				$this->checkTasks($project['name'], $project['tasks'], false);
			}
			else
			{
				$this->checkTasks($project['name'], $project['tasks'], $project_id);
			}
		}
	}

	/**
	 * compares task-list
	 *
	 * @param $project_name
	 * @param $arr_curr
	 * @param $project_id
	 */
	private function checkTasks($project_name, $arr_curr, $project_id)
	{
		foreach ($arr_curr as $task_id => $task)
		{
			$id = false;
			// worksapce is false if it is set up new
			if ($project_id != false)
			{
				/** @var PDOStatement $obj_query */
				$obj_query = $this->obj_db->query('SELECT `id` FROM `task` WHERE `id` = ' . $task_id);
				$id = $obj_query->fetchColumn();
			}

			if ($id == false)
			{
				// add project to database
				$obj_stmt = $this->obj_db->prepare("INSERT INTO `task` (id, project_id, name, assignee, completed, completed_at, due_on, created_at, modified_at, creator) VALUES (:id, :project_id, :name, :assignee, :completed, :completed_at, :due_on, :created_at, :modified_at, :creator)");

				$obj_stmt->bindParam(':id', $task_id);
				$obj_stmt->bindParam(':project_id', $project_id);
				$obj_stmt->bindParam(':name', $task['name']);
				$obj_stmt->bindParam(':assignee', $task['data']['assignee']);
				$obj_stmt->bindParam(':completed', $task['data']['completed']);
				$obj_stmt->bindParam(':completed_at', $task['data']['completed_at']);
				$obj_stmt->bindParam(':due_on', $task['data']['due_on']);
				$obj_stmt->bindParam(':created_at', $task['data']['created_at']);
				$obj_stmt->bindParam(':modified_at', $task['data']['modified_at']);
				$obj_stmt->bindParam(':creator', $task['data']['creator']);

				$obj_stmt->execute();

				// new task was added, do not compare task content because it is new
				$this->arr_msg[] = array(
					'msg' => sprintf($this->messages['new_task']['msg'], $task['data']['creator'], $task['name'], $project_name, $task['data']['due_on'], $task['data']['assignee'], $task_id),
					'color' => $this->messages['new_task']['color']
				);
			}
			else
			{
				$this->checkSingleTask($task_id, $task['name'], $task['data']);
			}
		}
	}

	/**
	 * compares the fields of a signle task
	 *
	 * @param $task_id
	 * @param $task_name
	 * @param $arr_curr
	 */
	private function checkSingleTask($task_id, $task_name, $arr_curr)
	{
		/** @var PDOStatement $obj_query */
		$obj_query = $this->obj_db->query('SELECT * FROM `task` WHERE `id` = ' . $task_id);
		$arr_old = $obj_query->fetchAll();

		foreach ($arr_curr as $key => $elem)
		{
			if ($elem != $arr_old[0][$key])
			{
				switch ($key)
				{
					case 'completed':
						$this->arr_msg[] = array(
							'msg' => sprintf($this->messages['completed_task']['msg'], $task_name, $arr_curr['due_on'], $task_id),
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
								'msg' => sprintf($this->messages['assigned_task']['msg'], $elem, $task_name, $arr_curr['due_on'], $task_id),
								'color' => $this->messages['assigned_task']['color']
							);
							break;
						}
					default:
						$this->arr_msg[] = array(
							'msg' => sprintf($this->messages['changed_task']['msg'], $task_name, $arr_curr['due_on'], $task_id, $key, $arr_old[0][$key] ,$elem),
							'color' => $this->messages['changed_task']['color']
						);
						break;
				}

				$this->obj_db->exec("UPDATE `task` SET ". $key ." = '". $elem ."' WHERE id = ". $task_id);
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
				$arr_data[(string)$workspace->id] = array(
					'name' => htmlentities($workspace->name, ENT_COMPAT, "UTF-8"),
					'projects' => array()
				);

				// Get all projects in the current workspace (all non-archived projects)
				$projects = $this->obj_asana->getProjectsInWorkspace($workspace->id, $archived = false);

				// As Asana API documentation says, when response is successful, we receive a 200 in response so...
				if($this->obj_asana->responseCode == "200" && !is_null($projects)){
					$projectsJson = json_decode($projects);
					foreach ($projectsJson->data as $project){
						if (is_null($this->allowed_projects) || in_array($project->id, $this->allowed_projects)) {
							$arr_data[(string)$workspace->id]['projects'][(string)$project->id] = array(
								'name' => htmlentities($project->name, ENT_COMPAT, "UTF-8"),
								'tasks' => array()
							);

							// Get all tasks in the current project
							$tasks = $this->obj_asana->getProjectTasks($project->id);
							$tasksJson = json_decode($tasks);
							if($this->obj_asana->responseCode == "200" && !is_null($tasks)){
								foreach ($tasksJson->data as $task){
									$arr_data[(string)$workspace->id]['projects'][(string)$project->id]['tasks'][(string)$task->id] = array(
										'name' => htmlentities($task->name, ENT_COMPAT, "UTF-8"),
										'data' => array()
									);

									$this->getTask($project->name, $task->id, $arr_data[(string)$workspace->id]['projects'][(string)$project->id]['tasks'][(string)$task->id]['data']);
								}
							} else {
								echo "Error while trying to connect to Asana, response code: {$this->obj_asana->responseCode}";
							}
						} else {
							echo "Ignoring project : {$project->id}";
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
			$name = htmlentities($taskdata->name, ENT_COMPAT, "UTF-8");

			$assignee = 'not set';
			if (!empty($taskdata->assignee))
			{
				if (!empty($taskdata->assignee->name))
				{
					$assignee = htmlentities($taskdata->assignee->name, ENT_COMPAT, "UTF-8");
				}
			}

			$projects = 'not set';
			if (count($taskdata->projects) > 0)
			{
				$projects = '';
				foreach ($taskdata->projects as $project)
				{
					$projects .= $project->name . ', ';
				}
				$projects = rtrim($projects, ', ');
			}

			$due_on = $taskdata->due_on;
			if (empty($due_on))
			{
				$due_on = 'not set';
			}

			$arr_data = array(
				'name' => $name,
				// 'projects' => $projects,
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

		/*
		 * sleep between task calls
		 * @todo implement request counter, to only sleep if limit could be reached
		 */
		sleep($this->asana_sleep_interval);
	}

}
