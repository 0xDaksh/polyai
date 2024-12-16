<?php

include 'functions.php';

$tasks = get_tasks_from_db();

if ($tasks->num_rows > 0) {
    while ($task = $tasks->fetch_assoc()) {
        if (!isset($task['analysis'])) {
            $subtasks = get_subtasks_from_db($task['id']);
            $output = run_analysis_agent($task, $subtasks);
            echo "Output for Task ID " . $task['id'] . ":<br/>" . $output . "<br/><br/>";
        } else {
            echo "Analysis already exists for Task ID " . $task['id'] . ".<br/>";
        }
    }
} else {
    echo "No tasks found.\n";
}

?>