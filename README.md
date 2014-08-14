TaskScheduler
=============

Schedule tasks using minimal PHP code.

Useful for scheduling tasks to run at specific times or intervals without having to write much custom logic or do any manual persistence of the last time a specific task has been run. It's a good idea to have this run every minute.


## Example Usage ##

```php
	// Will setup a task to run only on first Mondays and Tuesdays of every month, every 15 minutes during those days.
	$task = new TaskScheduler("my-task");
	$task->setInterval($task::FREQ_MIN, 15);
	$task->setWeekdays(array(
		$task::DAY_MON,
		$task::DAY_TUE,
	));

	// Uses "and" logic in combination with weekdays.
	$task->setDays(array(1, 2, 3, 4, 5, 6, 7));

	// To use "or" logic, uncomment this line so it will fire on the first 7 days of the week OR on every Mon/Tue of the month.
	//$task->setOrLogic(true);

	// Useful if the cron only runs once every 5 minutes, or, if the ->run() method executes after a long script.
	$task->setTimeThreshold(3); // Allows up to 3 minutes after the scheduled time and will still attempt a run.

	// Check to see if task should run...
	if ($task->run()) {
		// Do something here.
	} else {
		// See why it didn't work.
		dump($task->getFailReason());
	}

```