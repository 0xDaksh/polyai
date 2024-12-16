<?php

include 'functions.php';

$tasks = get_tasks_from_db();
$all_task_data = [];

if ($tasks->num_rows > 0) {
    while ($task = $tasks->fetch_assoc()) {
        $task_id = $task['id'];
        $subtasks = get_subtasks_from_db($task_id);
        
        $all_task_data[] = [
            'task_description' => $task['description'],
            'task_id' => $task_id,
            'subtasks' => $subtasks,
            'analysis' => json_decode($task['analysis'], true)
        ];
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Agent Analysis - All Tasks</title>
    <style>
        /* Base styles for dark mode */
        body {
            background-color: #1e1e1e;
            color: #d4d4d4;
            font-family: monospace;
            margin: 0;
            padding: 20px;
            font-size: 13px;
            height: auto;
            line-height: 19.5px;
            overflow-wrap: break-word;
            text-wrap-mode: wrap;
        }
        summary {
            cursor: pointer;
            list-style: none;
            position: relative;
        }
        summary::-webkit-details-marker {
            display: none;
        }
        p {
            margin-left: 20px;
        }
        details {
            margin: 10px;
        }
        details[open] p {
            margin-left: 40px;
        }
        .value {
            color: #ce9178;
        }
        .score {
            color: white;
        }
        .explanation {
            color: hsl(114, 100%, 35%);
        }
        .task-container {
            border: 1px solid #333;
            margin: 20px 0;
            padding: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <?php if (empty($all_task_data)): ?>
        <p>No tasks found.</p>
    <?php else: ?>
        <?php foreach ($all_task_data as $task_data): ?>
            <div class="task-container">
                <h2>Task ID: <?php echo $task_data['task_id']; ?></h2>
                Task: <span class="explanation"><?php echo $task_data['task_description']; ?></span> <br/>
                Total AI Agents: <span class="explanation"><?php echo count($task_data['subtasks']); ?></span> <br/><br/>
                
                Factors Evaluated by AI Agents: <br/>
                <?php foreach ($task_data['subtasks'] as $index => $subtask): ?>
                    <span class="explanation"><details>
                        <summary><span class="score">- Agent <?php echo $index + 1; ?>:</span> <?php echo $subtask['description']; ?></summary>
                        <p><span class="value">
                            <?php 
                                echo nl2br(htmlspecialchars($subtask['findings'])); 
                                if (!empty($subtask['sources'])) {
                                    echo "<br/><br/>### Sources<br/>";
                                    foreach ($subtask['sources'] as $index => $source) {
                                        echo "[" . ($index + 1) . "] " . $source . "  <br/>";
                                    }
                                }
                            ?>
                        </span></p>
                    </details></span>
                <?php endforeach; ?>

                <br/>AI Analysis Overview: <br/>
                <span class="explanation"><p><?php echo $task_data['analysis']['Summary']['Overview']; ?></p></span>

                <br/>Key Insights and Implications: <br/>
                <span class="explanation"><p><?php 
                    $insights = $task_data['analysis']['Key Insights and Implications'];
                    echo is_array($insights) ? print_r($insights, true) : $insights;
                ?></p></span>

                <br/>Overall Probability Score: <br/>
                <span class="explanation"><p><?php echo $task_data['analysis']['Summary']['Overall Probability Score']; ?></p></span>

                <br/>Thematic Breakdown: <br/>

                <?php foreach ($task_data['analysis']['Thematic Breakdown'] as $category => $details): ?>
                    <?php if (is_numeric($category)) { ?>
                        <span class="explanation"><details>
                            <summary>- <?php echo $details["Theme"]; ?></summary>
                            <p><span class="value">- Key Findings: <?php echo $details["Key Findings"]; ?></span></p>
                            <p><span class="value">- Probability Score: <?php echo $details["Probability Score"]; ?></span></p>
                            <p><span class="value">- Rationale: <?php echo $details["Rationale"]; ?></span></p>
                        </details></span>
                    <?php } else { ?>
                        <span class="explanation"><details>
                            <summary>- <?php echo $category; ?></summary>
                            <?php foreach ($details as $subcategory => $detail): ?>
                                <p><span class="value">- <?php echo $subcategory; ?>: <?php echo $detail; ?></span></p>
                            <?php endforeach; ?>
                        </details></span>
                    <?php } ?>
                <?php endforeach; ?>

            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

