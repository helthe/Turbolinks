var CSRFToken, Click, ComponentUrl, EVENTS, Link, ProgressBar, ProgressBarAPI, browserIsBuggy, browserSupportsCustomEvents, browserSupportsPushState, browserSupportsTurbolinks, cacheCurrentPage, cacheSize, changePage, clone, constrainPageCacheTo, createDocument, crossOriginRedirect, currentState, disableRequestCaching, enableTransitionCache, executeScriptTags, extractTitleAndBody, fetch, fetchHistory, fetchReplacement, findNodes, findNodesMatchingKeys, initializeTurbolinks, installDocumentReadyPageEventTriggers, installJqueryAjaxSuccessPageUpdateTrigger, loadedAssets, manuallyTriggerHashChangeForFirefox, onHistoryChange, onNodeRemoved, pageCache, pageChangePrevented, pagesCached, popCookie, processResponse, progressBar, ref, referer, reflectNewUrl, reflectRedirectedUrl, rememberCurrentUrlAndState, rememberReferer, removeCurrentPageFromCache, replace, requestCachingEnabled, requestMethodIsSafe, setAutofocusElement, swapNodes, transitionCacheEnabled, transitionCacheFor, triggerEvent, ua, uniqueId, updateScrollPosition, visit, xhr,
  slice = [].slice,
  indexOf = [].indexOf || function(item) { for (var i = 0, l = this.length; i < l; i++) { if (i in this && this[i] === item) return i; } return -1; },
  extend = function(child, parent) { for (var key in parent) { if (hasProp.call(parent, key)) child[key] = parent[key]; } function ctor() { this.constructor = child; } ctor.prototype = parent.prototype; child.prototype = new ctor(); child.__super__ = parent.prototype; return child; },
  hasProp = {}.hasOwnProperty,
  bind = function(fn, me){ return function(){ return fn.apply(me, arguments); }; };

pageCache = {};

cacheSize = 10;

transitionCacheEnabled = false;

requestCachingEnabled = true;

progressBar = null;

currentState = null;

loadedAssets = null;

referer = null;

xhr = null;

EVENTS = {
  BEFORE_CHANGE: 'page:before-change',
  FETCH: 'page:fetch',
  RECEIVE: 'page:receive',
  CHANGE: 'page:change',
  UPDATE: 'page:update',
  LOAD: 'page:load',
  PARTIAL_LOAD: 'page:partial-load',
  RESTORE: 'page:restore',
  BEFORE_UNLOAD: 'page:before-unload',
  AFTER_REMOVE: 'page:after-remove'
};

fetch = function(url, options) {
  var cachedPage;
  if (options == null) {
    options = {};
  }
  url = new ComponentUrl(url);
  if (options.change || options.keep) {
    removeCurrentPageFromCache();
  } else {
    cacheCurrentPage();
  }
  rememberReferer();
  if (progressBar != null) {
    progressBar.start();
  }
  if (transitionCacheEnabled && !options.change && (cachedPage = transitionCacheFor(url.absolute))) {
    fetchHistory(cachedPage);
    options.showProgressBar = false;
    options.scroll = false;
  } else {
    if (options.change) {
      if (options.scroll == null) {
        options.scroll = false;
      }
    }
  }
  return fetchReplacement(url, options);
};

transitionCacheFor = function(url) {
  var cachedPage;
  cachedPage = pageCache[url];
  if (cachedPage && !cachedPage.transitionCacheDisabled) {
    return cachedPage;
  }
};

enableTransitionCache = function(enable) {
  if (enable == null) {
    enable = true;
  }
  return transitionCacheEnabled = enable;
};

disableRequestCaching = function(disable) {
  if (disable == null) {
    disable = true;
  }
  requestCachingEnabled = !disable;
  return disable;
};

fetchReplacement = function(url, options) {
  if (options.cacheRequest == null) {
    options.cacheRequest = requestCachingEnabled;
  }
  if (options.showProgressBar == null) {
    options.showProgressBar = true;
  }
  triggerEvent(EVENTS.FETCH, {
    url: url.absolute
  });
  if (xhr != null) {
    xhr.abort();
  }
  xhr = new XMLHttpRequest;
  xhr.open('GET', url.formatForXHR({
    cache: options.cacheRequest
  }), true);
  xhr.setRequestHeader('Accept', 'text/html, application/xhtml+xml, application/xml');
  xhr.setRequestHeader('X-XHR-Referer', referer);
  xhr.onload = function() {
    var doc, loadedNodes;
    triggerEvent(EVENTS.RECEIVE, {
      url: url.absolute
    });
    if (doc = processResponse()) {
      reflectNewUrl(url);
      reflectRedirectedUrl();
      loadedNodes = changePage.apply(null, slice.call(extractTitleAndBody(doc)).concat([options]));
      if (options.showProgressBar) {
        if (progressBar != null) {
          progressBar.done();
        }
      }
      manuallyTriggerHashChangeForFirefox();
      updateScrollPosition(options.scroll);
      triggerEvent((options.change ? EVENTS.PARTIAL_LOAD : EVENTS.LOAD), loadedNodes);
      return constrainPageCacheTo(cacheSize);
    } else {
      if (progressBar != null) {
        progressBar.done();
      }
      return document.location.href = crossOriginRedirect() || url.absolute;
    }
  };
  if (progressBar && options.showProgressBar) {
    xhr.onprogress = (function(_this) {
      return function(event) {
        var percent;
        percent = event.lengthComputable ? event.loaded / event.total * 100 : progressBar.value + (100 - progressBar.value) / 10;
        return progressBar.advanceTo(percent);
      };
    })(this);
  }
  xhr.onloadend = function() {
    return xhr = null;
  };
  xhr.onerror = function() {
    return document.location.href = url.absolute;
  };
  return xhr.send();
};

fetchHistory = function(cachedPage, options) {
  if (options == null) {
    options = {};
  }
  if (xhr != null) {
    xhr.abort();
  }
  changePage(cachedPage.title, cachedPage.body, null, {
    runScripts: false
  });
  if (progressBar != null) {
    progressBar.done();
  }
  updateScrollPosition(options.scroll);
  return triggerEvent(EVENTS.RESTORE);
};

cacheCurrentPage = function() {
  var currentStateUrl;
  currentStateUrl = new ComponentUrl(currentState.url);
  return pageCache[currentStateUrl.absolute] = {
    url: currentStateUrl.relative,
    body: document.body,
    title: document.title,
    positionY: window.pageYOffset,
    positionX: window.pageXOffset,
    cachedAt: new Date().getTime(),
    transitionCacheDisabled: document.querySelector('[data-no-transition-cache]') != null
  };
};

removeCurrentPageFromCache = function() {
  return delete pageCache[new ComponentUrl(currentState.url).absolute];
};

pagesCached = function(size) {
  if (size == null) {
    size = cacheSize;
  }
  if (/^[\d]+$/.test(size)) {
    return cacheSize = parseInt(size);
  }
};

constrainPageCacheTo = function(limit) {
  var cacheTimesRecentFirst, i, key, len, pageCacheKeys, results;
  pageCacheKeys = Object.keys(pageCache);
  cacheTimesRecentFirst = pageCacheKeys.map(function(url) {
    return pageCache[url].cachedAt;
  }).sort(function(a, b) {
    return b - a;
  });
  results = [];
  for (i = 0, len = pageCacheKeys.length; i < len; i++) {
    key = pageCacheKeys[i];
    if (!(pageCache[key].cachedAt <= cacheTimesRecentFirst[limit])) {
      continue;
    }
    onNodeRemoved(pageCache[key].body);
    results.push(delete pageCache[key]);
  }
  return results;
};

replace = function(html, options) {
  var loadedNodes;
  if (options == null) {
    options = {};
  }
  loadedNodes = changePage.apply(null, slice.call(extractTitleAndBody(createDocument(html))).concat([options]));
  return triggerEvent((options.change ? EVENTS.PARTIAL_LOAD : EVENTS.LOAD), loadedNodes);
};

changePage = function(title, body, csrfToken, options) {
  var changedNodes, currentBody, nodesToChange, nodesToKeep, ref, scriptsToRun;
  title = (ref = options.title) != null ? ref : title;
  currentBody = document.body;
  if (options.change) {
    nodesToChange = findNodes(currentBody, '[data-turbolinks-temporary]');
    nodesToChange.push.apply(nodesToChange, findNodesMatchingKeys(currentBody, options.change));
  } else {
    nodesToChange = [currentBody];
  }
  triggerEvent(EVENTS.BEFORE_UNLOAD, nodesToChange);
  if (title !== false) {
    document.title = title;
  }
  if (options.change) {
    changedNodes = swapNodes(body, nodesToChange, {
      keep: false
    });
  } else {
    if (!options.flush) {
      nodesToKeep = findNodes(currentBody, '[data-turbolinks-permanent]');
      if (options.keep) {
        nodesToKeep.push.apply(nodesToKeep, findNodesMatchingKeys(currentBody, options.keep));
      }
      swapNodes(body, nodesToKeep, {
        keep: true
      });
    }
    document.body = body;
    if (csrfToken != null) {
      CSRFToken.update(csrfToken);
    }
    setAutofocusElement();
    changedNodes = [body];
  }
  scriptsToRun = options.runScripts === false ? 'script[data-turbolinks-eval="always"]' : 'script:not([data-turbolinks-eval="false"])';
  executeScriptTags(scriptsToRun);
  currentState = window.history.state;
  triggerEvent(EVENTS.CHANGE, changedNodes);
  triggerEvent(EVENTS.UPDATE);
  return changedNodes;
};

findNodes = function(body, selector) {
  return Array.prototype.slice.apply(body.querySelectorAll(selector));
};

findNodesMatchingKeys = function(body, keys) {
  var i, key, len, matchingNodes, ref;
  matchingNodes = [];
  ref = (Array.isArray(keys) ? keys : [keys]);
  for (i = 0, len = ref.length; i < len; i++) {
    key = ref[i];
    matchingNodes.push.apply(matchingNodes, findNodes(body, '[id^="' + key + ':"], [id="' + key + '"]'));
  }
  return matchingNodes;
};

swapNodes = function(targetBody, existingNodes, options) {
  var changedNodes, existingNode, i, len, nodeId, targetNode;
  changedNodes = [];
  for (i = 0, len = existingNodes.length; i < len; i++) {
    existingNode = existingNodes[i];
    if (!(nodeId = existingNode.getAttribute('id'))) {
      throw new Error("Turbolinks partial replace: turbolinks elements must have an id.");
    }
    if (targetNode = targetBody.querySelector('[id="' + nodeId + '"]')) {
      if (options.keep) {
        existingNode.parentNode.insertBefore(existingNode.cloneNode(true), existingNode);
        existingNode = targetNode.ownerDocument.adoptNode(existingNode);
        targetNode.parentNode.replaceChild(existingNode, targetNode);
      } else {
        targetNode = targetNode.cloneNode(true);
        targetNode = existingNode.ownerDocument.importNode(targetNode, true);
        existingNode.parentNode.replaceChild(targetNode, existingNode);
        onNodeRemoved(existingNode);
        changedNodes.push(targetNode);
      }
    }
  }
  return changedNodes;
};

onNodeRemoved = function(node) {
  if (typeof jQuery !== 'undefined') {
    jQuery(node).remove();
  }
  return triggerEvent(EVENTS.AFTER_REMOVE, node);
};

executeScriptTags = function(selector) {
  var attr, copy, i, j, len, len1, nextSibling, parentNode, ref, ref1, script, scripts;
  scripts = document.body.querySelectorAll(selector);
  for (i = 0, len = scripts.length; i < len; i++) {
    script = scripts[i];
    if (!((ref = script.type) === '' || ref === 'text/javascript')) {
      continue;
    }
    copy = document.createElement('script');
    ref1 = script.attributes;
    for (j = 0, len1 = ref1.length; j < len1; j++) {
      attr = ref1[j];
      copy.setAttribute(attr.name, attr.value);
    }
    if (!script.hasAttribute('async')) {
      copy.async = false;
    }
    copy.appendChild(document.createTextNode(script.innerHTML));
    parentNode = script.parentNode, nextSibling = script.nextSibling;
    parentNode.removeChild(script);
    parentNode.insertBefore(copy, nextSibling);
  }
};

setAutofocusElement = function() {
  var autofocusElement, list;
  autofocusElement = (list = document.querySelectorAll('input[autofocus], textarea[autofocus]'))[list.length - 1];
  if (autofocusElement && document.activeElement !== autofocusElement) {
    return autofocusElement.focus();
  }
};

reflectNewUrl = function(url) {
  if ((url = new ComponentUrl(url)).absolute !== referer) {
    return window.history.pushState({
      turbolinks: true,
      url: url.absolute
    }, '', url.absolute);
  }
};

reflectRedirectedUrl = function() {
  var location, preservedHash;
  if (location = xhr.getResponseHeader('X-XHR-Redirected-To')) {
    location = new ComponentUrl(location);
    preservedHash = location.hasNoHash() ? document.location.hash : '';
    return window.history.replaceState(window.history.state, '', location.href + preservedHash);
  }
};

crossOriginRedirect = function() {
  var redirect;
  if (((redirect = xhr.getResponseHeader('Location')) != null) && (new ComponentUrl(redirect)).crossOrigin()) {
    return redirect;
  }
};

rememberReferer = function() {
  return referer = document.location.href;
};

rememberCurrentUrlAndState = function() {
  window.history.replaceState({
    turbolinks: true,
    url: document.location.href
  }, '', document.location.href);
  return currentState = window.history.state;
};

manuallyTriggerHashChangeForFirefox = function() {
  var url;
  if (navigator.userAgent.match(/Firefox/) && !(url = new ComponentUrl).hasNoHash()) {
    window.history.replaceState(currentState, '', url.withoutHash());
    return document.location.hash = url.hash;
  }
};

updateScrollPosition = function(position) {
  if (Array.isArray(position)) {
    return window.scrollTo(position[0], position[1]);
  } else if (position !== false) {
    if (document.location.hash) {
      return document.location.href = document.location.href;
    } else {
      return window.scrollTo(0, 0);
    }
  }
};

clone = function(original) {
  var copy, key, value;
  if ((original == null) || typeof original !== 'object') {
    return original;
  }
  copy = new original.constructor();
  for (key in original) {
    value = original[key];
    copy[key] = clone(value);
  }
  return copy;
};

popCookie = function(name) {
  var ref, value;
  value = ((ref = document.cookie.match(new RegExp(name + "=(\\w+)"))) != null ? ref[1].toUpperCase() : void 0) || '';
  document.cookie = name + '=; expires=Thu, 01-Jan-70 00:00:01 GMT; path=/';
  return value;
};

uniqueId = function() {
  return new Date().getTime().toString(36);
};

triggerEvent = function(name, data) {
  var event;
  if (typeof Prototype !== 'undefined') {
    Event.fire(document, name, data, true);
  }
  event = document.createEvent('Events');
  if (data) {
    event.data = data;
  }
  event.initEvent(name, true, true);
  return document.dispatchEvent(event);
};

pageChangePrevented = function(url) {
  return !triggerEvent(EVENTS.BEFORE_CHANGE, {
    url: url
  });
};

processResponse = function() {
  var assetsChanged, clientOrServerError, doc, downloadingFile, extractTrackAssets, intersection, validContent;
  clientOrServerError = function() {
    var ref;
    return (400 <= (ref = xhr.status) && ref < 600);
  };
  validContent = function() {
    var contentType;
    return ((contentType = xhr.getResponseHeader('Content-Type')) != null) && contentType.match(/^(?:text\/html|application\/xhtml\+xml|application\/xml)(?:;|$)/);
  };
  downloadingFile = function() {
    var disposition;
    return ((disposition = xhr.getResponseHeader('Content-Disposition')) != null) && disposition.match(/^attachment/);
  };
  extractTrackAssets = function(doc) {
    var i, len, node, ref, results;
    ref = doc.querySelector('head').childNodes;
    results = [];
    for (i = 0, len = ref.length; i < len; i++) {
      node = ref[i];
      if ((typeof node.getAttribute === "function" ? node.getAttribute('data-turbolinks-track') : void 0) != null) {
        results.push(node.getAttribute('src') || node.getAttribute('href'));
      }
    }
    return results;
  };
  assetsChanged = function(doc) {
    var fetchedAssets;
    loadedAssets || (loadedAssets = extractTrackAssets(document));
    fetchedAssets = extractTrackAssets(doc);
    return fetchedAssets.length !== loadedAssets.length || intersection(fetchedAssets, loadedAssets).length !== loadedAssets.length;
  };
  intersection = function(a, b) {
    var i, len, ref, results, value;
    if (a.length > b.length) {
      ref = [b, a], a = ref[0], b = ref[1];
    }
    results = [];
    for (i = 0, len = a.length; i < len; i++) {
      value = a[i];
      if (indexOf.call(b, value) >= 0) {
        results.push(value);
      }
    }
    return results;
  };
  if (!clientOrServerError() && validContent() && !downloadingFile()) {
    doc = createDocument(xhr.responseText);
    if (doc && !assetsChanged(doc)) {
      return doc;
    }
  }
};

extractTitleAndBody = function(doc) {
  var title;
  title = doc.querySelector('title');
  return [title != null ? title.textContent : void 0, doc.querySelector('body'), CSRFToken.get(doc).token];
};

CSRFToken = {
  get: function(doc) {
    var tag;
    if (doc == null) {
      doc = document;
    }
    return {
      node: tag = doc.querySelector('meta[name="csrf-token"]'),
      token: tag != null ? typeof tag.getAttribute === "function" ? tag.getAttribute('content') : void 0 : void 0
    };
  },
  update: function(latest) {
    var current;
    current = this.get();
    if ((current.token != null) && (latest != null) && current.token !== latest) {
      return current.node.setAttribute('content', latest);
    }
  }
};

createDocument = function(html) {
  var doc;
  if (/<(html|body)/i.test(html)) {
    doc = document.documentElement.cloneNode();
    doc.innerHTML = html;
  } else {
    doc = document.documentElement.cloneNode(true);
    doc.querySelector('body').innerHTML = html;
  }
  doc.head = doc.querySelector('head');
  doc.body = doc.querySelector('body');
  return doc;
};

ComponentUrl = (function() {
  function ComponentUrl(original1) {
    this.original = original1 != null ? original1 : document.location.href;
    if (this.original.constructor === ComponentUrl) {
      return this.original;
    }
    this._parse();
  }

  ComponentUrl.prototype.withoutHash = function() {
    return this.href.replace(this.hash, '').replace('#', '');
  };

  ComponentUrl.prototype.withoutHashForIE10compatibility = function() {
    return this.withoutHash();
  };

  ComponentUrl.prototype.hasNoHash = function() {
    return this.hash.length === 0;
  };

  ComponentUrl.prototype.crossOrigin = function() {
    return this.origin !== (new ComponentUrl).origin;
  };

  ComponentUrl.prototype.formatForXHR = function(options) {
    if (options == null) {
      options = {};
    }
    return (options.cache ? this : this.withAntiCacheParam()).withoutHashForIE10compatibility();
  };

  ComponentUrl.prototype.withAntiCacheParam = function() {
    return new ComponentUrl(/([?&])_=[^&]*/.test(this.absolute) ? this.absolute.replace(/([?&])_=[^&]*/, "$1_=" + (uniqueId())) : new ComponentUrl(this.absolute + (/\?/.test(this.absolute) ? "&" : "?") + ("_=" + (uniqueId()))));
  };

  ComponentUrl.prototype._parse = function() {
    var ref;
    (this.link != null ? this.link : this.link = document.createElement('a')).href = this.original;
    ref = this.link, this.href = ref.href, this.protocol = ref.protocol, this.host = ref.host, this.hostname = ref.hostname, this.port = ref.port, this.pathname = ref.pathname, this.search = ref.search, this.hash = ref.hash;
    this.origin = [this.protocol, '//', this.hostname].join('');
    if (this.port.length !== 0) {
      this.origin += ":" + this.port;
    }
    this.relative = [this.pathname, this.search, this.hash].join('');
    return this.absolute = this.href;
  };

  return ComponentUrl;

})();

Link = (function(superClass) {
  extend(Link, superClass);

  Link.HTML_EXTENSIONS = ['html'];

  Link.allowExtensions = function() {
    var extension, extensions, i, len;
    extensions = 1 <= arguments.length ? slice.call(arguments, 0) : [];
    for (i = 0, len = extensions.length; i < len; i++) {
      extension = extensions[i];
      Link.HTML_EXTENSIONS.push(extension);
    }
    return Link.HTML_EXTENSIONS;
  };

  function Link(link1) {
    this.link = link1;
    if (this.link.constructor === Link) {
      return this.link;
    }
    this.original = this.link.href;
    this.originalElement = this.link;
    this.link = this.link.cloneNode(false);
    Link.__super__.constructor.apply(this, arguments);
  }

  Link.prototype.shouldIgnore = function() {
    return this.crossOrigin() || this._anchored() || this._nonHtml() || this._optOut() || this._target();
  };

  Link.prototype._anchored = function() {
    return (this.hash.length > 0 || this.href.charAt(this.href.length - 1) === '#') && (this.withoutHash() === (new ComponentUrl).withoutHash());
  };

  Link.prototype._nonHtml = function() {
    return this.pathname.match(/\.[a-z]+$/g) && !this.pathname.match(new RegExp("\\.(?:" + (Link.HTML_EXTENSIONS.join('|')) + ")?$", 'g'));
  };

  Link.prototype._optOut = function() {
    var ignore, link;
    link = this.originalElement;
    while (!(ignore || link === document)) {
      ignore = link.getAttribute('data-no-turbolink') != null;
      link = link.parentNode;
    }
    return ignore;
  };

  Link.prototype._target = function() {
    return this.link.target.length !== 0;
  };

  return Link;

})(ComponentUrl);

Click = (function() {
  Click.installHandlerLast = function(event) {
    if (!event.defaultPrevented) {
      document.removeEventListener('click', Click.handle, false);
      return document.addEventListener('click', Click.handle, false);
    }
  };

  Click.handle = function(event) {
    return new Click(event);
  };

  function Click(event1) {
    this.event = event1;
    if (this.event.defaultPrevented) {
      return;
    }
    this._extractLink();
    if (this._validForTurbolinks()) {
      if (!pageChangePrevented(this.link.absolute)) {
        visit(this.link.href);
      }
      this.event.preventDefault();
    }
  }

  Click.prototype._extractLink = function() {
    var link;
    link = this.event.target;
    while (!(!link.parentNode || link.nodeName === 'A')) {
      link = link.parentNode;
    }
    if (link.nodeName === 'A' && link.href.length !== 0) {
      return this.link = new Link(link);
    }
  };

  Click.prototype._validForTurbolinks = function() {
    return (this.link != null) && !(this.link.shouldIgnore() || this._nonStandardClick());
  };

  Click.prototype._nonStandardClick = function() {
    return this.event.which > 1 || this.event.metaKey || this.event.ctrlKey || this.event.shiftKey || this.event.altKey;
  };

  return Click;

})();

ProgressBar = (function() {
  var className, originalOpacity;

  className = 'turbolinks-progress-bar';

  originalOpacity = 0.99;

  ProgressBar.enable = function() {
    return progressBar != null ? progressBar : progressBar = new ProgressBar('html');
  };

  ProgressBar.disable = function() {
    if (progressBar != null) {
      progressBar.uninstall();
    }
    return progressBar = null;
  };

  function ProgressBar(elementSelector) {
    this.elementSelector = elementSelector;
    this._trickle = bind(this._trickle, this);
    this._reset = bind(this._reset, this);
    this.value = 0;
    this.content = '';
    this.speed = 300;
    this.opacity = originalOpacity;
    this.install();
  }

  ProgressBar.prototype.install = function() {
    this.element = document.querySelector(this.elementSelector);
    this.element.classList.add(className);
    this.styleElement = document.createElement('style');
    document.head.appendChild(this.styleElement);
    return this._updateStyle();
  };

  ProgressBar.prototype.uninstall = function() {
    this.element.classList.remove(className);
    return document.head.removeChild(this.styleElement);
  };

  ProgressBar.prototype.start = function() {
    if (this.value > 0) {
      this._reset();
      this._reflow();
    }
    return this.advanceTo(5);
  };

  ProgressBar.prototype.advanceTo = function(value) {
    var ref;
    if ((value > (ref = this.value) && ref <= 100)) {
      this.value = value;
      this._updateStyle();
      if (this.value === 100) {
        return this._stopTrickle();
      } else if (this.value > 0) {
        return this._startTrickle();
      }
    }
  };

  ProgressBar.prototype.done = function() {
    if (this.value > 0) {
      this.advanceTo(100);
      return this._finish();
    }
  };

  ProgressBar.prototype._finish = function() {
    this.fadeTimer = setTimeout((function(_this) {
      return function() {
        _this.opacity = 0;
        return _this._updateStyle();
      };
    })(this), this.speed / 2);
    return this.resetTimer = setTimeout(this._reset, this.speed);
  };

  ProgressBar.prototype._reflow = function() {
    return this.element.offsetHeight;
  };

  ProgressBar.prototype._reset = function() {
    this._stopTimers();
    this.value = 0;
    this.opacity = originalOpacity;
    return this._withSpeed(0, (function(_this) {
      return function() {
        return _this._updateStyle(true);
      };
    })(this));
  };

  ProgressBar.prototype._stopTimers = function() {
    this._stopTrickle();
    clearTimeout(this.fadeTimer);
    return clearTimeout(this.resetTimer);
  };

  ProgressBar.prototype._startTrickle = function() {
    if (this.trickleTimer) {
      return;
    }
    return this.trickleTimer = setTimeout(this._trickle, this.speed);
  };

  ProgressBar.prototype._stopTrickle = function() {
    clearTimeout(this.trickleTimer);
    return delete this.trickleTimer;
  };

  ProgressBar.prototype._trickle = function() {
    this.advanceTo(this.value + Math.random() / 2);
    return this.trickleTimer = setTimeout(this._trickle, this.speed);
  };

  ProgressBar.prototype._withSpeed = function(speed, fn) {
    var originalSpeed, result;
    originalSpeed = this.speed;
    this.speed = speed;
    result = fn();
    this.speed = originalSpeed;
    return result;
  };

  ProgressBar.prototype._updateStyle = function(forceRepaint) {
    if (forceRepaint == null) {
      forceRepaint = false;
    }
    if (forceRepaint) {
      this._changeContentToForceRepaint();
    }
    return this.styleElement.textContent = this._createCSSRule();
  };

  ProgressBar.prototype._changeContentToForceRepaint = function() {
    return this.content = this.content === '' ? ' ' : '';
  };

  ProgressBar.prototype._createCSSRule = function() {
    return this.elementSelector + "." + className + "::before {\n  content: '" + this.content + "';\n  position: fixed;\n  top: 0;\n  left: 0;\n  z-index: 2000;\n  background-color: #0076ff;\n  height: 3px;\n  opacity: " + this.opacity + ";\n  width: " + this.value + "%;\n  transition: width " + this.speed + "ms ease-out, opacity " + (this.speed / 2) + "ms ease-in;\n  transform: translate3d(0,0,0);\n}";
  };

  return ProgressBar;

})();

ProgressBarAPI = {
  enable: ProgressBar.enable,
  disable: ProgressBar.disable,
  start: function() {
    return ProgressBar.enable().start();
  },
  advanceTo: function(value) {
    return progressBar != null ? progressBar.advanceTo(value) : void 0;
  },
  done: function() {
    return progressBar != null ? progressBar.done() : void 0;
  }
};

installDocumentReadyPageEventTriggers = function() {
  return document.addEventListener('DOMContentLoaded', (function() {
    triggerEvent(EVENTS.CHANGE, [document.body]);
    return triggerEvent(EVENTS.UPDATE);
  }), true);
};

installJqueryAjaxSuccessPageUpdateTrigger = function() {
  if (typeof jQuery !== 'undefined') {
    return jQuery(document).on('ajaxSuccess', function(event, xhr, settings) {
      if (!jQuery.trim(xhr.responseText)) {
        return;
      }
      return triggerEvent(EVENTS.UPDATE);
    });
  }
};

onHistoryChange = function(event) {
  var cachedPage, ref;
  if (((ref = event.state) != null ? ref.turbolinks : void 0) && event.state.url !== currentState.url) {
    if (cachedPage = pageCache[(new ComponentUrl(event.state.url)).absolute]) {
      cacheCurrentPage();
      return fetchHistory(cachedPage, {
        scroll: [cachedPage.positionX, cachedPage.positionY]
      });
    } else {
      return visit(event.target.location.href);
    }
  }
};

initializeTurbolinks = function() {
  rememberCurrentUrlAndState();
  ProgressBar.enable();
  document.addEventListener('click', Click.installHandlerLast, true);
  window.addEventListener('hashchange', rememberCurrentUrlAndState, false);
  return window.addEventListener('popstate', onHistoryChange, false);
};

browserSupportsPushState = window.history && 'pushState' in window.history && 'state' in window.history;

ua = navigator.userAgent;

browserIsBuggy = (ua.indexOf('Android 2.') !== -1 || ua.indexOf('Android 4.0') !== -1) && ua.indexOf('Mobile Safari') !== -1 && ua.indexOf('Chrome') === -1 && ua.indexOf('Windows Phone') === -1;

requestMethodIsSafe = (ref = popCookie('request_method')) === 'GET' || ref === '';

browserSupportsTurbolinks = browserSupportsPushState && !browserIsBuggy && requestMethodIsSafe;

browserSupportsCustomEvents = document.addEventListener && document.createEvent;

if (browserSupportsCustomEvents) {
  installDocumentReadyPageEventTriggers();
  installJqueryAjaxSuccessPageUpdateTrigger();
}

if (browserSupportsTurbolinks) {
  visit = fetch;
  initializeTurbolinks();
} else {
  visit = function(url) {
    return document.location.href = url;
  };
}

this.Turbolinks = {
  visit: visit,
  replace: replace,
  pagesCached: pagesCached,
  cacheCurrentPage: cacheCurrentPage,
  enableTransitionCache: enableTransitionCache,
  disableRequestCaching: disableRequestCaching,
  ProgressBar: ProgressBarAPI,
  allowLinkExtensions: Link.allowExtensions,
  supported: browserSupportsTurbolinks,
  EVENTS: clone(EVENTS)
};
