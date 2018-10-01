<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class QuandoPlugin
 * @package Grav\Plugin
 */
class QuandoPlugin extends Plugin
{
    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents() {
        return [
            'onPluginsInitialized' => ['initializeIfRequired', 0],
        ];
    }

    /**
     * Initialize the plugin
     */
    public function initializeIfRequired() {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        // Enable the main event we are interested in
        $this->enable([
            'onTwigSiteVariables' => ['initializePlugin', 0],
            'onTwigTemplatePaths' => ['addTwigTemplatePaths', 0],
            'onTwigExtensions' => ['addTwigExtensions', 0],
        ]);
    }

    public function addTwigExtensions() {
        // require_once(__DIR__ . '/twig/ExampleTwigExtension.php');
        $this->grav['twig']->twig->addExtension(new DatetimeFormatExtension());
        $this->grav['twig']->twig->addExtension(new TranslateExtension());
    }

	/**
	* Add current directory to twig lookup paths.
	*/
	public function addTwigTemplatePaths()
	{
		$this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
	}

    public function initializePlugin() {
		// NB: $services_times vs. $service_times !!
		$services_times = $this->config['plugins']['quando']['hours'];

		$calendars = [];
		foreach($services_times as $name => $service_times) {
			$calendars[$name] = new ServiceTimes($service_times);
		}
		$this->grav['twig']->twig_vars['openhrs'] = $calendars; // TODO: remove deprecated name
		$this->grav['twig']->twig_vars['quando'] = $calendars;
	}

}

class DatetimeFormatExtension extends \Twig_Extension {

    public function getName() {
        return 'DatetimeFormatExtension';
    }

    public function getFilters() {
        return [
            new \Twig_SimpleFilter('briefTime', 'Grav\Plugin\ServiceTimes::briefTime'),
        ];
    }

}

class TranslateExtension extends \Twig_Extension {

    public function getName() {
        return 'TranslateExtension';
    }

    public function getFilters() {
        return [
            new \Twig_SimpleFilter('tav', [$this, 'translateArrayUsingValue']),
        ];
    }

	public function translateArrayUsingValue($key, $term, $lang=NULL, $case_sensitive= FALSE, $default=NULL) {
		global $grav;

		if (is_null($lang)) {
			$lang = $grav['language']->getDefault();
		}
		$vocab = $grav['language']->getTranslation($lang, $key, TRUE);

		if (!$case_sensitive) {
			$term = strtolower($term);
			$vocab = array_map('strtolower', $vocab);
		}

		$position = array_search($term, $vocab);

		if ($position === FALSE) {
			return $default;
		}
		return $grav['language']->translateArray($key, $position);
	}

}

class ServiceTimes {
	private $calendar;
	const DOW = [
		'sunday'    => 0,
		'monday'    => 1,
		'tuesday'   => 2,
		'wednesday' => 3,
		'thursday'  => 4,
		'friday'    => 5,
		'saturday'  => 6,
		]; // from PHP 7, we should be able to do this like const DOW = array_flip(['sunday','monday',....]);

	function __construct($calendar) {
		global $grav;
		$this->grav = $grav;
		$this->load($calendar);
		return $this;
	}

	private function load($calendar) {
		$this->calendar = $calendar;
		$this->timezone = new \DateTimeZone($this->calendar['timezone']);
		// TODO - validate the times ??
	}

	private function deprecatedMethodWarning($method_name) {
		$this->grav['debugger']->addMessage("Warning: Call to deprecated $method_name rendering template \"{$this->grav['page']->template()}\"");
	}

	public function availableOn($day_name, $timetable=NULL) {
		if (is_null($timetable)) {
			$timetable = $this->calendar['regular'];
		}
		return ( array_key_exists($day_name, $timetable) AND !empty($timetable[$day_name]) );
	}

	public function scheduleOn($day_name, $timetable=NULL) {
		if (is_null($timetable)) {
			$timetable = $this->regularTimetable();
		}

		$ret = [];

		if ($this->availableOn($day_name, $timetable)) {
			$ret = $timetable[$day_name];
		}

		return $ret;
	}

	private function availableLaterOnDay($dto, $schedule) { // "beforeCOB" ??
		if (empty($schedule)) {
			return FALSE;
		}
		else {
			$timeOfDay = $dto->format('H:i');
			$last_window = array_pop($schedule);
			$window_stops = array_key_exists('stops', $last_window) ? $last_window['stops'] : (array_key_exists('finishes', $last_window) ? $last_window['finishes'] : $last_window['shuts']); // TODO: remove deprecated property 'shuts' eventually
			return ( $window_stops > $timeOfDay );
		}
	}

    public static function briefTime($timeOfDay, $pattern='g.ia', $truncateZeroComponents=['.i']) {
		$to = \DateTime::createFromFormat('Y-m-d H:i', "1970-01-01 $timeOfDay"); // $to is "time object" (no significant date component)
		$mod_pattern = $pattern;
		foreach ($truncateZeroComponents as $truncateableComponent) {
			if (intval(strtr($to->format($truncateableComponent),'.-','  '), 10) == 0) {
				$mod_pattern = str_replace($truncateableComponent, '', $mod_pattern);
			}
		}
		return $to->format($mod_pattern);
    }

	public static function formatSchedule($schedule, $pattern, $truncateZeroComponents=[]) {
		$ret = [];
		foreach ($schedule as $window) {
			foreach ($window as $activity => $timeOfDay) {
				$window[$activity] = self::briefTime($timeOfDay, $pattern, $truncateZeroComponents);
			}
			$ret[] = $window;
		}
		return $ret;
	}

	public function statusAt($dto, $includeNext=TRUE) {
		$this->grav['debugger']->addMessage("Notice: Confirm template {$this->grav['page']->template()} or its inclusions do not use deprecated properties 'hours', 'open', 'open_on_day', or 'open_later_on_day'"); // TODO: remove deprecation notice

		$ret = [];
		$dto->setTimezone($this->timezone);

		$ret['hours'] = $ret['schedule'] = $this->scheduleAt($dto); // TODO: remove deprecated property
		$ret['open'] = $ret['available'] = $this->withinSchedule($dto, $ret['schedule']); // TODO: remove deprecated property
		$ret['open_on_day'] = $ret['available_on_day'] = !empty($ret['schedule']); // this isn't worth a function - "tradingDay" ??  // TODO: remove deprecated property
		$ret['open_later_on_day'] = $ret['available_later_on_day'] = $this->availableLaterOnDay($dto, $ret['schedule']); // TODO: remove deprecated property
		if ($includeNext) {
			$ret['until'] = $this->nextChange($dto, $ret['schedule']);
		}

		return $ret;
	}

	public function availableAt($dto) {
		return $this->statusAt($dto, FALSE)['open'];
	}

	public function isAvailable() {
		$dto = new \DateTime();
		return $this->availableAt($dto);
	}

	private function nextChange($dto, $schedule=NULL) {
		if (is_null($schedule)) {
			$schedule = $this->scheduleAt($dto);
		}
		$timeOfDay = $dto->format('H:i');

		foreach($schedule as $window) {
			$starts = array_key_exists('starts', $window) ? $window['starts'] : (array_key_exists('begins', $window) ? $window['begins'] : $window['opens']); // TODO: remove deprecated property 'opens' eventually
			$stops = array_key_exists('stops', $window) ? $window['stops'] : (array_key_exists('finishes', $window) ? $window['finishes'] : $window['shuts']); // TODO: remove deprecated property 'shuts' eventually

			// test for easy case where $timeOfDay is before this opening
			if ( $timeOfDay < $starts ) {
				return \DateTime::createFromFormat('Y-m-d H:i', $dto->format('Y-m-d ') . $starts);
			}

			if ( $starts <= $timeOfDay AND $stops > $timeOfDay ) {
				return \DateTime::createFromFormat('Y-m-d H:i', $dto->format('Y-m-d ') . $stops);
				// TODO: handle times going over midnight here, not needed now
			}
		}

		// still here? let's try the next day until we get something (recursive calls)
		$nextDay = $dto->add(new \DateInterval('P1D'));
		$nextDaySchedule = $this->scheduleAt($nextDay);
		$nextDay = \DateTime::createFromFormat('Y-m-d H:i', $nextDay->format('Y-m-d') . ' 00:00'); // bring it back to midnight
		return $this->nextChange($nextDay, $nextDaySchedule);

	}

	private function withinSchedule($dto, $schedule) {
		$timeOfDay = $dto->format('H:i');
		foreach($schedule as $window) {
			$starts = array_key_exists('starts', $window) ? $window['starts'] : (array_key_exists('begins', $window) ? $window['begins'] : $window['opens']); // TODO: remove deprecated property 'opens' eventually
			$stops = array_key_exists('stops', $window) ? $window['stops'] : (array_key_exists('finishes', $window) ? $window['finishes'] : $window['shuts']); // TODO: remove deprecated property 'shuts' eventually

			if ( $starts <= $timeOfDay AND $stops > $timeOfDay ) {
				return TRUE;
			}
		}
		return FALSE;
	}

	private function dayStringFromNumber($day_number) {
		return array_search($day_number, self::DOW);
	}

	private function dayStringFromDateTime($dto) {
		return $this->dayStringFromNumber($dto->format('w'));
	}

	private function scheduleAt($dto) {

		$date_ymd = $dto->format('Y-m-d');
		$day_name = $this->dayStringFromDateTime($dto);

		if ($day_name) {

			// look for period matches
			// NB - we are taking first match from periods array, so that's the precedence
			foreach($this->calendar['periods'] as $period) {
				$begins = array_key_exists('begin', $period) ? $period['begin'] : $period['start']; // TODO: remove deprecated property 'start' eventually
				$ends = array_key_exists('end', $period) ? $period['end'] : $period['finish']; // TODO: remove deprecated property 'finish' eventually
				if ( $begins <= $date_ymd AND $ends >= $date_ymd ) {
					return $this->findInSchedule($day_name, $date_ymd, $period);
				}
			}

			// look for global matches
			return $this->findInSchedule($day_name, $date_ymd, $this->calendar);
		}

		return [];
	}

	/*
	public function schedulesBetween($start, $finish) { // TODO: stub, make this wrap schedulesAfter()
	}
	*/

	public function schedulesAfter($start_dto, $days_duration) {
		$ret = []; $day = [];
		for ($i=0; $i < $days_duration ; $i++) {
			$day[] = ( $i == 0 ? clone $start_dto : clone $day[$i-1] );
			if ($i > 0) {
				$day[$i]->add(new \DateInterval('P1D')); // thanks PHP, WTF were you thinking when you created this class API ??
			}
			$ret[] = ['day' => $day[$i], 'schedule' => $this->scheduleAt($day[$i]), 'windows' => $this->scheduleAt($day[$i])]; // TODO: remove deprecated 'schedule' property here
		}
		return $ret;
	}

	public function regularTimetable() {
		return $this->calendar['regular'];
	}

	public function getTimetable($timetable=NULL) {
		if (is_null($timetable)) {
			return $this->calendar;
		}
		elseif (!array_key_exists($timetable, $this->calendar)) {
			return [];
		}
		else {
			return($this->calendar[$timetable]);
		}
	}

	public function schedulesWeek($start_dto, $days_context=NULL) {
		$day = clone $start_dto;
		if ($days_context) {
			$date_method = ( $days_context < 0 ? 'sub' : 'add' );
			call_user_func( [$day, $date_method], new \DateInterval('P' . abs($days_context) . 'D'));
		}
		return $this->schedulesAfter($day, 7);
	}

	private function findInSchedule($day_name, $date_ymd, $schedule) {

		// look for exception matches
		if (array_key_exists('exceptions', $schedule)) {
			if(array_key_exists($date_ymd, $schedule['exceptions'])) {
				return $schedule['exceptions'][$date_ymd]['hours'];
			}
		}

		// look for regular day matches
		return $this->scheduleOn($day_name, $schedule['regular']);
	}

	/* retrieve (only!) specific metadata properties from calendar, or an indexed array of all allowed metadata properties' values */
	public function getMeta($property=NULL) {
		$allowed_properties = ['headings', 'labels', 'microdata'];

		if (is_null($property)) {
			$ret = [];
			foreach($allowed_properties as $ap) {
				if (array_key_exists($ap, $this->calendar)) { // prevents a Twig error if labels is not declared, not sure if I should be requiring it instead
					$ret[$ap] = $this->calendar[$ap];
				}
			}
			return $ret;
		}

		if (!array_key_exists($property, $this->calendar)) {
			$this->grav['debugger']->addMessage("Warning: ServiceTimes::getMeta() called for non-existent property \"$property\" rendering template \"{$this->grav['page']->template()}\"");
			return [];
		}

		if (!in_array($property, $allowed_properties)) {
			$this->grav['debugger']->addMessage("Warning: ServiceTimes::getMeta() called for disallowed property \"$property\" rendering template \"{$this->grav['page']->template()}\"");
			return [];
		}

		// else
		return($this->calendar[$property]);

	}

	/* ******************************************************************* */
	/* Deprecated methods below retained for API compatibility: DO NOT USE */

	public function opensOn($day_name, $calendar=NULL) { // TODO: remove deprecated name
		$this->deprecatedMethodWarning(__METHOD__);
		return $this->availableOn($day_name, $calendar);
	}

	public function hoursOn($day_name, $calendar=NULL) { // TODO: remove deprecated name
		$this->deprecatedMethodWarning(__METHOD__);
		return $this->scheduleOn($day_name, $calendar);
	}

	public function openAt($dto) {  // TODO: remove deprecated name
		$this->deprecatedMethodWarning(__METHOD__);
		return $this->availableAt($dto);
	}

	public function isOpen() {  // TODO: remove deprecated name
		$this->deprecatedMethodWarning(__METHOD__);
		return $this->isAvailable();
	}

	public function getSchedule($member=NULL) { // TODO: remove deprecated name
		$this->deprecatedMethodWarning(__METHOD__);
		return $this->getTimetable($member);
	}

	public function regularSchedule() { // TODO: remove deprecated name
		$this->deprecatedMethodWarning(__METHOD__);
		return $this->regularTimetable();
	}

}
