// from https://developer.mozilla.org/en-US/docs/Web/API/NodeList/forEach
if (window.NodeList && !NodeList.prototype.forEach) {
	NodeList.prototype.forEach = Array.prototype.forEach;
}

function Quando(calendar) {
	var __this = this;

	var __init = function(cal) {
		console.log(cal);
		__this['calendar'] = cal;
		__this['name'] = cal.name;
		__this['lastStatus'] = {};
	}

	this.statusNow = function(cb) {
		console.log('Calling .statusNow');
		var xhr = new XMLHttpRequest();
		xhr.open('GET', '/quando/data/plugins/quando/status');
		xhr.onreadystatechange = function() {
			if (this.readyState === XMLHttpRequest.DONE && this.status === 200) {
				__this.lastStatus = JSON.parse(xhr.response)[__this.name];
				cb();
			}
			};
		xhr.send();
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

		__this.statusNow( function() {
			console.log(__this.lastStatus);
			var nextToggle = Date.parse(__this.lastStatus.until.date);
			console.log(__this.lastStatus.until.date);
			var interval = nextToggle - Date.now();
			if(interval < 0) { // by some terrible luck
				__this.scheduleToggle(selector, replacements); // we'll just try again
				return;
			}
			var onNow = __this.lastStatus.available;

			window.setTimeout(function() {
				console.log('doing it now @' + Date.now());

				if(replacements.hasOwnProperty('class') && replacements.class.length > 1) {
					var onClass = replacements.class[1];
					var offClass = replacements.class[0];
					if (offClass.trim().length > 0 && onClass.trim().length > 0) {  // zero-length classnames cause an exception
						domElements.forEach( function(node) {
							console.log(node.classList);
							node.classList.replace((onNow ? onClass : offClass), (onNow ? offClass : onClass));
							console.log(node.classList);
							});
					}
				}

				if(replacements.hasOwnProperty('text') && replacements.text.length > 1) {
					var onText = replacements.text[1];
					var offText = replacements.text[0];
					domElements.forEach( function(node) {
						node.textContent = (onNow ? offText : onText);
						});
				}

				// now set the next change toggle schedule, and so it goes ..
				__this.scheduleToggle(selector, replacements);
				}, interval);
			});
		};

	__init(calendar);
}