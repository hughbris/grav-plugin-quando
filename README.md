# Quando Plugin

The **Quando** Plugin is for [Grav CMS](http://github.com/getgrav/grav).

It exposes business opening hours and other service hours to Grav as a Twig hash. It provides starter templates you might want to copy into your theme, customise, and include.

## Is this fit for your purposes?

There are a few real world scenarios which are not supported yet by the plugin and some bugs affecting some scenarios. Be aware of these issues and make sure they don't affect you before you get too far here:

* [No real time refreshes yet (#5)](https://github.com/hughbris/grav-plugin-quando/issues/5)
* [Irregular periods containing service hours don't show the hours yet (#4)](https://github.com/hughbris/grav-plugin-quando/issues/4)
* [Incomplete/untested microformats support (#3)](https://github.com/hughbris/grav-plugin-quando/issues/3)
* [YAML configuration is currently by hand (#2)](https://github.com/hughbris/grav-plugin-quando/issues/2)

## Installation

Installing the Quando plugin can be done in one of two ways. The GPM (Grav Package Manager) installation method enables you to quickly and easily install the plugin with a simple terminal command, while the manual method enables you to do so via a zip file.

### GPM Installation (Preferred)

The simplest way to install this plugin is via the [Grav Package Manager (GPM)](http://learn.getgrav.org/advanced/grav-gpm) through your system's terminal (also called the command line).  From the root of your Grav install type:

    bin/gpm install quando

This will install the Quando plugin into your `/user/plugins` directory within Grav. Its files can be found under `/your/site/grav/user/plugins/quando`.

### Manual Installation

To install this plugin, just download the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `quando`. You can find these files on [GitHub](https://github.com/hughbris/grav-plugin-quando) or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/quando

> NOTE: This plugin is a modular component for Grav which requires [Grav](http://github.com/getgrav/grav) and the [Error](https://github.com/getgrav/grav-plugin-error) and [Problems](https://github.com/getgrav/grav-plugin-problems) to operate.

## Configuration

Before configuring this plugin, you should copy the `user/plugins/quando/quando.yaml` to `user/config/plugins/quando.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true
hours: [] # When populated, an indexed array of service hours listings, a complex YAML structure best configured through the Admin dashboard (when the blueprint is done!). One of these listings should be called 'opening' if you want any of the templates to work out of the box.
```

## Usage

It's enabled by default on install but will do nothing unless you use it.

Configure the hours through the Admin plugin is probably simplest. Or copy the [sample YAML](data.sample.yaml) (in fact, you'll have to do that until I add a blueprint for hours).

Copy, customise, and include one of the sample templates, probably start with a simple partial:

* [This week's hours](templates/partials/panel_hours.html.twig) web panel
* [Regular schedule](templates/partials/hours.html.twig)
* [Exceptions list](templates/partials/hours_exceptions.html.twig)

### API

This is subject to imminent change because of [new nomenclature](https://github.com/hughbris/grav-plugin-quando/issues/6#issuecomment-397140502) and data restructuring. It's only available in PHP and Twig at present, though there are plans to [expose the API through shortcodes](https://github.com/hughbris/grav-plugin-quando/issues/7).

#### ServiceTimes object

Exposed to Twig as 'quando' (formerly 'openhrs'), an indexed array with members named for each property, e.g. 'quando.opening'.

_RENAMED from 'Opnhrs'_

##### Properties

* _Calendar_ **calendar**: holds the loaded _calendar_ data for the class instance. _RENAMED from 'schedule'_

##### Methods

* _ServiceTimes_ **__construct**(_Calendar_ calendar): instantiate and load a single _calendar_'s data
* _boolean_ **availableOn**(_string_ day_name, _RegularTimetable_ timetable(=NULL)): return whether service times are in _timetable_, which defaults to the current calendar's regular _timetable_. _RENAMED from 'opensOn'_
* _boolean_ **availableAt**(_DateTime_ dto): Returns whether the service is available at _dto_ in the current _calendar_. _RENAMED from 'openAt'_
* _Schedule_ **scheduleOn**(_string_ day_name, _RegularTimetable_ timetable(=NULL)): Returns the _schedule_ for _day_name_ in _timetable_. _RENAMED from 'hoursOn'_
* _string_ **briefTime**(_string_ timeOfDay, _string_ pattern(='g.ia'), _array_ truncateZeroComponents(=['.i'])): Convert 24 hour time string to _pattern_, leaving off the components listed in _truncateZeroComponents_ if they are zero. (static) (exposed as Twig filter)
* _array_ **formatSchedule**(_Schedule_ schedule,  _string_ pattern, _array_ truncateZeroComponents(=[])): Format all the timestamps in _schedule_ to `briefTime`. (static)
* _array_ **statusAt**(_DateTime_ dto, _boolean_ includeNext(=TRUE)): Return indexed information about service status at _dto_, and optionally after the next status change.
* _boolean_ **isAvailable**(): The service is currently available. _RENAMED from 'isOpen'_
* _DateTime_ **nextChange**(_DateTime_ dto, _Schedule_ hours(=NULL)): Returns the time of next status change after _dto_, _hours_ can be passed as a convenience.
* _array_ **schedulesAfter**(_DateTime_ start_dto, _integer_ days_duration): Return a date-indexed array of schedules from _days_duration_ days after _dto_.
* _array_ **schedulesWeek**(_DateTime_ start_dto, _integer_ days_context(=NULL)): Return a date-indexed array of 7 schedules from _days_context_ days before _start_dto_.
* _Timetable_ **regularTimetable**(): Return the `regular` _timetable_ for the current _calendar_. _RENAMED from 'regularSchedule'_
* _Timetable_ **getTimetable**(_string_ member(=NULL)): Return every _timetable_ in the current _calendar_, or just the one named "_member_". _RENAMED from 'getSchedule'_
* _array_ **getMeta**(_string_ property(=NULL)): Return an indexed array of allowed metadata properties from the calendar ('microdata', 'headings', 'labels'), or just the one named "_property_" if it's in the allowed list.

## Examples in the wild

* [QE2 Dental opening hours](https://qe2dental.co.nz/about/opening) page
* [Quando's official demo](http://behold.metamotive.co.nz/quando)

## Credits

I had this in my original composer.json for some reason: https://github.com/spatie/opening-hours. I'm sorry, I can't remember, but it looks like I have been inspired heavily by this, its API especially.

## To Do

It's all supposed to be in the Issues. (Github make it non-trivial for me to reliably link there in any relative way. I'm sorry for that unfortunate fail. But hey, you can do emojis and fontawesome in markdown - that's important, right?)

