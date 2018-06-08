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

		$openhrs = [];
		foreach($services_times as $name => $service_times) {
			$openhrs[$name] = new Opnhrs($service_times);
		}

		$this->grav['twig']->twig_vars['openhrs'] = $openhrs;
	}

}

class DatetimeFormatExtension extends \Twig_Extension {

    public function getName() {
        return 'DatetimeFormatExtension';
    }

    public function getFilters() {
        return [
            new \Twig_SimpleFilter('briefTime', 'Grav\Plugin\Opnhrs::briefTime'),
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

class Opnhrs {
	private $schedule;
	const DOW = [
		'sunday'    => 0,
		'monday'    => 1,
		'tuesday'   => 2,
		'wednesday' => 3,
		'thursday'  => 4,
		'friday'    => 5,
		'saturday'  => 6,
		];

	function __construct($service) {
		$this->load($service);
		return $this;
	}

	private function load($service) {
		$this->schedule = $service;
		$this->timezone = new \DateTimeZone($this->schedule['timezone']);
		// TODO - validate the times ??
	}

	public function opensOn($day_name, $schedule=NULL) {
		if (is_null($schedule)) {
			$schedule = $this->schedule['regular'];
		}
		return ( array_key_exists($day_name, $schedule) AND !empty($schedule[$day_name]) );
	}

	public function hoursOn($day_name, $schedule=NULL) {
		if (is_null($schedule)) {
			$schedule = $this->schedule['regular'];
		}

		$ret = [];

		if ($this->opensOn($day_name, $schedule)) {
			$ret = $schedule[$day_name];
		}

		return $ret;
	}

	private function opensLaterOnDay($dto, $hours) { // "beforeCOB" ??
		if (empty($hours)) {
			return FALSE;
		}
		else {
			$timeOfDay = $dto->format('H:i');
			$last_opening = array_pop($hours);
			return ( $last_opening['shuts'] > $timeOfDay );
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

	public function formatSchedule($schedule, $pattern, $truncateZeroComponents=[]) {
		$ret = [];
		foreach ($schedule as $opening) {
			foreach ($opening as $activity => $timeOfDay) {
				$opening[$activity] = $this->briefTime($timeOfDay, $pattern, $truncateZeroComponents);
			}
			$ret[] = $opening;
		}
		return $ret;
	}

	public function statusAt($dto, $includeNext=TRUE) {
		$ret = [];
		$dto->setTimezone($this->timezone);

		$ret['hours'] = $this->scheduleAt($dto);
		$ret['open'] = $this->withinHours($dto, $ret['hours']);
		$ret['open_on_day'] = !empty($ret['hours']); // this isn't worth a function - "tradingDay" ??
		$ret['open_later_on_day'] = $this->opensLaterOnDay($dto, $ret['hours']);
		// $ret['debug'] = [$dto, $ret['hours']];
		// $ret['testsched'] = [['opens'=>'00:10','shuts'=>'18:00'],['opens'=>'19:20','shuts'=>'21:00']];
		// $ret['debug'] = $this->formatSchedule($ret['testsched'], 'g.ia', ['.i']);
		// $ret['debug'] = $this->schedulesAfter($dto, 2);
		// $ret['debug'] = $this->schedulesWeek($dto, -1);
		if ($includeNext) {
			$ret['until'] = $this->nextChange($dto, $ret['hours']);
		}

		return $ret;
	}

	public function openAt($dto) {
		return $this->statusAt($dto, FALSE)['open'];
	}

	public function isOpen() {
		$dto = new \DateTime();
		return $this->openAt($dto);
	}

	private function nextChange($dto, $hours) {
		$timeOfDay = $dto->format('H:i');

		foreach($hours as $seq => $opening) {

			// test for easy case where $timeOfDay is before the first open
			if ( $seq == 0 AND $timeOfDay < $opening['opens'] ) {
				return \DateTime::createFromFormat('Y-m-d H:i', $dto->format('Y-m-d ') . $opening['opens']);
			}

			if ( $opening['opens'] <= $timeOfDay AND $opening['shuts'] > $timeOfDay ) {
				return \DateTime::createFromFormat('Y-m-d H:i', $dto->format('Y-m-d ') . $opening['shuts']);
				// TODO: handle times going over midnight here, not needed now
			}
		}

		// still here? let's try the next day until we get something (recursive calls)
		$nextDay = $dto->add(new \DateInterval('P1D'));
		$nextDayHours = $this->scheduleAt($nextDay);
		$nextDay = \DateTime::createFromFormat('Y-m-d H:i', $nextDay->format('Y-m-d') . ' 00:00'); // bring it back to midnight
		return $this->nextChange($nextDay, $nextDayHours);

	}

	private function withinHours($dto, $hours) {
		$timeOfDay = $dto->format('H:i');
		foreach($hours as $opening) {
			if ( $opening['opens'] <= $timeOfDay AND $opening['shuts'] > $timeOfDay ) {
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
			foreach($this->schedule['periods'] as $period) {
				if ( $period['start'] <= $date_ymd AND $period['finish'] >= $date_ymd ) {
					return $this->findInSchedule($day_name, $date_ymd, $period);
				}
			}

			// look for global matches
			return $this->findInSchedule($day_name, $date_ymd, $this->schedule);
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
			$ret[] = ['day' => $day[$i], 'schedule' => $this->scheduleAt($day[$i])];
		}
		return $ret;
	}

	public function regularSchedule() {
		return $this->schedule['regular'];
		/*
		$ret = [];
		foreach (array_keys(self::DOW) as $day_name) {
			$ret[] = $this->hoursOn($day_name);
		}

		return $ret;
		*/
	}

	public function getSchedule($member=NULL) {
		if (is_null($member)) {
			return $this->schedule;
		}
		elseif (!array_key_exists($member, $this->schedule)) {
			return [];
		}
		else {
			return($this->schedule[$member]);
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
		return $this->hoursOn($day_name, $schedule['regular']);
	}

}
