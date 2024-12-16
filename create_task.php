<?php

include 'functions.php';

$task = "Who will win the NFL game on Friday, Dec 13th night, Rams vs 49ers?";
//$task2 = "MSFT shareholders vote for Bitcoin investment at their December 10, 2024 shareholder meeting?";
// NBA game predictions for Saturday December 7th
//$task3 = "Who will win the NBA game on Saturday December 7th night, Thunder vs Pelicans?";
//$task4 = "Who will win the NBA game on Saturday December 7th night, Pistons vs Knicks?"; 
//$task5 = "Who will win the NBA game on Saturday December 7th night, Mavericks vs Raptors?";
//$task6 = "Who will win the NBA game on Saturday December 7th night, Grizzlies vs Celtics?";
//$task7 = "Who will win the NBA game on Saturday December 7th night, Suns vs Heat?";


$task_id = insert_task_in_db($task);
//$task_id2 = insert_task_in_db($task2);
//$task_id3 = insert_task_in_db($task3);
//$task_id4 = insert_task_in_db($task4);
//$task_id5 = insert_task_in_db($task5);
//$task_id6 = insert_task_in_db($task6);
//$task_id7 = insert_task_in_db($task7);

echo "Task created: '" . $task . "' with Task ID: " . $task_id . "\n";

?>