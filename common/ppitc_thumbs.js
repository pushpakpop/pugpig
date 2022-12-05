var EXIT_CODES = {
  OK: 0,
  NO_URL: 1,
  NO_DESTINATION: 2,
  TIMEOUT: 3,
  PHANTOM_ERROR: 4,
  PAGE_ERROR: 5,
  UNKNOWN: 6
},
  TIMEOUT_MILLISECONDS = 7000,
  exitErrorCode = EXIT_CODES.UNKNOWN,
  page,
  sys = require("system"),
  width = 768,
  height = 1024,
  render_width = 768,
  zoom = width/render_width,
  url,
  filename,
  pageErrors = '',
  takenSnapshot = false;


setTimeout(function exitTimeout() {
  delayedExit('Timeout', EXIT_CODES.TIMEOUT);
}, TIMEOUT_MILLISECONDS);


function logMessage(msg, code) {
  if (code !== EXIT_CODES.OK) {
    console.error('ERROR: ' + msg);
  } else if (msg.length > 0) {
    console.log(msg);
  }
}


function delayedExit(msg, code) {
  logMessage(msg, code);
  exitErrorCode = code;

  setTimeout(function() {
    if (page) {
      page.close();
    }
    setTimeout(function() {
      phantom.exit(code);
    }, 200);
    phantom.onError = function(){};
    throw new Error('');
  }, 200);
}


function exit(msg, code) {
  logMessage(msg, code);
  exitErrorCode = code;
  phantomExit();
}


function exitError(msg, code) {
  exit('ERROR: ' + msg);
}


function phantomExit() {
  phantom.exit(exitErrorCode);
}


function getTraceDescription(trace) {
  msgStack = [];
  if (trace && trace.length) {
    msgStack.push('TRACE:');
    trace.forEach(function(t) {
      msgStack.push(' -> ' + t.file + ': ' + t.line + (t['function'] ? ' (in function "' + t['function'] +'")' : ''));
    });
  }
  return msgStack.join('\n');
}


phantom.onError = function(msg, trace) {
  delayedExit('phantom.onError : ' + msg + '\n' + getTraceDescription(trace), EXIT_CODES.PHANTOM_ERROR);
};


function renderSnapshot(page, format, quality, destination) {
  page.clipRect = {
    width: width,
    height: height
  };
  page.zoomFactor = zoom;

  page.render(destination, {
    format: format,
    quality: quality
  });

  takenSnapshot = true;
  console.log('Created snapshot "'+destination+'" with format "'+format+'" and quality "'+quality+'"');

  if (pageErrors.length>0) {
    delayedExit("COMPLETED WITH ERRORS:\n" + pageErrors, EXIT_CODES.PAGE_ERROR);
  } else {
    delayedExit('COMPLETED OK', EXIT_CODES.OK);
  }
}


function snapshotIsDelayed() {
  return page.evaluate(function() {
    return document.querySelector( 'meta[name="delaySnapshotUntilReady"]') || false;
  });
}

function pageOnError(msg, trace) {
  pageErrors = pageErrors + 'page.onError : ' + msg + '\n' + getTraceDescription(trace);
}

function pageOnConsoleMessage(data) {
  console.log(data);
}

function pageOnResourceError(request) {
  console.log('page.onResourceError request URL = ' + request.url);
  if (request.url === 'pugpig://onpageready') {
    renderSnapshot(page, 'png', 100, filename);
  }
}

//
//
//

if (sys.args.length < 2) {
  exitError("no url specified.", EXIT_CODES.NO_URL);
}

if (sys.args.length < 3) {
  exitError("no output filename specified.", EXIT_CODES.NO_DESTINATION);
}

url = sys.args[1];
filename = sys.args[2];
console.log('Rendering "' +url +'" to "' + filename +'"');

page = require("webpage").create();
page.onError = pageOnError;
page.onConsoleMessage = pageOnConsoleMessage;
page.onResourceError = pageOnResourceError;
page.viewportSize = {
    width: width,
    height: height
  };

page.open(url, function (status) {
  console.log('Page load returned: '+status);
  if (status !== 'success') {
    exitError("Unknown error : " + status);
  }

  page.evaluate(function() {
    document.body.bgColor = 'white';
  });

  if (!takenSnapshot) {
    if (snapshotIsDelayed()) {
      console.log('Snapshot is delayed');
    } else {
      console.log('Snapshot is immediate');
      renderSnapshot(page, 'png', 100, filename);
    }
  }
});
