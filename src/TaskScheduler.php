<?
/**
 * Class TaskScheduler
 *
 * Useful for scheduling tasks to run at specific times or intervals without having to write much custom logic or do any
 * manual persistence of the last time a specific task has been run. It's a good idea to have this run every minute.
 *
 * Example usage:

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

 */

class TaskScheduler {

	// Weekday options.
	const DAY_ALL = 0;
	const DAY_SUN = 1;
	const DAY_MON = 2;
	const DAY_TUE = 3;
	const DAY_WED = 4;
	const DAY_THU = 5;
	const DAY_FRI = 6;
	const DAY_SAT = 7;

	// Frequency options.
	const FREQ_MIN = 0;
	const FREQ_HOUR = 1;
	const FREQ_DAY = 2;
	const FREQ_MONTH = 3;

	// Override this for debugging/testing to ensure the task will run as expected.
	public $time = 0;

	// Configured via methods below.
	private $taskName = "";
	private $weekDays = array(); // Default setting to run every day (if empty). Intersected with days of the month.
	private $days = array(); // Default setting to run every day (if empty). Intersected with weekdays.
	private $intervals = array(); // Pluralized, but only one interval is currently allowed.
	private $times = array(); // Specific times of the day to run.
	private $timeThreshold = 1; // Flexibility in minutes (see ->setTimeThreshold() method for details).
	private $useOrLogic = false; // Weekday and month day logical combinations.
	private $failReason = ""; // Indicates reason why task failed to run. Use ->getFailReason() for contents.


	/**
	 * After instantiating, you should call either ->setInterval() or ->setTime() first.
	 *
	 * Then, check ->run() to see if you should run your task.
	 *
	 * @param	string	$taskName
	 * @throws	Exception
	 */
	public function __construct($taskName) {
		if (empty($taskName)) throw new \Exception("Task name cannot be empty.");
		if (!class_exists("DynSetting")) throw new \Exception("The class 'DynSetting' is required.");
		$this->taskName = $taskName;

		// Set current time. Allows easier debugging/testing.
		$this->time = time();
	}


	/**
	 * Indicate exactly which days this task can run. If not set, defaults to every day of the week.
	 *
	 * 1 = Sunday ... 7 = Saturday (etc)
	 *
	 * @param	int	$weekDay
	 * @throws	Exception
	 */
	public function setWeekday($weekDay) {
		$weekDay = (int) $weekDay;
		if ($weekDay < 0 || $weekDay > 7) throw new \Exception("Invalid weekday option: $weekDay");

		// See if this is set to run every day. If so, just clear the "days" array.
		if ($weekDay == self::DAY_ALL) $this->weekDays = array();

		// Drop into allowed weekdays to run.
		$this->weekDays[$weekDay] = true;
	}


	/**
	 * Specify multiple weekdays at a time during which this task can run.
	 *
	 * @param	array	$weekDays
	 */
	public function setWeekdays(array $weekDays) {
		foreach($weekDays as $weekDay) $this->setWeekday($weekDay);
	}


	/**
	 * Specify a day of the month to run.
	 *
	 * @param	int	$day
	 * @throws	Exception
	 */
	public function setDay($day) {
		$day = (int) $day;
		if ($day < 1 || $day > 31) throw new \Exception("Invalid month day option: $day");

		// Drop into allowed month days to run.
		$this->days[$day] = true;
	}


	/**
	 * Specifies multiple days of the month to run.
	 *
	 * @param array $days
	 */
	public function setDays(array $days) {
		foreach($days as $day) $this->setDay($day);
	}


	/**
	 * Specify the time of day. Leave null to assume every increment of that time segment.
	 *
	 * NOTE: Cannot be used with ->setInterval().
	 *
	 * @param	int|null	$hour	24 hour format. 0 = 12am, 12=12pm, etc.
	 * @param	int|null	$minute
	 * @throws	Exception
	 */
	public function setTime($hour = null, $minute = null) {
		// Must provided at least an hour/minute and cannot be called in combination with intervals.
		if (!isset($hour) && !isset($minute)) throw new \Exception("Please specify at least an hour or minute");
		if (!empty($this->intervals)) throw new \Exception("The method 'setTime()' cannot be used in combination with 'setInterval()'.");

		// Drop specified time into array using -1 to indicate every hour or minute.
		if (!isset($hour)) $hour = -1;
		if (!isset($minute)) $minute = -1;
		$this->times[] = array($hour, $minute);
	}


	/**
	 * Flexibility (in minutes) after any of the specified times have passed allow when running a task. This is useful
	 * when setting up a cron job to run every 5 minutes but a specific time of 12:03AM is configured via ->setTime() or
	 * if a previous task in the same script takes a very long time to run
	 *
	 * @param $timeThreshold
	 */
	public function setTimeThreshold($timeThreshold) {
		$this->timeThreshold = max(0, (int) $timeThreshold);
	}


	/**
	 * Indicates if "or" (instead of "and") logic should be used when combining weekday and day of month settings.
	 *
	 * @param	bool	$useOrLogic
	 */
	public function setOrLogic($useOrLogic) {
		$this->useOrLogic = (bool) $useOrLogic;
	}


	/**
	 * Sets a task to run on a regular basis.
	 *
	 * NOTE: Cannot be used with ->setTime().
	 *
	 * @param	int		$frequency		Time period.
	 * @param	int		$waitPeriod		Multiplier for the specified time period (must be 1 or more).
	 * @throws	Exception
	 */
	public function setInterval($frequency, $waitPeriod = 1) {
		// Cannot be called in combination with times or set multiple intervals.
		if (!empty($this->times)) throw new \Exception("The method 'setInterval()' cannot be used in combination with 'setTime()'.");
		if (count($this->intervals) == 1) throw new \Exception("The method 'setInterval()' can only be called once.");

		// Validate input.
		$frequency = (int) $frequency;
		$wait = (int) $waitPeriod;
		if ($frequency < 0 || $frequency > 3) throw new \Exception("Invalid frequency used: '$frequency'");
		if ($waitPeriod < 1) throw new \Exception("Invalid wait period used: '$frequency'");

		// Add to intervals array.
		$this->intervals[$frequency] = $waitPeriod;

	}


	/**
	 * Indicates if this task should be run now or not.
	 *
	 * @param	bool	$reset	Will reset the counter to flag this task as having just ran, if set to true (default).
	 * 							Setting this to false will give your script the opportunity to try and run again on the
	 * 							next possible iteration. Doing so however will require that you call ->reset() to ensure
	 * 							that the task will be flagged has having been completed.
	 * @return	bool
	 * @throws	Exception
	 */
	public function run($reset = true) {
		if (empty($this->times) && empty($this->intervals)) throw new \Exception("An interval or one or more specific times must be provided first before calling the 'run()' method.");

		// Check days and weekdays first.
		$weekday = ((int) date("w", $this->time)) + 1;
		$day = (int) date("j", $this->time);

		// Flag a miss-match on a weekday or day of month ONLY if specified AND not currently matched.
		$badWeekday = (!empty($this->weekDays) ? !isset($this->weekDays[$weekday]) : false);
		$badDay = (!empty($this->days) ? !isset($this->days[$day]) : false);
		if ($badWeekday || $badDay) {
			// Don't run at all if "and" logic is being used.
			if (!$this->useOrLogic) return $this->setFailReason("Bad weekday or day of month (using 'and' logic).");

			// Otherwise, don't run if both are bad ("or" logic).
			if ($badWeekday && $badDay) return $this->setFailReason("Bad weekday and day of month (using 'or' logic).");
		}


		// Get last time this task has run.
		$defaults = array( // Using array for future extensibility...
			"last" => 0,
		);
		$settings = DynSetting::get($this->getSettingName(), $defaults);
		$lastRun = $settings["last"];

		// Check specific times or desired interval.
		if (!empty($this->times)) {
			// Get an offset based on the current time and the threshold specified.
			$thresholdStamp = $this->time - ($this->timeThreshold * 60);

			// Check specific times.
			$matches = false;
			foreach($this->times as $time) {
				// See if the specified time time falls around the current time. Also make sure that the last run didn't
				// already occur around the current time to prevent running multiple times in succession.
				list($hour, $minute) = $time;
				$stamp = $this->getStamp($hour, $minute);
				if ($stamp >= $thresholdStamp && $stamp <= $this->time && $lastRun < $thresholdStamp) {
					$matches = true;
					break;
				}
			}

			// Break and return false now if there were no matches.
			if (!$matches) return $this->setFailReason("Current time doesn't match times listed.");

		} elseif (!empty($this->intervals)) {
			// Check specific interval. Only using the first interval for now.
			$frequency = key($this->intervals);
			$waitPeriod = current($this->intervals);

			// Get the threshold to determine if the task should run again.
			switch($frequency) {
				case self::FREQ_MIN:
					$frequency_name = "minutes";
					break;

				case self::FREQ_HOUR:
					$frequency_name = "hours";
					break;

				case self::FREQ_DAY:
					$frequency_name = "days";
					break;

				case self::FREQ_MONTH:
					$frequency_name = "months";
					break;

				default:
					return $this->setFailReason("Invalid frequency provided: '$frequency'");
					break;
			}
			$thresholdStamp = strtotime("+$waitPeriod $frequency_name", $lastRun);
			if ($this->time < $thresholdStamp) return $this->setFailReason("Must wait " . round(($thresholdStamp - $this->time) / 60) . " minutes until next run.");
		}

		// So far all checks have passed, so reset (if applicable) and return true;
		if ($reset) $this->reset();
		return true;
	}


	/**
	 * Will flag this task as having been completed. Usually already called after ->run() is called and returns true.
	 */
	public function reset() {
		$settings = $this->getSettings();
		$settings["last"] = $this->time;
		$this->saveSettings($settings);
	}


	/**
	 * Returns settings for current task.
	 *
	 * @return	array
	 */
	protected function getSettings() {
		$defaults = array( // Using array for future extensibility...
			"last" => 0,
		);
		$settings = DynSetting::get($this->getSettingName(), $defaults);
		return $settings;
	}


	/**
	 * Persists the provided settings for the current task.
	 *
	 * @param	array	$settings
	 */
	protected function saveSettings(array $settings) {
		DynSetting::set($this->getSettingName(), $settings);
	}


	/**
	 * Indicates the name of the setting as it is stored in DynSetting.
	 *
	 * @return string
	 */
	protected function getSettingName() {
		return "task-" . md5($this->taskName);
	}


	/**
	 * Converts the provided hour/minute to a timestamp that occurs on the same day as today (via current ->time).
	 *
	 * @param	int	$hour
	 * @param	int	$minute
	 * @return	int
	 */
	protected function getStamp($hour, $minute) {
		$month = date("n", $this->time);
		$day = date("j", $this->time);
		$year = date("y", $this->time);
		return mktime($hour, $minute, 0, $month, $day, $year);
	}


	/**
	 * @param	string	$reason
	 * @return	bool
	 */
	protected function setFailReason($reason) {
		$this->failReason = $reason;
		return false;
	}


	/**
	 * Indicates any "run()" returned false.
	 *
	 * @param	bool	$verbose	Incorporate additional debug information.
	 * @return string
	 */
	public function getFailReason($verbose = false) {
		$reason = $this->failReason;
		if ($verbose) {
			$settings = $this->getSettings();
			$lastRun = date("g:i A, F j, Y", $settings["last"]);

			$reason .= "\n\nLast Run: $lastRun";
			$timesFormatted = array();
			foreach($this->times as $time) {
				list($hour, $minute) = $time;
				$timesFormatted[] = date("g:i A", $this->getStamp($hour, $minute));
			}
			$reason .= "\n\nTimes: " . natJoin($timesFormatted);
			$reason .= "\n\n" . print_r($settings, true);
			$reason .= "\n\n" . print_r($this, true);
		}
		return $reason;
	}
}
