<?php

// Database connection parameters
$servername = "db";
$username = "root";
$password = "root";
$dbname = "agent_db"; // Replace with your actual database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/**
 * Inserts a new task into the database
 * @param string $description The description of the task
 * @return int The ID of the inserted task
 */
function insert_task_in_db($description) {
    global $conn;

    $stmt = $conn->prepare("INSERT INTO tasks (description) VALUES (?)");
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $description);

    if ($stmt->execute() === false) {
        die("Execute failed: " . $stmt->error);
    }

    $id = $stmt->insert_id;
    $stmt->close();

    return $id;
}

/**
 * Retrieves tasks from the database
 * @param int|null $task_id Optional task ID to retrieve a specific task
 * @return array|mysqli_result The task(s) data
 */
function get_tasks_from_db($task_id = null) {
    global $conn;
    if ($task_id) {
        $stmt = $conn->prepare("SELECT id, description, analysis FROM tasks WHERE id = ?");
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $task_id);
        if ($stmt->execute() === false) {
            die("Execute failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            die("No subtask found with the given ID.");
        }
    
        $result = $result->fetch_assoc();
        $stmt->close();
    } else {
        $sql = "SELECT id, description, analysis FROM tasks";
        $result = $conn->query($sql);
    }
    return $result;
}

/**
 * Retrieves subtasks from the database
 * @param int|null $task_id Optional task ID to retrieve subtasks for a specific task
 * @return array The subtask(s) data
 */
function get_subtasks_from_db($task_id = null) {
    global $conn;
    if ($task_id) {
        $stmt = $conn->prepare("SELECT id, description, agent_role, priority, task_id, crawled, findings, sources FROM subtasks WHERE task_id = ?");
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $task_id);
        if ($stmt->execute() === false) {
            die("Execute failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            die("No subtasks found with the given task ID.");
        }

        $subtasks = [];
        while ($row = $result->fetch_assoc()) {
            $row['sources'] = json_decode($row['sources'], true);
            $subtasks[] = $row;
        }
        $stmt->close();
        return $subtasks;
    } else {
        $sql = "SELECT id, description, agent_role, priority, task_id, crawled, findings, sources FROM subtasks";
        $subtasks = $conn->query($sql);
        return $subtasks;
    }
}

/**
 * Updates subtasks in the database for a given task
 * @param int $task_id The ID of the parent task
 * @param string $subtasks JSON string containing subtask data
 */
function update_subtasks_in_db($task_id, $subtasks) {
    global $conn;

    // Decode the JSON string into an array
    $subtasks_array = json_decode($subtasks, true)["Subtasks"];

    // Check if JSON decoding was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("JSON decode error: " . json_last_error_msg());
    }

    $stmt = $conn->prepare("INSERT INTO subtasks (description, agent_role, priority, task_id) VALUES (?, ?, ?, ?)");
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    foreach ($subtasks_array as $subtask) {
        $description = $subtask['Description'];
        $agent_role = $subtask['Agent Role'];
        $priority = $subtask['Priority'];

        $stmt->bind_param("sssi", $description, $agent_role, $priority, $task_id);

        if ($stmt->execute() === false) {
            die("Execute failed: " . $stmt->error);
        }
    }

    $stmt->close();
}

/**
 * Gets a response from ChatGPT API
 * @param string $text The prompt text to send to ChatGPT
 * @return string The API response content
 */
function get_chatgpt_response($text) {
    $api_key = "";

    $url = 'https://api.openai.com/v1/chat/completions';
    
    $data = [
        'model' => 'gpt-4o',
        //'model' => 'o1-preview',
        'response_format' => [
            'type' => 'json_object'
        ],
        'messages' => [
            [
                'role' => 'user',
                'content' => $text
            ]
        ]
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key
            ],
            'content' => json_encode($data)
        ]
    ];

    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        $error = error_get_last();
        return "Error getting ChatGPT response: " . $error['message'];
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return "Error decoding JSON response: " . json_last_error_msg();
    }

    return $result['choices'][0]['message']['content'] ?? "No content in response";
}

/**
 * Coordinates the breakdown of a task into subtasks
 * @param int $task_id The ID of the task to coordinate
 * @return string JSON response containing subtasks
 */
function coordinator_agent($task_id) {
    global $conn;
    $max_subtasks = 20;

    // Retrieve existing subtasks for the task
    $stmt = $conn->prepare("SELECT description, agent_role, priority FROM subtasks WHERE task_id = ?");
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $task_id);
    if ($stmt->execute() === false) {
        die("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $existing_subtasks = [];
    while ($row = $result->fetch_assoc()) {
        $existing_subtasks[] = [
            'Description' => $row['description'],
            'Agent Role' => $row['agent_role'],
            'Priority' => $row['priority']
        ];
    }
    $stmt->close();

    // Check if the number of existing subtasks equals or exceeds the maximum allowed
    if (count($existing_subtasks) >= $max_subtasks) {
        return json_encode(['Subtasks' => []]);
    }

    // Retrieve the task description from the database using the task_id
    $stmt = $conn->prepare("SELECT description FROM tasks WHERE id = ?");
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $task_id);
    if ($stmt->execute() === false) {
        die("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        die("No task found with the given ID.");
    }

    $task = $result->fetch_assoc()['description'];
    $stmt->close();

    // Prepare the prompt with existing subtasks
    $existing_subtasks_json = json_encode(['Subtasks' => $existing_subtasks], JSON_PRETTY_PRINT);

    $response = get_chatgpt_response("

### Coordinator Agent Prompt

**Objective:**  
You are a Coordinator Agent responsible for breaking down a complex primary task into smaller, fact-driven subtasks. Your role is to identify what factual information is needed, what areas need analysis, and to structure these into actionable subtasks. Your subtasks must be aligned with the overall objective: to understand and inform the likelihood of a binary event occurring without directly engaging in pure speculation.

**Primary Task:**  
{$task}

---

**Existing Subtasks:**  
{$existing_subtasks_json}

---

**Instructions:**

1. **Understand the Primary Task:**  
   - Analyze the primary task and identify key areas where factual data can be gathered.  
   - Consider relevant historical data, documented patterns, and verifiable information sources.  
   - The ultimate goal is to inform a probability estimate, not to conclusively predict the future without factual basis.

2. **Decompose the Task into Fact-Based Subtasks:**  
   - Break the main task into smaller subtasks that help build an evidence-based picture.  
   - Subtasks should focus on gathering historical data, analyzing trends, confirming relevant conditions or triggers, and identifying any known influential factors that can affect the outcome.
   - Always quote the full task: **{$task}** in the subtask title to maintain contextual linkage.
   - Each subtask should be independent, non-overlapping, and essential for understanding the underlying factual landscape.
   - Avoid speculation: Do not include subtasks that rely on unverified predictions, betting odds, or subjective forecasts.

3. **Clarity and Structure:**  
   For each subtask, provide:
   - **Subtask ID**: A unique identifier (e.g., Task-01).
   - **Description**: A concise explanation of the data or analysis needed.
   - **Agent Role**: The type of specialized agent best suited to execute that subtask (e.g., 'Data Gathering Agent', 'Historical Analysis Agent', 'Market Trend Analysis Agent').
   - **Priority**: High/Medium/Low based on the direct relevance and impact on informing the final probability.

4. **Output Format:**  
   Provide the subtasks in JSON format:

   ```json
   {
       'Subtasks': [
           {
                'Subtask ID': 'Task-01',
               'Description': '...',
               'Agent Role': '...',
               'Priority': '...'
           },
           ...
       ]
   }
   ```

5. **If No Additional Subtasks are Necessary:**  
   If the existing subtasks are already optimal, return an empty JSON list.

6. **Limits and Truthfulness:**  
   - If the number of subtasks exceeds {$max_subtasks}, return an empty list.
   - Ensure all subtasks are grounded in verifiable facts and insights.
   - Avoid speculation or tasks that produce unsupported predictions.
    
    ");

    update_subtasks_in_db($task_id, $response);

    return $response;
}

//  Important: always quote the full task: {$task} when coming up with subtasks so they don't get confused.

/**
 * Retrieves a specific subtask by ID
 * @param int $subtask_id The ID of the subtask to retrieve
 * @return array The subtask data
 */
function get_subtask_by_id($subtask_id) {
    global $conn;

    // Fetch the subtask details from the database
    $stmt = $conn->prepare("SELECT description, agent_role, crawled, task_id FROM subtasks WHERE id = ?");
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $subtask_id);
    if ($stmt->execute() === false) {
        die("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        die("No subtask found with the given ID.");
    }

    $subtask = $result->fetch_assoc();
    $stmt->close();

    return $subtask;
}

/**
 * Retrieves a specific task by ID
 * @param int $task_id The ID of the task to retrieve
 * @return array The task data
 */
function get_task_by_id($task_id) {
    global $conn;

    // Fetch the task details from the database
    $stmt = $conn->prepare("SELECT description FROM tasks WHERE id = ?");
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $task_id);
    if ($stmt->execute() === false) {
        die("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        die("No task found with the given ID.");
    }

    $task = $result->fetch_assoc();
    $stmt->close();

    return $task;
}

/**
 * Executes a specialized agent for a specific subtask
 * @param int $subtask_id The ID of the subtask to process
 * @return string JSON response containing findings and sources
 */
function specialized_agent($subtask_id) {
    $subtask = get_subtask_by_id($subtask_id);

    if ($subtask['crawled']) {
        return "Subtask already crawled.";
    }

    $description = $subtask['description'];
    $agent_role = $subtask['agent_role'];
    $parent_task = get_task_by_id($subtask['task_id']);

    $agent_prompt = "

### Speculation Agent Prompt

**Objective:**  
You are a “Speculation Agent” in name only, but your actual role is to gather and analyze factual, verifiable information relevant to the assigned subtask. You do not produce speculative predictions; instead, you focus on uncovering and summarizing factual data from credible sources. Your job is to provide insight that can inform an eventual probability assessment, strictly through evidence-based reasoning.

**Primary Event:**  
{$parent_task['description']}

**Your Task:**  
{$description}

Be insightful and strictly grounded in truth. Identify and summarize factual information only.

---

**Instructions:**

1. **Understand Your Role:**  
   - Your role is to gather factual, verifiable data relevant to the assigned subtask description: {$description}.
   - Focus on what can be confirmed: historical data, official reports, recognized expert analyses based on historical patterns, and other reputable, factual sources.

2. **Conduct Information Gathering and Analysis:**  
   - Use web searches (via provided functions) to find relevant, credible, and recent information.  
   - Cite authoritative sources where possible.
   - Avoid speculation, opinion polls, or betting odds.  
   - Present findings as verifiable statements, not forecasts.

3. **Format of Output:**  
   - Provide a clear, concise summary of factual findings related to the subtask.
   - Emphasize relevance, credibility, and clarity.

---
    
    ";

    $response = get_perplexity_response($agent_prompt);

    $unprocessed_result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return "Error decoding JSON response: " . json_last_error_msg();
    }

    $result = $unprocessed_result['choices'][0]['message']['content'] ?? null;

    if (isset($result)) {
        $findings = $result;
        $sources = json_encode($unprocessed_result['citations']);

        global $conn;

        // Update the subtask in the database
        $stmt = $conn->prepare("UPDATE subtasks SET crawled = 1, findings = ?, sources = ? WHERE id = ?");
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("ssi", $findings, $sources, $subtask_id);
        if ($stmt->execute() === false) {
            die("Execute failed: " . $stmt->error);
        }

        $stmt->close();

        return json_encode([
            'crawled' => true,
            'findings' => $findings,
            'sources' => json_decode($sources)
        ]);
    }

    return $result;
}

/**
 * Gets a response from Perplexity AI API
 * @param string $text The prompt text to send to Perplexity
 * @return string The API response
 */
function get_perplexity_response($text) {
    $api_key = "";
    $url = 'https://api.perplexity.ai/chat/completions';

    $messages = [
        [
            'role' => 'user',
            'content' => $text
        ]
    ];

    $data = [
        //'model' => 'llama-3.1-sonar-small-128k-online',
        'model' => 'llama-3.1-sonar-large-128k-online',
        'messages' => $messages
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'content-type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return "Error getting Perplexity response: " . $error;
    }

    curl_close($ch);

    return $response;
}

/**
 * Runs analysis on a task and its subtasks
 * @param array $task The task data
 * @param array $subtasks The subtasks data
 * @return string JSON response containing analysis
 */
function run_analysis_agent($task, $subtasks) {

    $central_question = $task['description'];
 
    $input = "
    
### Analysis Agent Prompt

**Objective:**  
You are the Analysis Agent. Your job is to evaluate all the factual findings collected from various subtasks and synthesize them into a structured probability assessment of the central question. Although you must provide a probability score, base it solely on weighted interpretations of factual data—historical patterns, current conditions, verified information—instead of raw speculation.

**Central Question:**  
{$central_question}

**Your Inputs:**  
A compiled set of factual findings derived from the subtasks and their respective agents.

---

**Instructions:**

1. **Evaluate Factual Findings:**  
   - Review all sourced data and findings, verifying that they are grounded in fact.  
   - Assess relevance (how closely the data relates to the central question), reliability (quality of the source and consistency of the information), and impact (the degree to which the factor influences the event’s likelihood).

2. **Weighting and Probability Assessment:**  
   - Assign weights to each factor based on its importance and credibility.  
   - Use a reasoned, evidence-based method to estimate how each factor increases or decreases the likelihood of the event.  
   - Combine these weighted factors to produce a final probability score. This score is a reasoned estimate, not a guaranteed prediction.

3. **Cross-Validation and Uncertainty Acknowledgment:**  
   - Check for contradictions or uncertainties in the findings.  
   - If data is inconclusive, explicitly state the uncertainty and consider it in your weighting.  
   - Your final assessment should reflect both confidence and limitations in the data.

4. **Output Format in JSON:**
   
   ```json
   {
       'Summary': 'A concise overview of the key factual findings and how they inform the final probability estimate.',
       'Thematic Breakdown': [
           {
               'Theme': 'Name of Theme/Factor',
               'Key Findings': 'Summarized factual data points.',
               'Probability Score': 'An integer 0–100 indicating likelihood informed by data.',
               'Rationale': 'Explanation of why that score was assigned based on factual data.'
           },
           ...
       ],
       'Key Insights and Implications': 'Critical insights, uncertainties, and recommendations for further fact-based analysis.',
       'Final Probability Score': {
           'Score': 'A single aggregated probability 0–100',
           'Explanation': 'How the score was derived from factual evidence, referencing weighting and logic used.'
       }
   }
   ```

5. **Important Notes:**
   - Do not rely on speculation at any point. If you must present a probability, it should be a transparent, data-informed approximation.
   - Emphasize uncertainty where appropriate. Clarify that even a data-driven probability is not a guaranteed outcome, but a best assessment based on known facts.

---

    ";
 
    foreach ($subtasks as $subtask) {
 
       $input .= "
 
       Topic: {$subtask['description']}
       Evaluator Role: {$subtask['agent_role']}
       Findings: \n\n {$subtask['findings']}
 
       ----
 
       ";
 
    }
 
    $input .= "
 
 
    -----------------------------
 
    Now give me the final output following the instructions I gave. Repeat the instructions and then start. Give me the JSON at the very end with the score being the last parameter.

    You should follow the JSON format below:

    {
        'Summary': {
            'Overview': '...',
            'Overall Probability Score': '...'
        },
        'Key Insights and Implications': '...',
        'Thematic Breakdown': {
            '...'
        }
    }

    Important: Overall Probability Score should only be a number between 0 and 100.
 
    JSON Output:
 
    ";
 
    $response = get_chatgpt_response($input);
 
    insert_analysis_into_db($task['id'], $response);
 
    return $response;
 
 }
 
/**
 * Inserts analysis results into the database
 * @param int $task_id The ID of the task
 * @param string $response The analysis response to insert
 */
function insert_analysis_into_db($task_id, $response) {

    global $conn;

    $stmt = $conn->prepare("UPDATE tasks SET analysis = ? WHERE id = ?");
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("si", $response, $task_id);

    if ($stmt->execute() === false) {
        die("Execute failed: " . $stmt->error);
    }

    $stmt->close();
 
 }

?>