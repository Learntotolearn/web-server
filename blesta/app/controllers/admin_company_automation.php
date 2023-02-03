<?php

/**
 * Admin Company Automation Settings
 *
 * @package blesta
 * @subpackage blesta.app.controllers
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminCompanyAutomation extends AppController
{
    /**
     * Pre-action
     */
    public function preAction()
    {
        parent::preAction();

        // Require login
        $this->requireLogin();

        $this->uses(['Companies', 'CronTasks', 'Logs', 'Navigation']);
        $this->components(['SettingsCollection']);
        $this->helpers(['DataStructure']);

        $this->ArrayHelper = $this->DataStructure->create('Array');

        Language::loadLang('admin_company_automation');

        // Set the left nav for all settings pages to settings_leftnav
        $this->set(
            'left_nav',
            $this->partial('settings_leftnav', ['nav' => $this->Navigation->getCompany($this->base_uri)])
        );
    }

    /**
     * Automation settings
     */
    public function index()
    {
        // Set the type of task
        $task_type = 'system';
        $task_types = $this->CronTasks->getTaskTypes();
        if (isset($this->get[0]) && array_key_exists($this->get[0], $task_types)) {
            $task_type = $this->get[0];
        }

        // Get all the cron tasks
        $task_runs = $this->CronTasks->getAllTaskRun(false, $task_type);
        $vars = $this->groupTasks($this->appendTaskInfo($task_runs));
        foreach ($vars as $group) {
            foreach ($group['tasks'] as &$task) {
                // Cast the time to the proper local time format
                if (!empty($task->time)) {
                    $task->time = $this->Date->cast($task->time, 'H:i:s');
                }
            }
        }

        if (!empty($this->post)) {
            // Ensure the 'enabled' setting is available even when all checkboxes are unchecked
            $this->post = array_merge(['enabled' => []], $this->post);

            // Start a transaction
            $this->CronTasks->begin();

            $errors = [];

            // Update the provided fields on each cron task
            foreach ($task_runs as &$task) {
                foreach ($this->post as $key => $task_run_ids) {
                    // Skip updating cron task settings that are not in this list
                    if (!in_array($key, ['enabled', 'time', 'interval'])) {
                        continue;
                    }

                    // Set the selected value for this field
                    if (array_key_exists($task->task_run_id, (array) $task_run_ids)) {
                        $task->{$key} = $task_run_ids[$task->task_run_id];
                    } elseif ($key == 'enabled') {
                        // The 'enabled' checkbox should be set to disabled if it's not given
                        $task->enabled = '0';
                    }
                }

                $this->CronTasks->editTaskRun($task->task_run_id, (array) $task);

                // Keep the most recent errors by breaking out
                if ($this->CronTasks->errors()) {
                    break;
                }
            }

            // Use only the most recent cron task's errors.
            // Note: there should never be errors
            if (($errors = $this->CronTasks->errors())) {
                // Error, rollback and reset vars
                $this->CronTasks->rollBack();

                $this->setMessage('error', $errors);
                $vars = $this->groupTasks($this->appendTaskInfo($task_runs));
            } else {
                // Success, commit changes
                $this->CronTasks->commit();

                $this->flashMessage('message', Language::_('AdminCompanyAutomation.!success.automation_updated', true));
                $this->redirect($this->base_uri . 'settings/company/automation/index/' . $task_type . '/');
            }
        }

        $this->set('company_timezone', str_replace('_', ' ', Configure::get('Blesta.company_timezone')));
        $this->set('time_values', $this->getTimes(5));
        $this->set('interval_values', $this->getIntervals());
        $this->set('task_types', $task_types);
        $this->set('tab', $task_type);
        $this->set('vars', $vars);
    }

    /**
     * Clears the POSTed cron task
     */
    public function clearTask()
    {
        // Clear the cron task
        if (!empty($this->post) && !empty($this->post['run_id'])) {
            $this->Logs->clearCronTask($this->post['run_id']);
        }

        $this->flashMessage('message', Language::_('AdminCompanyAutomation.!success.task_cleared', true));
        $this->redirect(
            $this->base_uri . 'settings/company/automation/'
                . (isset($this->get[0]) ? 'index/' . $this->get[0] . '/' : '')
        );
    }

    /**
     * Retrieve a list of available cron task intervals (in minutes)
     *
     * @return array A list of intervals
     */
    private function getIntervals()
    {
        $intervals = [5 => 5, 10 => 10, 15 => 15, 30 => 30, 45 => 45];

        foreach ($intervals as &$interval) {
            $interval = $interval . ' ' . Language::_('AdminCompanyAutomation.getintervals.text_minutes', true);
        }

        // Set each hour up to 24 hours
        for ($i = 1; $i <= 24; $i++) {
            $intervals[$i * 60] = $i . ' '
                . Language::_('AdminCompanyAutomation.getintervals.text_hour' . (($i == 1) ? '' : 's'), true);
        }

        return $intervals;
    }

    /**
     * Updates the given task objects to append properties representing additional information on the task
     *
     * @param array $task_runs array An array of stdClass objects representing each cron task run
     * @return array An array of stdClass objects representing each cron task run, but now including:
     *
     *  - last_ran
     *  - is_running
     *  - is_stalled
     */
    private function appendTaskInfo(array $task_runs)
    {
        // Fetch currently running tasks
        $running_tasks = [];
        foreach ($this->Logs->getRunningCronTasks() as $task) {
            $running_tasks[$task->run_id] = $task;
        }

        $base_cron_tasks = [];
        foreach ($task_runs as &$task) {
            // Fetch the base cron task in this tasks' group to check whether it has completed
            $group = null;
            if (array_key_exists($task->task_run_id, $running_tasks)) {
                // Create a hash of base cron tasks by group
                if (!isset($base_cron_tasks[$running_tasks[$task->task_run_id]->group])) {
                    $base_cron_tasks[$running_tasks[$task->task_run_id]->group] = $this->Logs->getSystemCronLastRun(
                        $running_tasks[$task->task_run_id]->group
                    );
                }

                // Set the group to filter this latest cron task on
                if ($base_cron_tasks[$running_tasks[$task->task_run_id]->group]) {
                    $base_task = $base_cron_tasks[$running_tasks[$task->task_run_id]->group];
                    $group = ($base_task->end_date !== null ? $base_task->group : null);
                    unset($base_task);
                }
            }

            // Fetch the latest cron task
            $task_log = $this->Logs->getLatestCron($task->task_run_id, $group);

            // Set the date this cron task last ran
            $task->last_ran = (!empty($task_log) ? $this->Date->cast($task_log->start_date, 'date_time') : null);
            $task->is_running = (!empty($task_log) && $task_log->end_date === null);
            $task->is_stalled = (!empty($task_log) && $task_log->end_date === null && $group !== null);
        }

        return $task_runs;
    }

    /**
     * Creates a set of task groups from runnable tasks based on the module or plugin
     *
     * @param array $task_runs array An array of stdClass objects representing each cron task run
     * @return array An array of plugin/module groups containing the name of the group and the task IDs in that group:
     *
     *  - name The name of the group
     *  - task_ids An array of each task ID in the group
     */
    private function groupTasks(array $task_runs)
    {
        // Group each task by system/plugin/module
        $groups = [];
        foreach ($task_runs as $task) {
            if (!empty($task->plugin)) {
                if (!isset($groups[$task->plugin->dir])) {
                    $groups[$task->plugin->dir] = ['name' => $task->plugin->name, 'tasks' => []];
                }

                $groups[$task->plugin->dir]['tasks'][$task->task_run_id] = $task;
            } elseif (!empty($task->module)) {
                if (!isset($groups[$task->module->class])) {
                    $groups[$task->module->class] = ['name' => $task->module->name, 'tasks' => []];
                }

                $groups[$task->module->class]['tasks'][$task->task_run_id] = $task;
            } else {
                if (!isset($groups['system'])) {
                    $groups['system'] = ['name' => '', 'tasks' => []];
                }

                $groups['system']['tasks'][$task->task_run_id] = $task;
            }
        }

        // Sort the groups by name
        $names = [];
        foreach ($groups as $index => &$group) {
            $names[$index] = $group['name'];

            // Sort the tasks within each group by name
            $task_names = [];
            foreach ($group['tasks'] as $key => $task) {
                $task_names[$key] = $task->real_name;
            }
            array_multisort($task_names, SORT_NATURAL, $group['tasks']);
        }
        array_multisort($names, SORT_NATURAL, $groups);

        return $groups;
    }
}
