(function(global) {

	var evalJSONP = function(callback) {
		return function(data) {
			var validJSON = false;
			if (typeof data == "string") {
				try {
					validJSON = JSON.parse(data);
				} catch (e) {
					/*invalid JSON*/
				}
			} else {
				validJSON = JSON.parse(JSON.stringify(data));
				window.console && console.warn('response data was not a JSON string');
			}

			if (validJSON) {
				callback(validJSON);
			} else {
				throw("JSONP call returned invalid or empty JSON");
			}
		}
	};

	var callbackCounter = 0;
		global.JSONPCallbacks = [];
		
	global.JSONP = function(url, callback) {
		var count = callbackCounter++;
		global.JSONPCallbacks[count] = evalJSONP(callback);
		url = url.replace('=callback', '=JSONPCallbacks[' + count + ']');

		var scriptTag = document.createElement('SCRIPT');
		scriptTag.src = url;
		document.getElementsByTagName('HEAD')[0].appendChild(scriptTag);
	};

})(this);