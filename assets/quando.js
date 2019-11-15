// from https://developer.mozilla.org/en-US/docs/Web/API/NodeList/forEach
if (window.NodeList && !NodeList.prototype.forEach) {
	NodeList.prototype.forEach = Array.prototype.forEach;
}

function Quando(calendar) {
	var __this = this;
	// this['prop'] = 'value';

	var __init = function(cal) {
		console.log(cal);

	}

	this.nextChange = function(){  // PHP nextChange($dto, $schedule=NULL)
		// FIXME: dummy stub
		return Date.now() + 5000;
		};

	this.getStatus = function() {  // PHP: isAvailable() $dto->statusAt['open']
		var status = Boolean(Math.round(Math.random()));
		console.log('Randomly changing to ' + status);
		return status; // FIXME
		};

	this.scheduleToggle = function(selector, replacements) {
		// you are allowed to replace one class and all text to a literal string, e.g.
		/*
			{
				'class': ['closed', 'open'], // 0: "off", 1: "on"
				'text': ['Shut!', 'Come in!'],
			};
		*/
		// let's check selector matches something and is supported before going any further ..
		if (document.querySelectorAll) {
			var domElements = document.querySelectorAll(selector);
			if (domElements.length == 0) {
				console.log('No elements match the selector "' + selector + '" in Quando.scheduleToggle');
				return;
			}
		}
		else {
			console.log('Quando.scheduleToggle() is aborted because document.querySelectorAll is not available');
			return;
		}
		if(replacements === undefined) {
			console.log('No DOM replacements passed to Quando.scheduleToggle()!');
			return;
			}
		var nextToggle = __this.nextChange();
		var interval = nextToggle - Date.now();
		if(interval < 0) { // by some terrible luck
			__this.scheduleToggle(selector, replacements); // we'll just try again
			return;
		}
		window.setTimeout(function() {
			console.log('doing it now @' + Date.now());
			var onNow = __this.getStatus();

			if(replacements.hasOwnProperty('class') && replacements.class.length > 1) {
				var onClass = replacements.class[1];
				var offClass = replacements.class[0];
				if (offClass.trim().length > 0 && onClass.trim().length > 0) {  // zero-length classnames cause an exception
					domElements.forEach( function(node) {
						console.log(node.classList);
						node.classList.replace((onNow ? offClass : onClass), (onNow ? onClass : offClass));
						console.log(node.classList);
						});
				}
			}

			if(replacements.hasOwnProperty('text') && replacements.text.length > 1) {
				var onText = replacements.text[1];
				var offText = replacements.text[0];
				domElements.forEach( function(node) {
					node.textContent = (onNow ? onText : offText);
					});
			}

			// now set the next change toggle schedule, and so it goes ..
			__this.scheduleToggle(selector, replacements);
			}, interval)
		};

	__init(calendar);
}