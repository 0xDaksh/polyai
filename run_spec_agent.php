<?php

include 'functions.php';

// Get subtasks from db
$subtasks = get_subtasks_from_db();

// Loop through each subtask and run the specialized agent
while ($subtask = $subtasks->fetch_assoc()) {
   $subtask_id = $subtask['id'];

   // Run the specialized agent to improve the subtasks
   $specialized_agent_output = specialized_agent($subtask_id);

   echo "<pre>";
   echo "Subtask ID: " . $subtask_id . "\n";
   echo "Specialized Agent Output:\n" . $specialized_agent_output;
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
