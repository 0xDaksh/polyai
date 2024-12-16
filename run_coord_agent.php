<?php

include 'functions.php';

// Get tasks from db
$tasks = get_tasks_from_db();

// Loop through each task and run the coordinator agent
while ($task = $tasks->fetch_assoc()) {
    $task_id = $task['id'];

    // Run the coordinator agent to improve the subtasks
    $coordinator_agent_output = coordinator_agent($task_id);

    echo "<pre>";
    echo "Task ID: " . $task_id . "\n";
    echo "Coordinator Agent Output:\n" . $coordinator_agent_output;
    echo "</pre>";
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Coordinator Agent</title>
    <script>
        let refreshCount = 1;
        const maxRefreshes = 5;

        // Store refresh count in sessionStorage to persist between reloads
        if (sessionStorage.getItem('refreshCount')) {
            refreshCount = parseInt(sessionStorage.getItem('refreshCount'));
            sessionStorage.setItem('refreshCount', refreshCount + 1);
        } else {
            sessionStorage.setItem('refreshCount', '1');
        }

        window.onload = function() {
            if (refreshCount < maxRefreshes) {
                refreshCount++;
                setTimeout(function() {
                    location.reload();
                }, 100); // Refresh every 100 milliseconds
            }
            else {
                sessionStorage.setItem('refreshCount', '1');
            }
        };
    </script>
</head>
<body>
</body>
</html>

